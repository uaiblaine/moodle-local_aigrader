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

// -------------------------------------------------------------------.
// Handle POST: enqueue grading for one submission.
// -------------------------------------------------------------------.
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
    $submission   = $DB->get_record(
        'assign_submission',
        ['id' => $submissionid, 'assignment' => $assign->id],
        '*',
        MUST_EXIST
    );

    // Pre-insert pending row for immediate UI feedback. The manager will.
    // Upsert this row again when the task runs.
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

    // Grade synchronously in this request. The teacher just clicked the
    // button and is actively waiting; making the LLM call right here
    // gives a 2-5 s response time instead of "queued, come back after
    // the next cron tick" — see the v1.0.4 pilot feedback ("estaría
    // esperando un minuto delante de los clientes"). The async adhoc
    // task class is still kept around for future auto-grading flows
    // triggered by student submission events, which DO need the cron
    // pattern so they don't block the student's submit request.
    \core\session\manager::write_close(); // Release the session lock during the LLM call.
    @set_time_limit(120);

    $mgr    = new \local_aigrader\manager();
    $result = $mgr->grade_submission((int) $submissionid);

    if ($result->success) {
        redirect(
            new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]),
            get_string('msg_graded_now', 'local_aigrader'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if (!empty($result->needs_review)) {
        redirect(
            new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]),
            get_string('msg_needs_manual_review', 'local_aigrader'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    // Generic LLM failure (rate limit, payload, auth, parse…). The
    // detailed banner on the manage table already classifies and shows
    // the cause; here we just surface a short headline in the toast.
    $classified = \local_aigrader\error_classifier::classify((string) $result->error);
    redirect(
        new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]),
        get_string($classified->headline_string_key(), 'local_aigrader'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// -------------------------------------------------------------------.
// Render the page.
// -------------------------------------------------------------------.
$PAGE->set_url(new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('manage_pagetitle', 'local_aigrader', format_string($assign->name)));
$PAGE->set_heading($course->fullname);

// Hide the activity header (which would re-render the assignment intro at
// the top of the page). The teacher already knows the assignment from the
// breadcrumb and the heading below; surfacing the intro here pushes the
// grading queue — and any failure banner — below the fold. `activityheader`
// is a magic property on $PAGE that is always defined (lazily), so we just
// call it directly.
$PAGE->activityheader->set_attrs(['hidecompletion' => true]);
$PAGE->activityheader->disable();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_heading', 'local_aigrader', format_string($assign->name)));

if (!$config || empty($config->enabled)) {
    echo $OUTPUT->notification(
        get_string('manage_disabled', 'local_aigrader'),
        \core\output\notification::NOTIFY_WARNING
    );
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
    echo $OUTPUT->notification(
        get_string('manage_no_submissions', 'local_aigrader'),
        \core\output\notification::NOTIFY_INFO
    );
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
    echo $OUTPUT->notification(
        get_string('manage_polling', 'local_aigrader'),
        \core\output\notification::NOTIFY_INFO
    );
}

// Teacher-only banner aggregating any AI-grading failures, grouped by error
// kind. Renders before the table so the teacher cannot miss it. Students
// never reach this page (capability local/aigrader:use is checked above).
echo \local_aigrader\output\error_banner::render($rows, $cmid);

// -------------------------------------------------------------------.
// Status counters + clickable filter chips.
//
// We bucket each row into one of five user-facing buckets that map onto
// the badge colours in the table:
//   - 'ai_proposed'      green   "Propuesta IA" (the teacher's queue)
//   - 'teacher_reviewed' blue    "Revisada por profesor"
//   - 'published'        green   "Publicada"
//   - 'problems'         yellow  "Formato no soportado" + "Error"
//   - 'none'             gray    Submission with no AI status yet (status = NULL)
//
// 'pending_ai' is treated as 'none' for filtering purposes — the polling
// banner above is the right place to surface in-flight work, not a sticky
// filter on a transient state.
//
// Click on a chip filters the table to that bucket (?filter=...). Click
// again on the same chip — or on the "Todas" chip — clears the filter.
// We never hide a row from the bulk action form: the row checkboxes that
// are filtered out simply don't render, so the teacher can only act on
// what they can see. That's an intentional safety property.
// -------------------------------------------------------------------.
// PARAM_ALPHAEXT (not PARAM_ALPHA) — the bucket keys contain underscores
// (e.g. 'ai_proposed') and PARAM_ALPHA would silently strip them, making
// the filter look broken to the teacher.
$filter = optional_param('filter', '', PARAM_ALPHAEXT);
$validfilters = ['ai_proposed', 'teacher_reviewed', 'published', 'problems', 'none'];
if ($filter !== '' && !in_array($filter, $validfilters, true)) {
    $filter = '';
}

$bucketof = function ($status) {
    if ($status === null || $status === 'pending_ai') {
        return 'none';
    }
    if ($status === 'error' || $status === 'unsupported_format') {
        return 'problems';
    }
    return $status; // ai_proposed | teacher_reviewed | published.
};

$counts = ['ai_proposed' => 0, 'teacher_reviewed' => 0, 'published' => 0,
    'problems' => 0, 'none' => 0];
foreach ($rows as $r) {
    $counts[$bucketof($r->ai_status ?? null)]++;
}

// Build the chip row. Chips render even when count is 0 so the layout
// is stable across reloads; the inactive ones get a muted style.
// Force text-white on dark-background chips. Some Moodle themes
// (notably Moove) don't carry Bootstrap 5's "white text on .badge with
// .bg-primary/.bg-success" rule, leaving the count invisible. Spell it
// out so the chip stays readable independent of theme.
$chipdefs = [
    'ai_proposed'      => ['label' => 'count_ai_proposed',      'class' => 'bg-success text-white'],
    'teacher_reviewed' => ['label' => 'count_teacher_reviewed', 'class' => 'bg-primary text-white'],
    'published'        => ['label' => 'count_published',        'class' => 'bg-success text-white'],
    'problems'         => ['label' => 'count_problems',         'class' => 'bg-warning text-dark'],
    'none'             => ['label' => 'count_none',             'class' => 'bg-secondary text-white'],
];

echo html_writer::start_div('aigrader-counter mb-3 d-flex flex-wrap align-items-center gap-2');
echo html_writer::tag('strong',
    get_string('count_total', 'local_aigrader', count($rows)),
    ['class' => 'me-2']
);

foreach ($chipdefs as $key => $def) {
    $count = $counts[$key];
    $isactive = ($filter === $key);
    $ismuted = ($count === 0 && !$isactive);

    // Toggle behavior: clicking the active chip clears the filter.
    $targeturl = new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]);
    if (!$isactive) {
        $targeturl->param('filter', $key);
    }

    $chipclass = 'badge ' . $def['class'];
    if ($isactive) {
        $chipclass .= ' border border-dark';
    }
    if ($ismuted) {
        $chipclass .= ' opacity-50';
    }
    $chipclass .= ' text-decoration-none';

    echo html_writer::link(
        $targeturl,
        s(get_string($def['label'], 'local_aigrader', $count)),
        [
            'class'      => $chipclass,
            'style'      => 'cursor: pointer;',
            'aria-label' => $isactive
                ? get_string('count_clear_filter', 'local_aigrader')
                : get_string('count_filter_to', 'local_aigrader', get_string($def['label'], 'local_aigrader', $count)),
        ]
    );
}

