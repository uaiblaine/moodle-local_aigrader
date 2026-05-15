<?php
/**
 * CLI helper: attach a local file as an assignsubmission_file to a submission.
 *
 * Use this in dev to exercise the dispatcher with real .docx/.zip/.ipynb/etc.
 *
 * Usage:
 *   php local/aigrader/cli/attach_file.php --submissionid=N --file=/path/to/foo.docx
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params(
    [
        'submissionid' => null,
        'file'         => null,
        'help'         => false,
    ],
    [
        's' => 'submissionid',
        'f' => 'file',
        'h' => 'help',
    ]
);

if ($options['help'] || empty($options['submissionid']) || empty($options['file'])) {
    cli_writeln(<<<EOT
AI Grader Pro — Attach a file to an existing assignment submission (dev only)

Usage:
  php local/aigrader/cli/attach_file.php \\
      --submissionid=<id> --file=<absolute path>

EOT);
    exit(0);
}

global $DB;

$submissionid = (int) $options['submissionid'];
$filepath     = (string) $options['file'];

if (!is_readable($filepath)) {
    cli_error('Cannot read file: ' . $filepath);
}

$assignsub = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('assign', $assignsub->assignment, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

$fs = get_file_storage();
$filename = basename($filepath);

// Remove an existing file with the same name in this submission area.
$existing = $fs->get_file($context->id, 'assignsubmission_file', 'submission_files',
    $submissionid, '/', $filename);
if ($existing) {
    $existing->delete();
    cli_writeln('Removed existing file with the same name.');
}

$fileinfo = [
    'contextid' => $context->id,
    'component' => 'assignsubmission_file',
    'filearea'  => 'submission_files',
    'itemid'    => $submissionid,
    'filepath'  => '/',
    'filename'  => $filename,
];

$stored = $fs->create_file_from_pathname($fileinfo, $filepath);

cli_writeln('Attached: ' . $filename);
cli_writeln('  fileid:    ' . $stored->get_id());
cli_writeln('  mimetype:  ' . $stored->get_mimetype());
cli_writeln('  size:      ' . $stored->get_filesize() . ' bytes');
cli_writeln('  submission ' . $submissionid . ' now has ' .
    count($fs->get_area_files($context->id, 'assignsubmission_file', 'submission_files', $submissionid, 'filename', false)) .
    ' files.');
