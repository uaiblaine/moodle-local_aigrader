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
 * Adhoc task that grades a submission in the background.
 *
 * Enqueued from CLI (cli/enqueue.php) or, in future versions, from the
 * teacher UI when they click "Grade with AI". Picked up by Moodle's cron.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\task;

use local_aigrader\manager;
/**
 * Class grade_submission.
 */
class grade_submission extends \core\task\adhoc_task {
    /**
     * Returns the localised name shown in Site administration > Server > Tasks.
     */
    public function get_name(): string {
        return get_string('task_grade_submission', 'local_aigrader');
    }

    /**
     * Execute the grading. Throws on failure so Moodle retries the task with
     * its standard backoff. The manager already persists status='error' to
     * local_aigrader_submission before the exception bubbles up, so the
     * teacher UI can show the failure even while retries are in flight.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        if (!is_object($data) || empty($data->submissionid)) {
            throw new \coding_exception('grade_submission adhoc task requires custom_data->submissionid');
        }
        $submissionid = (int) $data->submissionid;

        mtrace('[local_aigrader] grading submissionid=' . $submissionid);

        $mgr    = new manager();
        $result = $mgr->grade_submission($submissionid);

        if (!$result->success) {
            // Throw so Moodle increments the task's fail count and reschedules.
            // With exponential backoff. The error is already persisted.
            throw new \moodle_exception(
                'errortaskfailed',
                'local_aigrader',
                '',
                'submissionid=' . $submissionid . ' err=' . $result->error
            );
        }

        mtrace(sprintf(
            '[local_aigrader] OK submissionid=%d grade=%s tokens=%d/%d duration=%dms',
            $submissionid,
            (string) ($result->proposal->grade ?? '?'),
            $result->tokens_input,
            $result->tokens_output,
            $result->duration_ms
        ));
    }
}
