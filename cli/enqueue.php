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
 * CLI script: enqueue a grading task for asynchronous processing by cron.
 *
 * Usage:
 *   php local/aigrader/cli/enqueue.php --submissionid=N
 *   php local/aigrader/cli/enqueue.php --submissionid=N --run
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    [
        'submissionid' => null,
        'run'          => false,
        'help'         => false,
    ],
    [
        's' => 'submissionid',
        'r' => 'run',
        'h' => 'help',
    ]
);

if ($options['help'] || empty($options['submissionid'])) {
    cli_writeln(<<<EOT
AI Grader Pro — Enqueue a grading task (CLI)

Adds a grade_submission adhoc task to Moodle's queue. The task will be
picked up by the next cron run (typically within 1 minute) and the
proposal will appear in local_aigrader_submission with status=ai_proposed
once done.

Usage:
  php local/aigrader/cli/enqueue.php --submissionid=<id> [--run]

Options:
  -s, --submissionid=<id>  ID from m_assign_submission. Required.
  -r, --run                After enqueueing, immediately run cron to
                           process the task (useful for testing).
  -h, --help               Show this help.

EOT);
    exit(0);
}

global $DB, $USER;

// In CLI context $USER->id may be 0 (no session). Default to admin so the
// Log entries have a meaningful userid for testing. In the future UI flow,
// $USER->id will be the teacher's id when they trigger grading.
if (empty($USER->id)) {
    $adminuser = $DB->get_record('user', ['username' => 'admin']);
    if ($adminuser) {
        \core\cron::setup_user($adminuser);
    }
}

$submissionid = (int) $options['submissionid'];

// Validate: submission exists, plugin is enabled on its assignment.
$submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
$config     = $DB->get_record('local_aigrader_assign', ['assignid' => $submission->assignment]);

if (!$config || empty($config->enabled)) {
    cli_writeln('ERROR: AI Grader Pro is not enabled on assignment ' . $submission->assignment);
    exit(2);
}
if (trim((string) $config->criteria_text) === '') {
    cli_writeln('ERROR: assignment ' . $submission->assignment . ' has no evaluation criteria set');
    exit(2);
}

// Enqueue.
$task = new \local_aigrader\task\grade_submission();
$task->set_custom_data((object) ['submissionid' => $submissionid]);
$task->set_userid((int) $USER->id);

\core\task\manager::queue_adhoc_task($task);

cli_writeln('Enqueued grade_submission task for submissionid=' . $submissionid);
cli_writeln('Userid (who triggered):  ' . $USER->id);
cli_writeln('Will be processed by the next cron run.');

if ($options['run']) {
    cli_writeln('');
    cli_writeln('--- Running cron now (--run flag) ---');

    // Moodle 4.5: \core\cron::run_adhoc_tasks() is the modern API.
    \core\cron::run_adhoc_tasks(time(), 0, true);

    cli_writeln('');
    cli_writeln('--- After cron ---');
    $row = $DB->get_record('local_aigrader_submission', ['submissionid' => $submissionid]);
    if ($row) {
        cli_writeln('local_aigrader_submission.status:         ' . $row->status);
        cli_writeln('local_aigrader_submission.proposed_grade: ' . ($row->proposed_grade ?? '(null)'));
        cli_writeln('local_aigrader_submission.timeprocessed:  ' . ($row->timeprocessed ?? '(null)'));
        cli_writeln('local_aigrader_submission.error_message:  ' . ($row->error_message ?? '(none)'));
    } else {
        cli_writeln('(no row in local_aigrader_submission yet — task may have failed before reaching manager)');
    }
}
