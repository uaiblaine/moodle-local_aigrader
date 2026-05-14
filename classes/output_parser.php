<?php
/**
 * Parses the LLM's JSON response into a structured proposal.
 *
 * Handles common LLM quirks: wrapping in markdown code fences, adding
 * preamble/postamble text, returning grade on 0-100 instead of 0-10, etc.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

defined('MOODLE_INTERNAL') || die();

class output_parser {

    public static function parse(string $raw): parsed_proposal {
        if (trim($raw) === '') {
            return parsed_proposal::error('LLM returned empty response', $raw);
        }

        $cleaned = self::strip_code_fences($raw);
        $cleaned = self::extract_json_object($cleaned);

        $data = json_decode($cleaned, true);
        if (!is_array($data)) {
            $err = json_last_error_msg();
            return parsed_proposal::error('JSON decode failed: ' . $err, $raw);
        }

        // Validate required keys.
        $required = ['final_grade', 'criterion_scores', 'strengths', 'improvements', 'justification'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                return parsed_proposal::error("Missing required field: {$field}", $raw);
            }
        }

        // Normalise grade (0-10 expected; tolerate 0-100).
        $grade = is_numeric($data['final_grade']) ? (float) $data['final_grade'] : null;
        if ($grade === null) {
            return parsed_proposal::error('final_grade is not numeric', $raw);
        }
        if ($grade < 0) {
            return parsed_proposal::error("final_grade below zero: {$grade}", $raw);
        }
        if ($grade > 10) {
            if ($grade <= 100) {
                // LLM returned 0-100 by mistake; normalise.
                $grade = round($grade / 10, 2);
            } else {
                return parsed_proposal::error("final_grade out of range: {$grade}", $raw);
            }
        }

        // Validate types.
        if (!is_array($data['criterion_scores'])) {
            return parsed_proposal::error('criterion_scores is not an object/map', $raw);
        }
        if (!is_array($data['strengths'])) {
            return parsed_proposal::error('strengths is not an array', $raw);
        }
        if (!is_array($data['improvements'])) {
            return parsed_proposal::error('improvements is not an array', $raw);
        }
        if (!is_string($data['justification'])) {
            return parsed_proposal::error('justification is not a string', $raw);
        }

        // Normalise criterion_scores to floats.
        $criteria = [];
        foreach ($data['criterion_scores'] as $slug => $score) {
            if (is_numeric($score)) {
                $val = (float) $score;
                if ($val > 10 && $val <= 100) {
                    $val = round($val / 10, 2);
                }
                $criteria[(string) $slug] = $val;
            }
        }

        // Force strings on lists.
        $strengths    = array_values(array_map('strval', $data['strengths']));
        $improvements = array_values(array_map('strval', $data['improvements']));

        $language = is_string($data['feedback_language'] ?? null)
            ? $data['feedback_language']
            : '';

        return parsed_proposal::success(
            grade: round($grade, 2),
            criterion_scores: $criteria,
            strengths: $strengths,
            improvements: $improvements,
            justification: trim($data['justification']),
            language: $language,
            cleaned_json: $cleaned,
            raw_response: $raw
        );
    }

    /**
     * Strip ```json fences if the LLM wrapped its output in markdown.
     */
    private static function strip_code_fences(string $text): string {
        $text = trim($text);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?[ \t]*\r?\n/', '', $text, 1);
            $text = preg_replace('/\r?\n```[ \t]*$/', '', $text, 1);
        }
        return trim($text);
    }

    /**
     * Find the first top-level JSON object in the text by balancing braces.
     * Tolerant of preamble/postamble that some LLMs add.
     */
    private static function extract_json_object(string $text): string {
        $start = strpos($text, '{');
        if ($start === false) {
            return $text;
        }
        $depth     = 0;
        $in_string = false;
        $escape    = false;
        $len       = strlen($text);
        for ($i = $start; $i < $len; $i++) {
            $c = $text[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($c === '\\') {
                $escape = true;
                continue;
            }
            if ($c === '"') {
                $in_string = !$in_string;
                continue;
            }
            if ($in_string) {
                continue;
            }
            if ($c === '{') {
                $depth++;
            } else if ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
        return substr($text, $start);
    }
}
