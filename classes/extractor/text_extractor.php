<?php
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

defined('MOODLE_INTERNAL') || die();

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
            // get the plain text, but flag it so we know if grading quality drops.
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
     */
    private static function normalise(string $html): string {
        // Replace common block tags with newlines BEFORE strip_tags so we don't
        // glue paragraphs together.
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
