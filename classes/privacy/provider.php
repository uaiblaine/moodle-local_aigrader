<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

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
/**
 * Class provider.
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\core_userlist_provider, \core_privacy\local\request\plugin\provider {
    // ---------------------------------------------------------------------.
    // Metadata\provider — declare what we store.
    // ---------------------------------------------------------------------.

    /**
     * Get metadata.
     */
    public static function get_metadata(collection $collection): collection {

        // Local_aigrader_assign: per-assignment configuration.
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

        // Local_aigrader_submission: per-submission AI proposal state.
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

        // Local_aigrader_log: append-only audit log.
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

    // ---------------------------------------------------------------------.
    // Plugin\provider — contexts and export.
    // ---------------------------------------------------------------------.

    /**
     * Get contexts for userid.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        // Step 1: collect every assignid where this user has data (as student,
        // final_grader, log userid, log studentid, or config usermodified).
        $assignids = [];

        foreach (
            $DB->get_fieldset_select(
                'local_aigrader_assign',
                'assignid',
                'usermodified = ?',
                [$userid]
            ) as $aid
        ) {
            $assignids[(int) $aid] = (int) $aid;
        }

        $rows = $DB->get_records_sql(
            "SELECT DISTINCT assignid
               FROM {local_aigrader_submission}
              WHERE studentid = :uid1 OR final_grader = :uid2",
            ['uid1' => $userid, 'uid2' => $userid]
        );
        foreach ($rows as $row) {
            $assignids[(int) $row->assignid] = (int) $row->assignid;
        }

        $rows = $DB->get_records_sql(
            "SELECT DISTINCT las.assignid
               FROM {local_aigrader_submission} las
               JOIN {local_aigrader_log} lal ON lal.submissionid = las.submissionid
              WHERE lal.userid = :uid1 OR lal.studentid = :uid2",
            ['uid1' => $userid, 'uid2' => $userid]
        );
        foreach ($rows as $row) {
            $assignids[(int) $row->assignid] = (int) $row->assignid;
        }

        if (empty($assignids)) {
            return $contextlist;
        }

        // Step 2: resolve those assign ids to course-module context ids.
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($assignids), SQL_PARAMS_NAMED, 'aid');
        $params = array_merge($inparams, [
            'modname'  => 'assign',
            'ctxlevel' => CONTEXT_MODULE,
        ]);
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE ctx.contextlevel = :ctxlevel
                   AND m.name = :modname
                   AND cm.instance $insql";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get users in context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        // Use a permissive lookup (any module type) then filter — the strict
        // 'assign' variant returns false in some PHPUnit-bootstrapped contexts.
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm || $cm->modname !== 'assign') {
            return;
        }
        $assignid = (int) $cm->instance;

        // Config: usermodified.
        $userlist->add_from_sql(
            'usermodified',
            "SELECT usermodified FROM {local_aigrader_assign}
              WHERE assignid = :aid",
            ['aid' => $assignid]
        );

        // Submission: studentid and final_grader.
        $userlist->add_from_sql(
            'studentid',
            "SELECT studentid FROM {local_aigrader_submission}
              WHERE assignid = :aid",
            ['aid' => $assignid]
        );
        $userlist->add_from_sql(
            'final_grader',
            "SELECT final_grader FROM {local_aigrader_submission}
              WHERE assignid = :aid AND final_grader IS NOT NULL",
            ['aid' => $assignid]
        );

        // Log: userid (teacher) and studentid.
        $userlist->add_from_sql(
            'userid',
            "SELECT lal.userid
               FROM {local_aigrader_log} lal
               JOIN {local_aigrader_submission} las ON las.submissionid = lal.submissionid
              WHERE las.assignid = :aid",
            ['aid' => $assignid]
        );
        $userlist->add_from_sql(
            'studentid',
            "SELECT lal.studentid
               FROM {local_aigrader_log} lal
               JOIN {local_aigrader_submission} las ON las.submissionid = lal.submissionid
              WHERE las.assignid = :aid",
            ['aid' => $assignid]
        );
    }

    /**
     * Export user data.
     */
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
            $studentsubs = $DB->get_records(
                'local_aigrader_submission',
                ['assignid' => $assignid, 'studentid' => $userid]
            );
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
            $gradersubs = $DB->get_records(
                'local_aigrader_submission',
                ['assignid' => $assignid, 'final_grader' => $userid]
            );
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
            $config = $DB->get_record(
                'local_aigrader_assign',
                ['assignid' => $assignid, 'usermodified' => $userid]
            );
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
                    // Prompt_text and response_json contain the student's submission.
                    // And the LLM's response — export them for the student only.
                    'prompt_text'    => $isstudent ? $r->prompt_text : null,
                    'response_json'  => $isstudent ? $r->response_json : null,
                    'timecreated'    => transform::datetime($r->timecreated),
                ];
                writer::with_context($context)
                    ->export_data(array_merge($subroot, ['log', (string) $r->id]), $data);
            }
        }
    }

    // ---------------------------------------------------------------------.
    // Deletion paths.
    // ---------------------------------------------------------------------.

    /**
     * Delete data for all users in context.
     */
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
        $DB->delete_records_select(
            'local_aigrader_log',
            'submissionid IN (SELECT submissionid FROM {local_aigrader_submission} WHERE assignid = ?)',
            [$assignid]
        );
        // Delete all submissions for this assignment.
        $DB->delete_records('local_aigrader_submission', ['assignid' => $assignid]);
        // Delete the assignment config.
        $DB->delete_records('local_aigrader_assign', ['assignid' => $assignid]);
    }

    /**
     * Delete data for user.
     */
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

    /**
     * Delete data for users.
     */
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
        $DB->delete_records_select(
            'local_aigrader_log',
            'submissionid IN (SELECT submissionid FROM {local_aigrader_submission}
                                WHERE assignid = ? AND studentid = ?)',
            [$assignid, $userid]
        );

        // 2. Hard-delete submission rows where this user is the student.
        $DB->delete_records(
            'local_aigrader_submission',
            ['assignid' => $assignid, 'studentid' => $userid]
        );

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
