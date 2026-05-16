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
 * "Retry now" endpoint for AI Grader Pro failures.
 *
 * Behaviour:
 *   - If there is an existing adhoc task for this submission (failed and
 *     waiting for backoff), reset its `faildelay` to 0 and its `nextruntime`
 *     to "now" so the next cron tick picks it up immediately.
 *   - If no task exists (e.g. it was discarded after too many failures, or
 *     the teacher never enqueued one to begin with), enqueue a fresh task.
 *   - Either way, reset the submission row to status='pending_ai' and clear
 *     the stale error_message so the UI does not keep showing it after the
 *     redirect.
 *
 * Capability: local/aigrader:use on the assignment context (same as the
 * "Grade with AI" button on manage.php).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$cmid         = required_param('cmid', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);
require_sesskey();

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/aigrader:use', $context);

$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

// Make sure the submission belongs to this assignment — no cross-tenant retry.
$submission = $DB->get_record(
    'assign_submission',
    ['id' => $submissionid, 'assignment' => $assign->id],
    '*',
    MUST_EXIST
);

$redirecturl = new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]);

// 1. Reset the local_aigrader_submission row to pending_ai.
$existing = $DB->get_record('local_aigrader_submission', ['submissionid' => $submissionid]);
$now = time();
if ($existing) {
    $DB->update_record('local_aigrader_submission', (object) [
        'id'            => $existing->id,
        'status'        => 'pending_ai',
        'error_message' => null,
        'timemodified'  => $now,
    ]);
}

// 2. Reset the grading task (or enqueue a fresh one). The dedupe and
// concurrent-worker handling live in \local_aigrader\task_reset so a
// unit test can exercise them without spinning up this endpoint.
\local_aigrader\task_reset::reset_grading_task($submissionid, (int) $USER->id);

redirect(
    $redirecturl,
    get_string('msg_enqueued', 'local_aigrader'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