if ($filter !== '') {
    echo html_writer::link(
        new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]),
        get_string('count_clear_filter', 'local_aigrader'),
        ['class' => 'ms-2 small']
    );
}
echo html_writer::end_div();

// Apply the filter. We keep the unfiltered $rows reference around so the
// counter math above stays honest; the table renders only $visiblerows.
$visiblerows = $rows;
if ($filter !== '') {
    $visiblerows = array_filter($rows, fn($r) => $bucketof($r->ai_status ?? null) === $filter);
}

if (empty($visiblerows)) {
    echo $OUTPUT->notification(
        get_string('count_no_rows_match_filter', 'local_aigrader'),
        \core\output\notification::NOTIFY_INFO
    );
}

// -------------------------------------------------------------------.
// Bulk actions form. Wraps the table so the teacher can apply one
// action ("Publicar tal cual", "Calificar con IA", …) to many rows in
// one click. POSTs to bulk.php which classifies, optionally shows a
// confirmation page for destructive actions, and dispatches to the
// bulk dispatcher.
// -------------------------------------------------------------------.
// Bulk dropdown is intentionally small: just one destructive action
// (publish) and one work action (grade with AI). v1.0.5 had four actions;
// the pilot feedback was that the matrix was too noisy and that "Recalificar"
// vs "Calificar con IA" was a distinction without difference for the
// teacher — they pick "Calificar con IA" and the dispatcher figures out
// whether each row is a first grade or a re-grade.
$bulkactions = [
    ''                                                       => get_string('bulk_action_choose', 'local_aigrader'),
    \local_aigrader\bulk\dispatcher::ACTION_APPROVE_PUBLISH => get_string('bulk_action_approve_publish', 'local_aigrader'),
    \local_aigrader\bulk\dispatcher::ACTION_GRADE_AI       => get_string('bulk_action_grade_ai', 'local_aigrader'),
];

