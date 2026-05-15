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
 * Extractor for Jupyter notebooks (.ipynb).
 *
 * A notebook is JSON with a list of "cells" of type markdown or code,
 * each with optional outputs. We extract:
 *   - markdown cells → as text
 *   - code cells → as code with kernel language marker
 *   - text outputs (stream / execute_result) → included for context
 *   - error outputs → included (often pedagogically relevant)
 *   - image / display_data outputs → omitted with a placeholder
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;
/**
 * Class ipynb_extractor.
 */
class ipynb_extractor {
    /**
     * Extract file.
     */
    public static function extract_file(\stored_file $file): ?string {
        $raw = $file->get_content();
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['cells']) || !is_array($data['cells'])) {
            return null;
        }

        // Detect kernel language; defaults to python.
        $language = strtolower((string) ($data['metadata']['kernelspec']['language'] ?? 'python'));

        $parts = [];
        foreach ($data['cells'] as $i => $cell) {
            $type   = (string) ($cell['cell_type'] ?? '');
            $source = self::join_source($cell['source'] ?? '');

            if ($source === '' && empty($cell['outputs'])) {
                continue;
            }

            $label = '--- Cell ' . ($i + 1);

            if ($type === 'markdown') {
                $parts[] = $label . ' (markdown) ---';
                $parts[] = $source;
            } else if ($type === 'code') {
                $parts[] = $label . " (code, {$language}) ---";
                if ($source !== '') {
                    $parts[] = $source;
                }
                self::append_outputs($parts, $cell['outputs'] ?? []);
            } else if ($type === 'raw') {
                $parts[] = $label . ' (raw) ---';
                if ($source !== '') {
                    $parts[] = $source;
                }
            }
        }

        $text = trim(implode("\n", $parts));
        return $text === '' ? null : $text;
    }

    /**
     * In .ipynb the source field is sometimes a single string, sometimes
     * an array of strings (one per line).
     */
    private static function join_source($source): string {
        if (is_array($source)) {
            return rtrim(implode('', $source));
        }
        return rtrim((string) $source);
    }

    /**
     * Append outputs.
     */
    private static function append_outputs(array &$parts, array $outputs): void {
        foreach ($outputs as $output) {
            $otype = (string) ($output['output_type'] ?? '');
            switch ($otype) {
                case 'stream':
                    $text = self::join_source($output['text'] ?? '');
                    if ($text !== '') {
                        $parts[] = '--- Output (' . ($output['name'] ?? 'stdout') . ') ---';
                        $parts[] = $text;
                    }
                    break;
                case 'execute_result':
                    $text = self::join_source($output['data']['text/plain'] ?? '');
                    if ($text !== '') {
                        $parts[] = '--- Result ---';
                        $parts[] = $text;
                    }
                    break;
                case 'error':
                    $traceback = $output['traceback'] ?? [];
                    if (is_array($traceback) && $traceback) {
                        $parts[] = '--- Error ---';
                        // Strip ANSI escape sequences from traceback.
                        $clean = preg_replace('/\x1b\[[0-9;]*m/', '', implode("\n", $traceback));
                        $parts[] = $clean;
                    }
                    break;
                case 'display_data':
                    if (isset($output['data']['text/plain'])) {
                        $text = self::join_source($output['data']['text/plain']);
                        if ($text !== '') {
                            $parts[] = '--- Display ---';
                            $parts[] = $text;
                        }
                    } else {
                        $parts[] = '[Image / rich output omitted]';
                    }
                    break;
            }
        }
    }
}
