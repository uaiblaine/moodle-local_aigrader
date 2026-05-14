<?php
/**
 * AI Grader Pro management page.
 *
 * Lists all submissions for a given assignment (cmid) and lets the teacher
 * trigger AI grading on each one. Shows current AI Grader Pro status per
 * submission and auto-refreshes while any are pending.
 *
 * URL: /local/aigrader/manage.php?cmid=<course module id>
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$cmid = required_param('cmid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/aigrader:use', $context);

$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
$config = $DB->get_record('local_aigrader_assign', ['assignid' => $assign->id]);

// -------------------------------------------------------------------
// Handle POST: enqueue grading for one submission.
// -------------------------------------------------------------------
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'enqueue' && data_submitted()) {
    require_sesskey();

    if (!$config || empty($config->enabled)) {
        throw new moodle_exception('errornotenabled', 'local_aigrader');
    }
    if (trim((string) $config->criteria_text) === '') {
        throw new moodle_exception('errornocriteria', 'local_aigrader');
    }

    $submissionid = required_param('submissionid', PARAM_INT);
    $submission   = $DB->get_record('assign_submission',
        ['id' => $submissionid, 'assignment' => $assign->id], '*', MUST_EXIST);

    // Pre-insert pending row for immediate UI feedback. The manager will
    // upsert this row again when the task runs.
    $existing = $DB->get_record('local_aigrader_submission', ['submissionid' => $submissionid]);
    $now = time();
    if (!$existing) {
        $DB->insert_record('local_aigrader_submission', (object) [
            'submissionid' => $submissionid,
            'assignid'     => (int) $assign->id,
            'courseid'     => (int) $assign->course,
            'studentid'    => (int) $submission->userid,
            'status'       => 'pending_ai',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    } else {
        $DB->update_record('local_aigrader_submission', (object) [
            'id'            => $existing->id,
            'status'        => 'pending_ai',
            'error_message' => null,
            'timemodified'  => $now,
        ]);
    }

    // Enqueue.
    $task = new \local_aigrader\task\grade_submission();
    $task->set_custom_data((object) ['submissionid' => (int) $submissionid]);
    $task->set_userid((int) $USER->id);
    \core\task\manager::queue_adhoc_task($task);

    redirect(new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]),
        get_string('msg_enqueued', 'local_aigrader'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// -------------------------------------------------------------------
// Render the page.
// -------------------------------------------------------------------
$PAGE->set_url(new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('manage_pagetitle', 'local_aigrader', format_string($assign->name)));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_heading', 'local_aigrader', format_string($assign->name)));

if (!$config || empty($config->enabled)) {
    echo $OUTPUT->notification(get_string('manage_disabled', 'local_aigrader'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

// Fetch all latest, submitted submissions for this assignment.
// Use the standard set of name fields so fullname() does not emit debug warnings.
$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
$sql = "SELECT s.id            AS submissionid,
               s.userid        AS studentid,
               s.timemodified  AS submitted_at,
               {$namefields->selects},
               ag.id            AS aigrader_id,
               ag.status        AS ai_status,
               ag.proposed_grade,
               ag.timeprocessed,
               ag.error_message
        FROM   {assign_submission} s
        JOIN   {user} u            ON u.id = s.userid
        LEFT JOIN {local_aigrader_submission} ag ON ag.submissionid = s.id
        WHERE  s.assignment = :assignid
          AND  s.latest = 1
          AND  s.status = :submitted
        ORDER BY u.lastname, u.firstname";
$rows = $DB->get_records_sql($sql, array_merge([
    'assignid'  => $assign->id,
    'submitted' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
], $namefields->params));

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('manage_no_submissions', 'local_aigrader'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// Auto-refresh while any AI grading is pending.
$haspending = false;
foreach ($rows as $r) {
    if (($r->ai_status ?? '') === 'pending_ai') {
        $haspending = true;
        break;
    }
}
if ($haspending) {
    $PAGE->requires->js_init_code("setTimeout(function() { window.location.reload(); }, 4000);");
    echo $OUTPUT->notification(get_string('manage_polling', 'local_aigrader'),
        \core\output\notification::NOTIFY_INFO);
}

$table = new html_table();
$table->head = [
    get_string('th_student',  'local_aigrader'),
    get_string('th_submitted','local_aigrader'),
    get_string('th_status',   'local_aigrader'),
    get_string('th_grade',    'local_aigrader'),
    get_string('th_action',   'local_aigrader'),
];
$table->attributes['class'] = 'generaltable';
$table->data = [];

foreach ($rows as $r) {
    $student   = fullname($r);
    $submitted = $r->submitted_at ? userdate($r->submitted_at, get_string('strftimedatetimeshort')) : '-';
    $status    = local_aigrader_render_status($r->ai_status, $r->error_message);
    $grade     = $r->proposed_grade !== null ? format_float($r->proposed_grade, 2) . ' / 10' : '-';

    // Build action: trigger or re-trigger button.
    $btnlabel = ($r->ai_status === null)
        ? get_string('btn_grade_with_ai', 'local_aigrader')
        : get_string('btn_regrade_with_ai', 'local_aigrader');

    $disabled = ($r->ai_status === 'pending_ai');

    $action = '';

    // If there's a proposal to review, that's the primary CTA.
    if (in_array($r->ai_status, ['ai_proposed', 'teacher_reviewed', 'published'], true)) {
        $reviewlabel = $r->ai_status === 'published'
            ? get_string('btn_view_published', 'local_aigrader')
            : get_string('btn_review', 'local_aigrader');
        $action .= html_writer::link(
            new moodle_url('/local/aigrader/review.php', ['submissionid' => $r->submissionid]),
            $reviewlabel,
            ['class' => 'btn btn-success btn-sm me-1']
        );
    }

    // Re-grade / Grade-with-AI button (form post that enqueues a task).
    if (!$disabled) {
        $action .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'style'  => 'display:inline;',
        ]);
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',      'value' => sesskey()]);
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',       'value' => 'enqueue']);
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submissionid', 'value' => $r->submissionid]);
        $action .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => $btnlabel,
            'class' => ($r->ai_status === null) ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm',
        ]);
        $action .= html_writer::end_tag('form');
    } else {
        $action .= html_writer::span(get_string('btn_pending', 'local_aigrader'), 'text-muted');
    }

    $table->data[] = [$student, $submitted, $status, $grade, $action];
}

echo html_writer::table($table);

// Quick-link footer.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
        get_string('manage_back_to_assignment', 'local_aigrader')
    ),
    'mt-3'
);

echo $OUTPUT->footer();

// -------------------------------------------------------------------
// Local helpers.
// -------------------------------------------------------------------
/**
 * Render the AI grading status as a badge.
 */
function local_aigrader_render_status(?string $status, ?string $errormsg): string {
    if ($status === null) {
        return html_writer::span(get_string('status_none', 'local_aigrader'), 'badge bg-secondary');
    }
    switch ($status) {
        case 'pending_ai':
            return html_writer::span(get_string('status_pending', 'local_aigrader'), 'badge bg-info');
        case 'ai_proposed':
            return html_writer::span(get_string('status_proposed', 'local_aigrader'), 'badge bg-success');
        case 'teacher_reviewed':
            return html_writer::span(get_string('status_reviewed', 'local_aigrader'), 'badge bg-primary');
        case 'published':
            return html_writer::span(get_string('status_published', 'local_aigrader'), 'badge bg-success');
        case 'error':
            $badge = html_writer::span(get_string('status_error', 'local_aigrader'), 'badge bg-danger');
            if ($errormsg) {
                $badge .= ' ' . html_writer::span(s($errormsg), 'small text-muted');
            }
            return $badge;
        case 'unsupported_format':
            return html_writer::span(get_string('status_unsupported', 'local_aigrader'), 'badge bg-warning');
        default:
            return html_writer::span(s($status), 'badge bg-secondary');
    }
}
