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
 * Helper to reset (or freshly enqueue) the grade_submission adhoc task
 * for a given submission.
 *
 * Used by retry.php to wire the "Retry now" button to a deterministic
 * state on the task queue: at most one row per submission, never any
 * stale faildelay backoff, and not touching tasks currently being
 * executed by a worker.
 *
 * Lives in its own class so it can be exercised by a PHPUnit test
 * without spinning up the full retry.php endpoint (which requires
 * Moodle's session, capability, and CSRF machinery).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

/**
 * Stateless reset helper.
 */
class task_reset {

    /**
     * Outcome of a reset operation.
     */
    public const RESULT_RESET   = 'reset';   // An existing task was updated.
    public const RESULT_LOCKED  = 'locked';  // Existing task is currently running; left alone.
    public const RESULT_NEW     = 'new';     // No task existed; a fresh one was enqueued.

    /**
     * Reset the grade_submission adhoc task for a submission so the next
     * cron tick picks it up immediately. Idempotent: calling it twice in
     * a row is safe and leaves a single task row.
     *
     * Why direct DML instead of \core\task\manager::reschedule_or_queue_
     * adhoc_task(): on Moodle 4.5.11 we observed that API inserting a
     * duplicate row rather than updating the existing one — even when
     * the in-memory task object had its id set — leaving N+1 rows after
     * each retry. Direct UPDATE on task_adhoc has no such risk and is
     * a one-row write.
     *
     * Tasks currently being executed (timestarted IS NOT NULL) are NOT
     * modified; the worker will finish on its own and either succeed
     * or apply its own faildelay on failure.
     *
     * @param int $submissionid The {assign_submission}.id whose task to reset.
     * @param int $userid The teacher's user id used when enqueuing a fresh task.
     * @return string One of self::RESULT_* constants. Tests assert on this.
     */
    public static function reset_grading_task(int $submissionid, int $userid): string {
        global $DB;

        $tasks   = \core\task\manager::get_adhoc_tasks(\local_aigrader\task\grade_submission::class);
        $now     = time();
        $didreset = false;
        $sawlocked = false;

        foreach ($tasks as $task) {
            $data = $task->get_custom_data();
            if (!is_object($data) || (int) ($data->submissionid ?? 0) !== $submissionid) {
                continue;
            }
            $taskid = $task->get_id();
            if (!$taskid) {
                continue; // Defensive: task without id should not happen.
            }

            // Re-read the live row state to detect concurrent worker locks.
            $row = $DB->get_record('task_adhoc', ['id' => $taskid]);
            if (!$row) {
                continue; // Disappeared (worker finished and deleted it).
            }
            if (!empty($row->timestarted)) {
                $sawlocked = true;
                continue; // Locked by a worker.
            }

            $DB->update_record('task_adhoc', (object) [
                'id'          => (int) $taskid,
                'nextruntime' => $now,
                'faildelay'   => 0,
            ]);
            $didreset = true;
        }

        if ($didreset) {
            return self::RESULT_RESET;
        }

        if ($sawlocked) {
            // A task exists but it is being executed RIGHT NOW. Don't
            // enqueue a second one — the running worker will finish and
            // produce a result. The teacher's UI will see it on next poll.
            return self::RESULT_LOCKED;
        }

        // No matching task at all → enqueue fresh.
        $task = new \local_aigrader\task\grade_submission();
        $task->set_custom_data((object) ['submissionid' => $submissionid]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task);
        return self::RESULT_NEW;
    }
}