// Render the bulk form OUTSIDE the table (not wrapping it) so the per-row
// "Recalificar con IA" forms below are not illegally nested. The row
// checkboxes inside the table use HTML5's `form="aigrader-bulk-form"`
// attribute to participate in this form even though they live outside it.
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/local/aigrader/bulk.php'))->out(false),
    'id'     => 'aigrader-bulk-form',
    'class'  => 'mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid',    'value' => $cmid]);

echo html_writer::start_div('aigrader-bulk-bar d-flex align-items-center gap-2 mb-2');
echo html_writer::tag('label', get_string('bulk_label_with_selected', 'local_aigrader'),
    ['for' => 'aigrader-bulk-action', 'class' => 'form-label mb-0']);

$selecthtml = html_writer::start_tag('select', [
    'name'  => 'action',
    'id'    => 'aigrader-bulk-action',
    'class' => 'form-select',
    'style' => 'max-width: 280px;',
]);
foreach ($bulkactions as $value => $label) {
    $selecthtml .= html_writer::tag('option', s($label), ['value' => $value]);
}
$selecthtml .= html_writer::end_tag('select');
echo $selecthtml;

echo html_writer::tag('button', get_string('bulk_apply', 'local_aigrader'), [
    'type'  => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

$table = new html_table();
$selectallhtml = html_writer::empty_tag('input', [
    'type'       => 'checkbox',
    'id'         => 'aigrader-select-all',
    'aria-label' => get_string('bulk_select_all', 'local_aigrader'),
]);
$table->head = [
    $selectallhtml,
    get_string('th_student', 'local_aigrader'),
    get_string('th_submitted', 'local_aigrader'),
    get_string('th_status', 'local_aigrader'),
    get_string('th_grade', 'local_aigrader'),
    get_string('th_action', 'local_aigrader'),
];
$table->attributes['class'] = 'generaltable';
$table->data = [];

foreach ($visiblerows as $r) {
    $student   = fullname($r);
    $submitted = $r->submitted_at ? userdate($r->submitted_at, get_string('strftimedatetimeshort')) : '-';
    $status    = local_aigrader_render_status($r->ai_status, $r->error_message);
    $grade     = $r->proposed_grade !== null ? format_float($r->proposed_grade, 2) . ' / 10' : '-';

    // form="aigrader-bulk-form" associates this checkbox with the bulk
    // form rendered above, so it participates in the bulk POST even
    // though it lives inside the per-row table cell (which can't be
    // wrapped in <form> without breaking the per-row enqueue button).
    $checkbox = html_writer::empty_tag('input', [
        'type'       => 'checkbox',
        'name'       => 'ids[]',
        'value'      => $r->submissionid,
        'form'       => 'aigrader-bulk-form',
        'class'      => 'aigrader-row-check',
        'aria-label' => get_string('bulk_select_row', 'local_aigrader', $student),
    ]);

    // Per-row button label is always "Calificar con IA" — see v1.0.6 UX
    // simplification: distinguishing "first grade" from "re-grade" gave
    // the teacher no actionable information (both call the same code
    // path) and made the dropdown / button text unnecessarily wordy.
    $btnlabel = get_string('btn_grade_with_ai', 'local_aigrader');

    $disabled = ($r->ai_status === 'pending_ai');

    $action = '';

    // Primary CTA: a "Revisar →" link to review.php.
    //   - For submissions WITH an AI proposal (ai_proposed / teacher_reviewed / published)
    //     review.php pre-fills the form with the AI's grade and feedback.
    //   - For submissions WITHOUT a usable AI proposal (unsupported_format, error)
    //     review.php opens the same form with empty defaults so the teacher can
    //     grade by hand without leaving AI Grader Pro for Moodle's native grader.
    //   - Hidden only for: NULL (never run) and pending_ai (in flight).
    $reviewablestatuses = ['ai_proposed', 'teacher_reviewed', 'published',
        'unsupported_format', 'error'];
    if (in_array($r->ai_status, $reviewablestatuses, true)) {
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
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'enqueue']);
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submissionid', 'value' => $r->submissionid]);
        // Button style: primary when "Calificar con IA" is the most
        // useful next action (no proposal yet), outline-secondary as the
        // soft "re-grade" option whenever a Revisar button is already the
        // primary CTA for the row.
        $isprimaryforrow = ($r->ai_status === null || $r->ai_status === 'pending_ai');
        $action .= html_writer::empty_tag('input', [
            'type'  => 'submit',
            'value' => $btnlabel,
            'class' => $isprimaryforrow ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm',
        ]);
        $action .= html_writer::end_tag('form');
    } else {
        $action .= html_writer::span(get_string('btn_pending', 'local_aigrader'), 'text-muted');
    }

    $table->data[] = [$checkbox, $student, $submitted, $status, $grade, $action];
}

