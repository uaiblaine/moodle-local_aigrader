<?php
/**
 * CLI helper: insert a dummy assignment submission for extractor testing.
 *
 * Creates a row in {assign_submission} + {assignsubmission_onlinetext} so we
 * can validate the extractor without manually submitting via the UI.
 *
 * Usage:
 *   php local/aigrader/cli/insert_test_submission.php \
 *       --assignid=1 \
 *       --userid=2 \
 *       --text="Some essay HTML <p>here</p>"
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
        'assignid' => null,
        'userid'   => null,
        'text'     => null,
        'help'     => false,
    ],
    [
        'a' => 'assignid',
        'u' => 'userid',
        't' => 'text',
        'h' => 'help',
    ]
);

if ($options['help'] || empty($options['assignid']) || empty($options['text'])) {
    cli_writeln(<<<EOT
AI Grader Pro — Insert dummy submission (CLI helper for testing)

Inserts a fake online text submission into the database. Useful to exercise
the extractor without going through the student UI flow.

Usage:
  php local/aigrader/cli/insert_test_submission.php \\
      --assignid=<assign.id> --text="<text or html>" [--userid=<user.id>]

Options:
  -a, --assignid=<id>  ID from m_assign. Required.
  -u, --userid=<id>    User who appears as submitter. Defaults to admin.
  -t, --text="..."     The submission text. Can include basic HTML.
  -h, --help           Show this help.

On success, prints the new submission ID to stdout (last line).
EOT);
    exit(0);
}

global $DB;

$assignid = (int) $options['assignid'];
$text     = (string) $options['text'];

$assign = $DB->get_record('assign', ['id' => $assignid], 'id, course', MUST_EXIST);

$userid = (int) ($options['userid'] ?? 0);
if (!$userid) {
    $userid = (int) $DB->get_field('user', 'id', ['username' => 'admin']);
}
if (!$userid) {
    cli_error('Cannot resolve userid');
}

$now = time();

$submission = (object) [
    'assignment'    => $assignid,
    'userid'        => $userid,
    'timecreated'   => $now,
    'timemodified'  => $now,
    'timestarted'   => $now,
    'status'        => 'submitted',
    'groupid'       => 0,
    'attemptnumber' => 0,
    'latest'        => 1,
];
$submissionid = $DB->insert_record('assign_submission', $submission);

$onlinetext = (object) [
    'assignment'   => $assignid,
    'submission'   => $submissionid,
    'onlinetext'   => $text,
    'onlineformat' => FORMAT_HTML,
];
$DB->insert_record('assignsubmission_onlinetext', $onlinetext);

cli_writeln('Inserted assign_submission id=' . $submissionid . ' user=' . $userid . ' assign=' . $assignid);
cli_writeln('Text length (bytes): ' . strlen($text));
cli_writeln('');
cli_writeln('Run the extractor:');
cli_writeln('  php local/aigrader/cli/extract.php --submissionid=' . $submissionid);

// Last line = the bare ID, easy to pipe.
echo $submissionid . "\n";
