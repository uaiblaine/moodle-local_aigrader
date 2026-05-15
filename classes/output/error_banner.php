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
 * Renderer for the teacher-only error banner on the manage page.
 *
 * Takes the rows of the manage.php submission list, picks out the ones
 * whose status is "error", classifies the error message, and emits a
 * prominent notification at the top of the page grouping failures by
 * kind so the teacher can act on them as a batch.
 *
 * The banner is NEVER shown to students — manage.php requires the
 * local/aigrader:use capability, which by default is only granted to
 * editing teachers and above. This file does not need to gate visibility
 * itself; it is only invoked from the teacher page.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\output;

use html_writer;
use local_aigrader\classified_error;
use local_aigrader\error_classifier;
use moodle_url;

/**
 * Stateless renderer (no Moodle renderer base class needed — we just emit HTML).
 */
class error_banner {

    /**
     * Build the banner HTML for the manage page.
     *
     * @param array $rows Same row shape as in manage.php (objects with
     *                    `studentid`, `submissionid`, `ai_status`,
     *                    `error_message`, plus user name fields).
     * @param int   $cmid Course-module id, used to build the retry URL.
     * @return string HTML, or '' if no errors.
     */
    public static function render(array $rows, int $cmid): string {
        // Group failing rows by error kind so we show one banner per kind.
        $groups = []; // kind => [ ['row' => $r, 'classified' => $c ], ... ].
        foreach ($rows as $r) {
            if (($r->ai_status ?? '') !== 'error') {
                continue;
            }
            $classified = error_classifier::classify($r->error_message ?? '');
            $groups[$classified->kind][] = ['row' => $r, 'classified' => $classified];
        }

        if (!$groups) {
            return '';
        }

        $html = '';
        foreach ($groups as $kind => $entries) {
            $html .= self::render_group($kind, $entries, $cmid);
        }
        return $html;
    }

    /**
     * Render one banner block grouping all failures of the same kind.
     *
     * @param string $kind One of error_classifier::KIND_* constants.
     * @param array  $entries Each item: [ 'row' => stdClass, 'classified' => classified_error ].
     * @param int    $cmid
     */
    private static function render_group(string $kind, array $entries, int $cmid): string {
        // Use the first entry to source parameters (model, limit, ...). All
        // entries in this group share the same kind so the same params apply
        // approximately — if a teacher sees mixed values across students they
        // can drill into "Show raw error" for the specific one.
        /** @var classified_error $sample */
        $sample = $entries[0]['classified'];

        // Headline. Pluralise if >1 affected.
        if (count($entries) === 1) {
            $title = get_string($sample->headline_string_key(), 'local_aigrader');
        } else {
            $title = get_string('err_banner_title_plural', 'local_aigrader', count($entries))
                . ' — '
                . get_string($sample->headline_string_key(), 'local_aigrader');
        }

        // Body. For payload-too-large, prefer the parametric body when we have
        // numbers extracted; otherwise the body_partial fallback. Other kinds
        // have a single body string.
        $bodykey = $sample->body_string_key();
        $body = '';
        if ($kind === error_classifier::KIND_PAYLOAD_TOO_LARGE
            && isset($sample->params['limit'], $sample->params['requested'], $sample->params['model'])) {
            $body = get_string($bodykey, 'local_aigrader', (object) $sample->params);
        } else if ($kind === error_classifier::KIND_PAYLOAD_TOO_LARGE) {
            $body = get_string('err_payload_too_large_body_partial', 'local_aigrader');
        } else if ($kind === error_classifier::KIND_UNKNOWN) {
            $body = get_string($bodykey, 'local_aigrader', s(error_classifier::summarize_raw($sample->raw)));
        } else {
            $body = get_string($bodykey, 'local_aigrader');
        }

        // Suggested action.
        $action = get_string($sample->action_string_key(), 'local_aigrader');

        // List of affected students with retry links.
        $studentlinks = [];
        foreach ($entries as $entry) {
            $r = $entry['row'];
            $name = fullname($r);
            $retryurl = new moodle_url('/local/aigrader/retry.php', [
                'cmid'         => $cmid,
                'submissionid' => $r->submissionid,
                'sesskey'      => sesskey(),
            ]);
            $studentlinks[] = html_writer::span(
                s($name) . ' ' . html_writer::link(
                    $retryurl,
                    get_string('err_banner_retry', 'local_aigrader'),
                    ['class' => 'btn btn-sm btn-outline-danger ms-2']
                ),
                'me-3 d-inline-block'
            );
        }

        // Optional collapsible "raw error" for the curious / for support.
        $rawid = 'aigrader-raw-' . uniqid();
        $rawblock = html_writer::tag('button',
            get_string('err_banner_show_details', 'local_aigrader'),
            [
                'type' => 'button',
                'class' => 'btn btn-link btn-sm p-0',
                'data-bs-toggle' => 'collapse',
                'data-bs-target' => '#' . $rawid,
                'aria-expanded' => 'false',
                'aria-controls' => $rawid,
            ]
        );
        $rawcontent = '';
        foreach ($entries as $entry) {
            $rawcontent .= html_writer::tag(
                'pre',
                s(error_classifier::summarize_raw($entry['classified']->raw)),
                ['class' => 'small text-muted mb-1']
            );
        }
        $rawblock .= html_writer::div($rawcontent, 'collapse mt-2', ['id' => $rawid]);

        // Alert level: warning for transient (will recover), danger for
        // teacher-action-required.
        $alertlevel = $sample->is_transient() ? 'alert-warning' : 'alert-danger';

        $bodyhtml = html_writer::tag('h5', s($title), ['class' => 'alert-heading mb-2'])
            . html_writer::tag('p', s($body), ['class' => 'mb-2'])
            . html_writer::tag('p', s($action), ['class' => 'mb-2 fst-italic'])
            . html_writer::div(
                get_string('err_banner_affecting', 'local_aigrader', implode('', $studentlinks)),
                'mb-2'
            )
            . $rawblock;

        return html_writer::div(
            $bodyhtml,
            'alert ' . $alertlevel . ' aigrader-error-banner',
            ['role' => 'alert']
        );
    }
}
