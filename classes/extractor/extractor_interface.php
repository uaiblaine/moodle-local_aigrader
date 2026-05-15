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
/**
 * Interface extractor_interface.
 */
interface extractor_interface {
    /**
     * Extract the textual content of a submission.
     *
     * @param int $submissionid The {assign_submission}.id to read.
     * @return extraction_result A success or error result. Never throws.
     */
    public static function extract(int $submissionid): extraction_result;
}
