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
 * CLI runner: perform a full AI grading on a submission.
 *
 * Builds prompt -> calls AI Subsystem (which proxies to your configured
 * provider) -> parses JSON -> saves proposal to local_aigrader_submission
 * -> logs to local_aigrader_log.
 *
 * Usage:
 *   php local/aigrader/cli/grade.php --submissionid=N
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
        'help'         => false,
    ],
    [
        's' => 'submissionid',
        'h' => 'help',
    ]
);

if ($options['help'] || empty($options['submissionid'])) {
    cli_writeln(<<<EOT
AI Grader Pro — Grade a submission end-to-end (CLI)

Runs the full grading pipeline:
  build prompt -> call AI Subsystem -> parse JSON ->
  save proposal -> log audit entry

Requires the assignment to have AI Grader Pro enabled and criteria set.

Usage:
  php local/aigrader/cli/grade.php --submissionid=<id>

EOT);
    exit(0);
}

$submissionid = (int) $options['submissionid'];

cli_heading('AI Grader Pro · grade.php');
cli_writeln('submissionid: ' . $submissionid);
cli_writeln('Running... (calls the LLM, may take a few seconds)');
cli_writeln('');

$manager = new \local_aigrader\manager();
$result = $manager->grade_submission($submissionid);

cli_writeln('--- Result ---');
cli_writeln('success:        ' . ($result->success ? 'YES' : 'NO'));
cli_writeln('error:          ' . ($result->error ?? '(none)'));
cli_writeln('llm_provider:   ' . $result->llm_provider);
cli_writeln('llm_model:      ' . $result->llm_model);
cli_writeln('tokens in:      ' . $result->tokens_input);
cli_writeln('tokens out:     ' . $result->tokens_output);
cli_writeln('duration:       ' . $result->duration_ms . ' ms');
cli_writeln('prompt_hash:    ' . substr($result->prompt_hash, 0, 16) . '...');
cli_writeln('submission row: ' . ($result->submission_record_id ?? '(none)'));
cli_writeln('log row:        ' . ($result->log_record_id ?? '(none)'));

if ($result->proposal && $result->proposal->success) {
    cli_writeln('');
    cli_writeln('--- Proposal ---');
    cli_writeln('Final grade:    ' . $result->proposal->grade . ' / 10');
    cli_writeln('Language:       ' . $result->proposal->language);
    cli_writeln('');
    cli_writeln('Criterion scores:');
    foreach ($result->proposal->criterion_scores as $slug => $score) {
        cli_writeln('  - ' . str_pad($slug, 30) . ' ' . $score);
    }
    cli_writeln('');
    cli_writeln('Strengths:');
    foreach ($result->proposal->strengths as $s) {
        cli_writeln('  + ' . $s);
    }
    cli_writeln('');
    cli_writeln('Improvements:');
    foreach ($result->proposal->improvements as $i) {
        cli_writeln('  - ' . $i);
    }
    cli_writeln('');
    cli_writeln('Justification:');
    cli_writeln('  ' . $result->proposal->justification);
}

if ($result->proposal && !$result->proposal->success) {
    cli_writeln('');
    cli_writeln('--- Raw LLM response (parse failed) ---');
    cli_writeln($result->proposal->raw_response);
}

exit($result->success ? 0 : 1);
