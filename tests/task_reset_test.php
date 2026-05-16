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
 * Tests for the "Retry now" task-reset logic.
 *
 * The motivating bug: in v1.0.0-beta the Retry endpoint called
 * \core\task\manager::reschedule_or_queue_adhoc_task() which, on
 * Moodle 4.5.11, inserted a duplicate task_adhoc row each time it
 * ran rather than updating the existing one. Two consecutive retries
 * produced three task_adhoc rows for the same submission. These
 * tests pin down the new behaviour so a future regression is caught.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

/**
 * @covers \local_aigrader\task_reset
 */
final class task_reset_test extends \advanced_testcase {

    /**
     * Counts the matching adhoc tasks in task_adhoc for a given submissionid.
     */
    private function count_tasks_for(int $submissionid): int {
        global $DB;
        $rows = $DB->get_records('task_adhoc', [
            'classname' => '\\local_aigrader\\task\\grade_submission',
        ]);
        $n = 0;
        foreach ($rows as $r) {
            $cd = json_decode($r->customdata ?? '', true);
            if (is_array($cd) && (int) ($cd['submissionid'] ?? 0) === $submissionid) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Returns the single task_adhoc record for the submission, or null.
     */
    private function get_task_record(int $submissionid): ?object {
        global $DB;
        $rows = $DB->get_records('task_adhoc', [
            'classname' => '\\local_aigrader\\task\\grade_submission',
        ]);
        foreach ($rows as $r) {
            $cd = json_decode($r->customdata ?? '', true);
            if (is_array($cd) && (int) ($cd['submissionid'] ?? 0) === $submissionid) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Enqueue a real adhoc task using the manager API (same path the
     * "Grade with AI" button uses) and force a failed state on it.
     */
    private function enqueue_failed_task(int $submissionid, int $userid, int $faildelay = 120): void {
        global $DB;
        $task = new \local_aigrader\task\grade_submission();
        $task->set_custom_data((object) ['submissionid' => $submissionid]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task);

        // Simulate a failed prior run: bump faildelay and push nextruntime
        // far enough into the future that the next cron tick would skip it.
        $row = $this->get_task_record($submissionid);
        $this->assertNotNull($row, 'queue_adhoc_task should have inserted a row');
        $DB->update_record('task_adhoc', (object) [
            'id'          => $row->id,
            'faildelay'   => $faildelay,
            'nextruntime' => time() + $faildelay,
        ]);
    }

    // -------------------------------------------------------------------.
    // Behaviour
    // -------------------------------------------------------------------.

    /**
     * The headline regression: a failed task must be RESET in place, not
     * duplicated. After reset there is still exactly one row, faildelay=0,
     * nextruntime ≤ now.
     */
    public function test_failed_task_is_reset_in_place_no_duplicate(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $submissionid = 99001;

        $this->enqueue_failed_task($submissionid, (int) $user->id);
        $this->assertSame(1, $this->count_tasks_for($submissionid));

        $result = task_reset::reset_grading_task($submissionid, (int) $user->id);

        $this->assertSame(task_reset::RESULT_RESET, $result);
        $this->assertSame(1, $this->count_tasks_for($submissionid),
            'must not have created a duplicate task row');

        $row = $this->get_task_record($submissionid);
        $this->assertEquals(0, $row->faildelay, 'faildelay should be cleared');
        $this->assertLessThanOrEqual(time() + 1, (int) $row->nextruntime,
            'nextruntime should be set to roughly now');
    }

    /**
     * Calling reset twice in a row must remain idempotent. (Three retries
     * would have caused three duplicate rows in the v1.0.0-beta bug.)
     */
    public function test_reset_is_idempotent_under_repeated_calls(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $submissionid = 99002;

        $this->enqueue_failed_task($submissionid, (int) $user->id);

        task_reset::reset_grading_task($submissionid, (int) $user->id);
        task_reset::reset_grading_task($submissionid, (int) $user->id);
        task_reset::reset_grading_task($submissionid, (int) $user->id);

        $this->assertSame(1, $this->count_tasks_for($submissionid),
            'three consecutive resets must leave exactly one row');
    }

    /**
     * If no task exists yet (first ever grading on this submission), reset
     * must ENQUEUE a fresh task rather than no-op.
     */
    public function test_reset_with_no_existing_task_enqueues_new(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $submissionid = 99003;

        $this->assertSame(0, $this->count_tasks_for($submissionid));

        $result = task_reset::reset_grading_task($submissionid, (int) $user->id);

        $this->assertSame(task_reset::RESULT_NEW, $result);
        $this->assertSame(1, $this->count_tasks_for($submissionid));
    }

    /**
     * If a task is currently being executed by a worker (timestarted IS
     * NOT NULL), reset must NOT touch it and must NOT enqueue a duplicate.
     * The running worker will finish on its own.
     */
    public function test_reset_skips_running_task_and_does_not_duplicate(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $submissionid = 99004;

        // Create the task and mark it as running.
        $this->enqueue_failed_task($submissionid, (int) $user->id, faildelay: 0);
        $row = $this->get_task_record($submissionid);
        $DB->update_record('task_adhoc', (object) [
            'id'           => $row->id,
            'timestarted'  => time(),
            'hostname'     => 'test-worker',
        ]);

        $result = task_reset::reset_grading_task($submissionid, (int) $user->id);

        $this->assertSame(task_reset::RESULT_LOCKED, $result,
            'a running task must be reported as locked, not reset');
        $this->assertSame(1, $this->count_tasks_for($submissionid),
            'must not enqueue a second task while one is running');
    }

    /**
     * Multiple submissions must not interfere with each other: resetting
     * the task for submission A leaves submission B's task alone.
     */
    public function test_reset_isolates_by_submissionid(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();

        $this->enqueue_failed_task(99005, (int) $user->id, faildelay: 600);
        $this->enqueue_failed_task(99006, (int) $user->id, faildelay: 600);

        $rowbefore = $this->get_task_record(99006);
        $faildelaybefore = (int) $rowbefore->faildelay;

        task_reset::reset_grading_task(99005, (int) $user->id);

        // 99005 should now have faildelay=0.
        $row5 = $this->get_task_record(99005);
        $this->assertEquals(0, (int) $row5->faildelay);

        // 99006 should still have faildelay=600 (untouched).
        $row6 = $this->get_task_record(99006);
        $this->assertEquals($faildelaybefore, (int) $row6->faildelay,
            'reset must not affect unrelated submissions');
    }
}
