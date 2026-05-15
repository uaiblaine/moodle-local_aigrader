<?php
/**
 * Privacy Subsystem implementation for AI Grader Pro.
 *
 * Declares the personal data this plugin stores across its 3 tables
 * (local_aigrader_assign, local_aigrader_submission, local_aigrader_log)
 * plus the fact that submission text is sent to an external LLM via the
 * Moodle AI Subsystem. Implements export and delete for GDPR.
 *
 * Deletion policy:
 *   - Student data (rows where this user is the studentid) is fully deleted.
 *   - Teacher data (userid, final_grader, usermodified) is anonymised to 0,
 *     preserving the rest of the audit trail. The AI Act (Reg. 2024/1689)
 *     requires high-risk education systems to keep activity records; we keep
 *     them but strip the teacher's identity on request.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    // ---------------------------------------------------------------------
    // metadata\provider — declare what we store.
    // ---------------------------------------------------------------------

    public static function get_metadata(collection $collection): collection {

        // local_aigrader_assign: per-assignment configuration.
        $collection->add_database_table(
            'local_aigrader_assign',
            [
                'assignid'      => 'privacy:metadata:assign:assignid',
                'criteria_text' => 'privacy:metadata:assign:criteria_text',
                'usermodified'  => 'privacy:metadata:assign:usermodified',
                'timecreated'   => 'privacy:metadata:assign:timecreated',
                'timemodified'  => 'privacy:metadata:assign:timemodified',
            ],
            'privacy:metadata:assign'
        );

        // local_aigrader_submission: per-submission AI proposal state.
        $collection->add_database_table(
            'local_aigrader_submission',
            [
                'submissionid'      => 'privacy:metadata:submission:submissionid',
                'studentid'         => 'privacy:metadata:submission:studentid',
                'status'            => 'privacy:metadata:submission:status',
                'proposed_grade'    => 'privacy:metadata:submission:proposed_grade',
                'proposed_feedback' => 'privacy:metadata:submission:proposed_feedback',
                'final_grade'       => 'privacy:metadata:submission:final_grade',
                'final_feedback'    => 'privacy:metadata:submission:final_feedback',
                'final_grader'      => 'privacy:metadata:submission:final_grader',
                'timecreated'       => 'privacy:metadata:submission:timecreated',
                'timemodified'      => 'privacy:metadata:submission:timemodified',
                'timeprocessed'     => 'privacy:metadata:submission:timeprocessed',
                'timepublished'     => 'privacy:metadata:submission:timepublished',
            ],
            'privacy:metadata:submission'
        );

        // local_aigrader_log: append-only audit log.
        $collection->add_database_table(
            'local_aigrader_log',
            [
                'userid'         => 'privacy:metadata:log:userid',
                'studentid'      => 'privacy:metadata:log:studentid',
                'action'         => 'privacy:metadata:log:action',
                'llm_provider'   => 'privacy:metadata:log:llm_provider',
                'llm_model'      => 'privacy:metadata:log:llm_model',
                'prompt_text'    => 'privacy:metadata:log:prompt_text',
                'response_json'  => 'privacy:metadata:log:response_json',
                'tokens_input'   => 'privacy:metadata:log:tokens_input',
                'tokens_output'  => 'privacy:metadata:log:tokens_output',
                'proposed_grade' => 'privacy:metadata:log:proposed_grade',
                'final_grade'    => 'privacy:metadata:log:final_grade',
                'teacher_edits'  => 'privacy:metadata:log:teacher_edits',
                'timecreated'    => 'privacy:metadata:log:timecreated',
            ],
            'privacy:metadata:log'
        );

        // External LLM provider (via Moodle AI Subsystem).
        $collection->add_external_location_link(
            'ai_subsystem',
            [
                'prompt_text' => 'privacy:metadata:ai_subsystem:prompt_text',
                'userid'      => 'privacy:metadata:ai_subsystem:userid',
            ],
            'privacy:metadata:ai_subsystem'
        );

        return $collection;
    }

    // ---------------------------------------------------------------------
    // plugin\provider — contexts and export.
    // ---------------------------------------------------------------------

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Per-assignment config: user appears as usermodified.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname1
                  JOIN {local_aigrader_assign} laa ON laa.assignid = cm.instance
                 WHERE ctx.contextlevel = :ctxlevel1
                   AND laa.usermodified = :uid1";
        $params = ['modname1' => 'assign', 'ctxlevel1' => CONTEXT_MODULE, 'uid1' => $userid];
        $contextlist->add_from_sql($sql, $params);

        // Submission rows: user appears as studentid OR final_grader.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname1
                  JOIN {local_aigrader_submission} las ON las.assignid = cm.instance
                 WHERE ctx.contextlevel = :ctxlevel1
                   AND (las.studentid = :uid1 OR las.final_grader = :uid2)";
        $params = ['modname1' => 'assign', 'ctxlevel1' => CONTEXT_MODULE,
                   'uid1' => $userid, 'uid2' => $userid];
        $contextlist->add_from_sql($sql, $params);

        // Log rows: user appears as userid (teacher) OR studentid.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname1
                  JOIN {local_aigrader_submission} las ON las.assignid = cm.instance
                  JOIN {local_aigrader_log} lal ON lal.submissionid = las.submissionid
                 WHERE ctx.contextlevel = :ctxlevel1
                   AND (lal.userid = :uid1 OR lal.studentid = :uid2)";
        $params = ['modname1' => 'assign', 'ctxlevel1' => CONTEXT_MODULE,
                   'uid1' => $userid, 'uid2' => $userid];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('assign', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $assignid = (int) $cm->instance;

        // Config: usermodified.
        $userlist->add_from_sql('usermodified',
            "SELECT usermodified FROM {local_aigrader_assign} WHERE assignid = ?",
            [$assignid]);

        // Submission: studentid and final_grader.
        $userlist->add_from_sql('studentid',
            "SELECT studentid FROM {local_aigrader_submission} WHERE assignid = ?",
            [$assignid]);
        $userlist->add_from_sql('final_grader',
            "SELECT final_grader FROM {local_aigrader_submission}
              WHERE assignid = ? AND final_grader IS NOT NULL",
            [$assignid]);

        // Log: userid (teacher) and studentid.
        $userlist->add_from_sql('userid',
            "SELECT lal.userid
               FROM {local_aigrader_log} lal
               JOIN {local_aigrader_submission} las ON las.submissionid = lal.submissionid
              WHERE las.assignid = ?",
            [$assignid]);
        $userlist->add_from_sql('studentid',
            "SELECT lal.studentid
               FROM {local_aigrader_log} lal
               JOIN {local_aigrader_submission} las ON las.submissionid = lal.submissionid
              WHERE las.assignid = ?",
            [$assignid]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        $subroot = [get_string('pluginname', 'local_aigrader')];

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('assign', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $assignid = (int) $cm->instance;

            // 1. Submissions where the user is the student.
            $studentsubs = $DB->get_records('local_aigrader_submission',
                ['assignid' => $assignid, 'studentid' => $userid]);
            foreach ($studentsubs as $s) {
                $data = (object) [
                    'role'              => 'student',
                    'status'            => $s->status,
                    'proposed_grade'    => $s->proposed_grade,
                    'final_grade'       => $s->final_grade,
                    'proposed_feedback' => $s->proposed_feedback,
                    'final_feedback'    => $s->final_feedback,
                    'timecreated'       => transform::datetime($s->timecreated),
                    'timeprocessed'     => $s->timeprocessed ? transform::datetime($s->timeprocessed) : null,
                    'timepublished'     => $s->timepublished ? transform::datetime($s->timepublished) : null,
                ];
                writer::with_context($context)
                    ->export_data(array_merge($subroot, ['submissions', 'as_student', (string) $s->id]), $data);
            }

            // 2. Submissions where the user was the final_grader (teacher).
            $gradersubs = $DB->get_records('local_aigrader_submission',
                ['assignid' => $assignid, 'final_grader' => $userid]);
            foreach ($gradersubs as $s) {
                if (isset($studentsubs[$s->id])) {
                    continue; // Already exported.
                }
                $data = (object) [
                    'role'          => 'teacher_who_approved',
                    'status'        => $s->status,
                    'final_grade'   => $s->final_grade,
                    'timepublished' => $s->timepublished ? transform::datetime($s->timepublished) : null,
                ];
                writer::with_context($context)
                    ->export_data(array_merge($subroot, ['submissions', 'as_teacher', (string) $s->id]), $data);
            }

            // 3. Per-assignment config last modified by the user.
            $config = $DB->get_record('local_aigrader_assign',
                ['assignid' => $assignid, 'usermodified' => $userid]);
            if ($config) {
                $data = (object) [
                    'role'          => 'teacher_who_configured',
                    'enabled'       => (bool) $config->enabled,
                    'criteria_text' => $config->criteria_text,
                    'source'        => $config->source,
                    'timecreated'   => transform::datetime($config->timecreated),
                    'timemodified'  => transform::datetime($config->timemodified),
                ];
                writer::with_context($context)->export_data(array_merge($subroot, ['config']), $data);
            }

            // 4. Audit log rows where the user appears (either as student or teacher).
            $sql = "SELECT lal.*
                      FROM {local_aigrader_log} lal
                      JOIN {local_aigrader_submission} las ON las.submissionid = lal.submissionid
                     WHERE las.assignid = :assignid
                       AND (lal.userid = :uid1 OR lal.studentid = :uid2)
                  ORDER BY lal.id";
            $rows = $DB->get_records_sql($sql, [
                'assignid' => $assignid, 'uid1' => $userid, 'uid2' => $userid,
            ]);
            foreach ($rows as $r) {
                $isteacher = ((int) $r->userid === $userid);
                $isstudent = ((int) $r->studentid === $userid);
                $data = (object) [
                    'role'           => ($isstudent ? 'student' : '') . ($isstudent && $isteacher ? '+' : '') . ($isteacher ? 'teacher' : ''),
                    'action'         => $r->action,
                    'llm_provider'   => $r->llm_provider,
                    'llm_model'      => $r->llm_model,
                    'tokens_input'   => $r->tokens_input,
                    'tokens_output'  => $r->tokens_output,
                    'proposed_grade' => $r->proposed_grade,
                    'final_grade'    => $r->final_grade,
                    // prompt_text and response_json contain the student's submission
                    // and the LLM's response — export them for the student only.
                    'prompt_text'    => $isstudent ? $r->prompt_text : null,
                    'response_json'  => $isstudent ? $r->response_json : null,
                    'timecreated'    => transform::datetime($r->timecreated),
                ];
                writer::with_context($context)
                    ->export_data(array_merge($subroot, ['log', (string) $r->id]), $data);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Deletion paths.
    // ---------------------------------------------------------------------

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('assign', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $assignid = (int) $cm->instance;

        // Delete log rows for any submission of this assignment.
        $DB->delete_records_select('local_aigrader_log',
            'submissionid IN (SELECT submissionid FROM {local_aigrader_submission} WHERE assignid = ?)',
            [$assignid]);
        // Delete all submissions for this assignment.
        $DB->delete_records('local_aigrader_submission', ['assignid' => $assignid]);
        // Delete the assignment config.
        $DB->delete_records('local_aigrader_assign', ['assignid' => $assignid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('assign', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $assignid = (int) $cm->instance;

            self::erase_user_in_assignment($assignid, $userid);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('assign', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $assignid = (int) $cm->instance;

        foreach ($userlist->get_userids() as $userid) {
            self::erase_user_in_assignment($assignid, (int) $userid);
        }
    }

    /**
     * Apply the deletion policy for a single user within a single assignment.
     *   - As student: hard-delete log rows + submission row.
     *   - As teacher: anonymise (set the userid field to 0) in log/submission/config.
     */
    private static function erase_user_in_assignment(int $assignid, int $userid): void {
        global $DB;

        // 1. Hard-delete log rows where this user is the student.
        $DB->delete_records_select('local_aigrader_log',
            'submissionid IN (SELECT submissionid FROM {local_aigrader_submission}
                                WHERE assignid = ? AND studentid = ?)',
            [$assignid, $userid]);

        // 2. Hard-delete submission rows where this user is the student.
        $DB->delete_records('local_aigrader_submission',
            ['assignid' => $assignid, 'studentid' => $userid]);

        // 3. Anonymise teacher userid in log rows belonging to this assignment.
        $DB->execute(
            "UPDATE {local_aigrader_log}
                SET userid = 0
              WHERE userid = ?
                AND submissionid IN (SELECT submissionid FROM {local_aigrader_submission}
                                       WHERE assignid = ?)",
            [$userid, $assignid]
        );

        // 4. Anonymise final_grader in submission rows belonging to this assignment.
        $DB->execute(
            "UPDATE {local_aigrader_submission}
                SET final_grader = 0
              WHERE assignid = ? AND final_grader = ?",
            [$assignid, $userid]
        );

        // 5. Anonymise usermodified in the assignment config.
        $DB->execute(
            "UPDATE {local_aigrader_assign}
                SET usermodified = 0
              WHERE assignid = ? AND usermodified = ?",
            [$assignid, $userid]
        );
    }
}
