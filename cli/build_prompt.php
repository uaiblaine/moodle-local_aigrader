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
 * CLI script: build (and inspect) the full LLM prompt for a submission.
 *
 * Use this to preview exactly what AI Grader Pro would send to the LLM,
 * without spending any API call. Great for iterating on criteria.
 *
 * Usage:
 *   php local/aigrader/cli/build_prompt.php --submissionid=N
 *   php local/aigrader/cli/build_prompt.php --submissionid=N --raw
 *   php local/aigrader/cli/build_prompt.php --submissionid=N --json
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
        'json'         => false,
        'help'         => false,
    ],
    [
        's' => 'submissionid',
        'r' => 'raw',
        'j' => 'json',
        'h' => 'help',
    ]
);

if ($options['help'] || empty($options['submissionid'])) {
    cli_writeln(<<<EOT
AI Grader Pro — Prompt builder (CLI preview)

Composes the full LLM prompt for a submission and prints it. No API call
is made; this is purely for inspection.

Usage:
  php local/aigrader/cli/build_prompt.php --submissionid=<id> [--raw|--json]

Options:
  -s, --submissionid=<id>  ID from m_assign_submission. Required.
  -r, --raw                Print system+user messages only, no metadata header.
                           Useful for piping to a real LLM via cli tools.
  -j, --json               Print the chat-messages array as JSON.
                           Useful for piping to llm/openai/anthropic CLIs.
  -h, --help               Show this help.

EOT);
    exit(0);
}

$submissionid = (int) $options['submissionid'];

try {
    $prompt = \local_aigrader\prompt\builder::build_for_submission($submissionid);
} catch (\Throwable $e) {
    cli_writeln('ERROR: ' . $e->getMessage());
    exit(2);
}

if ($options['json']) {
    cli_writeln(json_encode($prompt->as_chat_messages(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    exit(0);
}

if ($options['raw']) {
    cli_writeln('[SYSTEM]');
    cli_writeln($prompt->system_message);
    cli_writeln('');
    cli_writeln('[USER]');
    cli_writeln($prompt->user_message);
    exit(0);
}

cli_heading('AI Grader Pro · build_prompt.php');
cli_writeln('submissionid:      ' . $submissionid);
cli_writeln('assignid:          ' . $prompt->metadata['assignid']);
cli_writeln('courseid:          ' . $prompt->metadata['courseid']);
cli_writeln('studentid:         ' . $prompt->metadata['studentid']);
cli_writeln('language:          ' . $prompt->metadata['language']);
cli_writeln('model_override:    ' . ($prompt->metadata['model_override'] ?? '(none)'));
cli_writeln('submission_format: ' . $prompt->metadata['submission_format']);
cli_writeln('submission_chars:  ' . $prompt->metadata['submission_chars']);
cli_writeln('system_chars:      ' . mb_strlen($prompt->system_message));
cli_writeln('user_chars:        ' . mb_strlen($prompt->user_message));
cli_writeln('total_chars:       ' . $prompt->total_chars());
cli_writeln('prompt_hash:       ' . $prompt->hash());

if (!empty($prompt->metadata['extraction_warnings'])) {
    cli_writeln('extraction warnings:');
    foreach ($prompt->metadata['extraction_warnings'] as $w) {
        cli_writeln('  - ' . $w);
    }
}

cli_writeln('');
cli_writeln('=========================== SYSTEM MESSAGE ===========================');
cli_writeln($prompt->system_message);
cli_writeln('');
cli_writeln('============================ USER MESSAGE ============================');
cli_writeln($prompt->user_message);
cli_writeln('=========================== end of prompt ============================');

exit(0);
