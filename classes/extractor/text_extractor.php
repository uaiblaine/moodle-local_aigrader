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
 * Extractor for online text submissions (assignsubmission_onlinetext).
 *
 * Reads the submission's onlinetext HTML, strips tags, normalises whitespace
 * and returns plain text ready for the LLM prompt.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;
/**
 * Class text_extractor.
 */
class text_extractor implements extractor_interface {
    /**
     * Extract content from an online text submission.
     *
     * @param int $submissionid The {assign_submission}.id
     * @return extraction_result
     */
    public static function extract(int $submissionid): extraction_result {
        global $DB;

        if ($submissionid <= 0) {
            return extraction_result::error('Invalid submissionid: ' . $submissionid);
        }

        $rec = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submissionid]);
        if (!$rec) {
            return extraction_result::error(
                'No online text submission found for submissionid=' . $submissionid
            );
        }

        $raw = (string) ($rec->onlinetext ?? '');
        $text = self::normalise($raw);

        if ($text === '') {
            return extraction_result::error(
                'Online text submission is empty for submissionid=' . $submissionid
            );
        }

        $warnings = [];
        if (mb_strlen($raw) > mb_strlen($text) * 5) {
            // Heuristic: HTML markup was more than 5x the resulting text.
            // Probably a rich editor with lots of formatting; the LLM will still
            // Get the plain text, but flag it so we know if grading quality drops.
            $warnings[] = 'Submission had heavy HTML markup; only plain text was extracted';
        }

        return extraction_result::success($text, extraction_result::FORMAT_ONLINETEXT, $warnings);
    }

    /**
     * Convert raw HTML from an online text submission to plain text.
     *
     * - Replaces block-level tags with newlines so paragraphs stay separated.
     * - Strips remaining tags.
     * - Decodes HTML entities.
     * - Normalises whitespace.
     *
     * @param string $html Raw HTML from the online-text submission editor.
     * @return string Plain-text version safe to embed in the LLM prompt.
     */
    private static function normalise(string $html): string {
        // Replace common block tags with newlines BEFORE strip_tags so we don't.
        // Glue paragraphs together.
        $html = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n", $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse runs of 3+ newlines into 2 (preserve paragraph breaks).
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // Strip trailing whitespace per line.
        $lines = preg_split('/\n/', $text);
        $lines = array_map('rtrim', $lines);
        $text = implode("\n", $lines);

        return trim($text);
    }
}
