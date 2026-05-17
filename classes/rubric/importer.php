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
 * Imports criteria from an assignment's gradingform_rubric definition and
 * converts it to natural-language text suitable for the LLM prompt.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\rubric;
/**
 * Class importer.
 */
class importer {
    /**
     * Returns natural-language criteria for the given course module if it has
     * a gradingform_rubric configured. Returns null if no rubric, or if the
     * grading method is not 'rubric'.
     *
     * @param int $cmid Course module id (NOT the assign instance id).
     * @return string|null
     */
    public static function import_for_cmid(int $cmid): ?string {
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');

        try {
            $context = \context_module::instance($cmid);
        } catch (\moodle_exception $e) {
            return null;
        }

        $manager = get_grading_manager($context, 'mod_assign', 'submissions');
        if ($manager->get_active_method() !== 'rubric') {
            return null;
        }

        $controller = $manager->get_controller('rubric');
        if (!$controller || !$controller->is_form_defined()) {
            return null;
        }

        $definition = $controller->get_definition();
        if (empty($definition->rubric_criteria) || !is_array($definition->rubric_criteria)) {
            return null;
        }

        return self::format_criteria($definition);
    }

    /**
     * Convert a gradingform_rubric definition into a human-readable, LLM-friendly text.
     *
     * @param \stdClass $definition Decoded gradingform_rubric instance.
     * @return string Multi-line criteria text suitable for the prompt.
     */
    private static function format_criteria(\stdClass $definition): string {
        $lines = [];

        if (!empty($definition->description)) {
            $lines[] = trim(format_text(
                $definition->description,
                $definition->descriptionformat ?? FORMAT_HTML,
                ['noclean' => true]
            ));
            $lines[] = '';
        }

        $lines[] = get_string('rubric_export_header', 'local_aigrader');
        $lines[] = '';

        foreach ($definition->rubric_criteria as $criterion) {
            $description = trim(strip_tags($criterion['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            // Compute max score for this criterion to derive an implicit weight hint.
            $maxscore = 0;
            if (!empty($criterion['levels']) && is_array($criterion['levels'])) {
                foreach ($criterion['levels'] as $level) {
                    if (isset($level['score']) && $level['score'] > $maxscore) {
                        $maxscore = (float) $level['score'];
                    }
                }
            }

            $lines[] = '- ' . $description . ' (max ' . self::format_score($maxscore) . ' pts):';

            if (!empty($criterion['levels']) && is_array($criterion['levels'])) {
                foreach ($criterion['levels'] as $level) {
                    $score = self::format_score((float) ($level['score'] ?? 0));
                    $defn = trim(strip_tags($level['definition'] ?? ''));
                    if ($defn === '') {
                        continue;
                    }
                    $lines[] = '    * ' . $score . ' pts: ' . $defn;
                }
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * Format a rubric score: drop the decimal part if integer, else keep two decimals.
     *
     * @param float $score Raw score from the rubric definition.
     * @return string Pretty-printed score for the criteria text.
     */
    private static function format_score(float $score): string {
        if (fmod($score, 1.0) === 0.0) {
            return (string) (int) $score;
        }
        return rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
    }
}