echo html_writer::table($table);

// Tiny inline JS for the "select all" checkbox. No AMD module needed for
// this; it's a five-line behavior that only touches checkboxes inside the
// bulk form.
$PAGE->requires->js_init_code(<<<'JS'
    document.getElementById('aigrader-select-all')?.addEventListener('change', function(e) {
        document.querySelectorAll('.aigrader-row-check').forEach(function(c) {
            c.checked = e.target.checked;
        });
    });
JS);

// Quick-link footer.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
        get_string('manage_back_to_assignment', 'local_aigrader')
    ),
    'mt-3'
);

echo $OUTPUT->footer();

// -------------------------------------------------------------------.
// Local helpers.
// -------------------------------------------------------------------.
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
            // Truncate the raw error and drop provider marketing tails (e.g.
            // Groq's "Upgrade to Dev Tier..." URL). The full text is still
            // available in the banner's "Show raw error" disclosure.
            $badge = html_writer::span(get_string('status_error', 'local_aigrader'), 'badge bg-danger');
            if ($errormsg) {
                $short = \local_aigrader\error_classifier::summarize_raw($errormsg);
                $badge .= ' ' . html_writer::span(s($short), 'small text-muted');
            }
            return $badge;
        case 'unsupported_format':
            $badge = html_writer::span(get_string('status_unsupported', 'local_aigrader'), 'badge bg-warning text-dark');
            if ($errormsg) {
                // The dispatcher's needs_review() reason already states which
                // files were skipped and what formats we accept. Show it.
                $badge .= ' ' . html_writer::span(s($errormsg), 'small text-muted');
            }
            return $badge;
        default:
            return html_writer::span(s($status), 'badge bg-secondary');
    }
}
