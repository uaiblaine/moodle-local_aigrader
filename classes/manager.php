<?php
/**
 * Orchestrates the end-to-end grading pipeline:
 *
 *   build_prompt → call AI Subsystem → parse JSON →
 *   persist proposal to local_aigrader_submission → log to local_aigrader_log
 *
 * Returns a grading_result that the caller (CLI, future adhoc task, future
 * AJAX endpoint) can present to the user.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

use local_aigrader\prompt\builder;
use local_aigrader\prompt\built_prompt;

defined('MOODLE_INTERNAL') || die();

class manager {

    public function grade_submission(int $submissionid): grading_result {
        global $DB, $USER;

        $start  = microtime(true);
        $result = new grading_result($submissionid);
        $prompt = null;
        $submrecid = null;

        try {
            // 1. Build prompt (throws if config missing or extraction fails).
            $prompt = builder::build_for_submission($submissionid);
            $result->prompt_hash = $prompt->hash();
            $result->llm_model   = (string) ($prompt->metadata['model_override'] ?? '');

            // 2. Find/create the local_aigrader_submission row in pending state.
            $submrecid = self::upsert_submission_row($prompt, status: 'pending_ai');
            $result->submission_record_id = $submrecid;

            // 3. Resolve module context for the AI Subsystem call.
            $assignid = (int) $prompt->metadata['assignid'];
            [$course, $cm] = get_course_and_cm_from_instance($assignid, 'assign');
            $context = \context_module::instance($cm->id);

            // 4. Build and dispatch the action via Moodle's AI Subsystem.
            //
            // In Moodle 4.5, generate_text only accepts prompttext (no
            // systeminstruction parameter — added in 4.6+). We concatenate
            // our system+user messages so the LLM sees the full instructions.
            // The provider's own systeminstruction config should be EMPTY so
            // it does not interfere with our prompt.
            $combinedprompt = $prompt->system_message . "\n\n--- TASK ---\n\n" . $prompt->user_message;

            $action = new \core_ai\aiactions\generate_text(
                contextid: $context->id,
                userid: (int) $USER->id,
                prompttext: $combinedprompt,
            );

            /** @var \core_ai\manager $aimanager */
            $aimanager = \core\di::get(\core_ai\manager::class);
            // Moodle 4.5: process_action takes only the action (no component arg).
            $response  = $aimanager->process_action($action);

            $result->duration_ms = (int) round((microtime(true) - $start) * 1000);

            // 5. Check AI Subsystem success.
            if (!$response->get_success()) {
                $err = trim(($response->get_errorcode() ?? '') . ': ' . ($response->get_errormessage() ?? ''));
                if ($err === ':') {
                    $err = 'AI Subsystem call failed (no error message provided)';
                }
                self::mark_submission_error($submrecid, $err);
                $result->log_record_id = self::log_action('grade', $prompt, null, $response, $err, $result->duration_ms);
                $result->mark_error($err);
                return $result;
            }

            $data = $response->get_response_data();
            $responsetext = (string) ($data['generatedcontent'] ?? '');
            $result->tokens_input  = (int) ($data['prompttokens'] ?? 0);
            $result->tokens_output = (int) ($data['completiontokens'] ?? 0);

            // The openai provider response does not include 'model' in 4.5;
            // read it from the provider's configured model setting instead.
            if (empty($result->llm_model)) {
                $result->llm_model = (string) get_config(
                    'aiprovider_openai',
                    'action_generate_text_model'
                );
            }
            $result->llm_provider = 'openai'; // We use the openai provider (possibly pointed at Groq).

            // 6. Parse the LLM response into a structured proposal.
            $proposal = output_parser::parse($responsetext);

            if (!$proposal->success) {
                $err = 'parse_error: ' . $proposal->error;
                self::mark_submission_error($submrecid, $err);
                $result->log_record_id = self::log_action('grade', $prompt, $proposal, $response, $err, $result->duration_ms);
                $result->proposal = $proposal;
                $result->mark_error($err);
                return $result;
            }

            // 7. Persist the proposal.
            $now = time();
            $DB->update_record('local_aigrader_submission', (object) [
                'id'                => $submrecid,
                'status'            => 'ai_proposed',
                'proposed_grade'    => $proposal->grade,
                'proposed_feedback' => $proposal->as_json(),
                'error_message'     => null,
                'timeprocessed'     => $now,
                'timemodified'      => $now,
            ]);

            // 8. Log the action.
            $result->log_record_id = self::log_action('grade', $prompt, $proposal, $response, null, $result->duration_ms);

            $result->proposal = $proposal;
            $result->mark_success();
            return $result;

        } catch (\Throwable $e) {
            $result->duration_ms = (int) round((microtime(true) - $start) * 1000);
            $msg = 'exception: ' . $e->getMessage();
            if ($submrecid !== null) {
                self::mark_submission_error($submrecid, $msg);
            }
            if ($prompt !== null) {
                $result->log_record_id = self::log_action('grade', $prompt, null, null, $msg, $result->duration_ms);
            }
            $result->mark_error($msg);
            return $result;
        }
    }

    /**
     * Insert or update the local_aigrader_submission row, returning its id.
     */
    private static function upsert_submission_row(built_prompt $prompt, string $status): int {
        global $DB;

        $submissionid = (int) $prompt->metadata['submissionid'];
        $existing = $DB->get_record('local_aigrader_submission', ['submissionid' => $submissionid]);

        $now = time();
        $rec = (object) [
            'submissionid' => $submissionid,
            'assignid'     => (int) $prompt->metadata['assignid'],
            'courseid'     => (int) $prompt->metadata['courseid'],
            'studentid'    => (int) $prompt->metadata['studentid'],
            'status'       => $status,
            'error_message' => null,
            'timemodified' => $now,
        ];

        if ($existing) {
            $rec->id = $existing->id;
            $DB->update_record('local_aigrader_submission', $rec);
            return (int) $existing->id;
        }
        $rec->timecreated = $now;
        return (int) $DB->insert_record('local_aigrader_submission', $rec);
    }

    private static function mark_submission_error(int $localid, string $message): void {
        global $DB;
        $DB->update_record('local_aigrader_submission', (object) [
            'id'            => $localid,
            'status'        => 'error',
            'error_message' => $message,
            'timeprocessed' => time(),
            'timemodified'  => time(),
        ]);
    }

    /**
     * Write an entry to local_aigrader_log. Returns its id.
     */
    private static function log_action(
        string $action,
        built_prompt $prompt,
        ?parsed_proposal $proposal,
        ?object $response,
        ?string $error,
        int $duration_ms
    ): int {
        global $DB, $USER;

        $now = time();

        $data = $response ? $response->get_response_data() : [];

        // Resolve model name: API response > assignment override > provider default.
        $modelname = (string) ($data['model'] ?? '');
        if ($modelname === '') {
            $modelname = (string) ($prompt->metadata['model_override'] ?? '');
        }
        if ($modelname === '') {
            $modelname = (string) get_config('aiprovider_openai', 'action_generate_text_model');
        }

        $rec = (object) [
            'submissionid'      => (int) $prompt->metadata['submissionid'],
            'userid'            => (int) $USER->id,
            'studentid'         => (int) $prompt->metadata['studentid'],
            'courseid'          => (int) $prompt->metadata['courseid'],
            'action'            => $action,
            'llm_provider'      => 'openai',
            'llm_model'         => $modelname,
            'prompt_hash'       => $prompt->hash(),
            'prompt_text'       => $prompt->system_message . "\n\n" . $prompt->user_message,
            'response_json'     => $proposal ? $proposal->as_json() : ($response ? json_encode($data) : null),
            'tokens_input'      => (int) ($data['prompttokens'] ?? 0),
            'tokens_output'     => (int) ($data['completiontokens'] ?? 0),
            'cost_usd'          => 0.0,
            'duration_ms'       => $duration_ms,
            'proposed_grade'    => $proposal && $proposal->success ? $proposal->grade : null,
            'final_grade'       => null,
            'teacher_edits'     => null,
            'submission_format' => (string) ($prompt->metadata['submission_format'] ?? ''),
            'timecreated'       => $now,
        ];

        if ($error !== null) {
            // Append error to response_json so it's still queryable.
            $rec->response_json = json_encode([
                'error' => $error,
                'partial' => $rec->response_json,
            ]);
        }

        return (int) $DB->insert_record('local_aigrader_log', $rec);
    }
}
