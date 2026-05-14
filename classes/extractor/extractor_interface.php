<?php
/**
 * Common contract for all submission extractors in AI Grader Pro.
 *
 * Each implementation knows how to read one specific submission format
 * (online text, .docx, code files, .zip, .ipynb, ...) and convert it to
 * plain text suitable for inclusion in the LLM prompt.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

defined('MOODLE_INTERNAL') || die();

interface extractor_interface {

    /**
     * Extract the textual content of a submission.
     *
     * @param int $submissionid The {assign_submission}.id to read.
     * @return extraction_result A success or error result. Never throws.
     */
    public static function extract(int $submissionid): extraction_result;
}
