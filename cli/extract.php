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
 * CLI script: test the AI Grader Pro extractor on a real submission.
 *
 * Usage:
 *   php local/aigrader/cli/extract.php --submissionid=123
 *   php local/aigrader/cli/extract.php --submissionid=123 --raw
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
        'raw'          => false,
        'help'         => false,
    ],
    [
        's' => 'submissionid',
        'r' => 'raw',
        'h' => 'help',
    ]
);

if ($options['help'] || empty($options['submissionid'])) {
    cli_writeln(<<<EOT
AI Grader Pro — Submission extractor (CLI)

Reads the content of a Moodle assignment submission and shows what AI Grader
Pro would feed to the LLM for grading.

Usage:
  php local/aigrader/cli/extract.php --submissionid=<id> [--raw]

Options:
  -s, --submissionid=<id>  ID from m_assign_submission. Required.
  -r, --raw                Print only the extracted text, no metadata header.
                           Useful for piping or diffing.
  -h, --help               Show this help.

EOT);
    exit(0);
}

$submissionid = (int) $options['submissionid'];

$result = \local_aigrader\extractor\dispatcher::extract($submissionid);

if ($options['raw']) {
    cli_writeln($result->text);
    exit($result->is_ok() ? 0 : 1);
}

cli_heading('AI Grader Pro · extract.php');
cli_writeln('submissionid:  ' . $submissionid);
cli_writeln('format:        ' . $result->format);
cli_writeln('characters:    ' . $result->chars);
cli_writeln('truncated:     ' . ($result->truncated ? 'yes' : 'no'));
cli_writeln('error:         ' . ($result->error ?? '(none)'));

if (!empty($result->warnings)) {
    cli_writeln('warnings:');
    foreach ($result->warnings as $w) {
        cli_writeln('  - ' . $w);
    }
} else {
    cli_writeln('warnings:      (none)');
}

cli_writeln('');
cli_writeln('--- Extracted text ---');
cli_writeln($result->is_ok() ? $result->text : '(extractor returned no usable text)');
cli_writeln('--- end ---');

exit($result->is_ok() ? 0 : 1);
