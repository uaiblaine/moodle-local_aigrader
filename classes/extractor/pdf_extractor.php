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
 * Extractor for PDF submissions (.pdf).
 *
 * Wraps the bundled `smalot/pdfparser` library (see ../../thirdparty/) so the
 * grading pipeline can read LaTeX-rendered reports, manuscripts, etc. without
 * requiring a system-level `pdftotext` binary.
 *
 * Defensive caps to protect Moodle's memory limit:
 *   - MAX_FILESIZE_BYTES: PDFs over this size are not parsed at all (typical
 *     11 MB PDF needed ~1 GB of PHP memory during the v1.0.2 pilot probe).
 *   - The extracted plain text is left as-is here; the dispatcher applies the
 *     standard MAX_CHARS cap downstream.
 *
 * Quality caveats (documented for the teacher in the manage banner):
 *   - LaTeX math, equations, and figures are dropped or garbled. The library
 *     extracts the textual stream of the PDF, not its visual rendering.
 *   - Multi-column papers may have their column order interleaved.
 *   - Scanned (image-only) PDFs return near-empty text — those should fall
 *     through to the dispatcher's needs_review path.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

/**
 * Class pdf_extractor.
 */
class pdf_extractor {

    /**
     * Reject PDFs larger than this. Parsing a typical 11 MB PDF in the pilot
     * required PHP memory_limit = 1 GB, well above the 128–256 MB most Moodle
     * sites run with. Above this size we bail out cleanly and the dispatcher
     * surfaces a "PDF too large to process automatically" hint.
     */
    public const MAX_FILESIZE_BYTES = 5 * 1024 * 1024; // 5 MB.

    /**
     * Minimum extracted-text length to consider the parse worthwhile. Below
     * this the PDF is probably a scan / image-only / empty / damaged and the
     * caller should treat it as unparseable.
     */
    public const MIN_USEFUL_CHARS = 200;

    /**
     * Extract plain text from a PDF stored_file.
     *
     * Returns null on any failure mode (too big, parse error, image-only,
     * out-of-memory). The dispatcher treats null as "skipped / unsupported"
     * which keeps the failure surface consistent with the other extractors.
     *
     * @param \stored_file $file Submitted PDF file.
     * @return string|null Plain text, or null when extraction failed.
     */
    public static function extract_file(\stored_file $file): ?string {
        if ($file->get_filesize() > self::MAX_FILESIZE_BYTES) {
            debugging(
                'local_aigrader: skipping PDF ' . $file->get_filename()
                . ' (size ' . $file->get_filesize()
                . ' > cap ' . self::MAX_FILESIZE_BYTES . ')',
                DEBUG_DEVELOPER
            );
            return null;
        }

        $raw = $file->get_content();
        if ($raw === false || $raw === '') {
            return null;
        }

        // Make sure the bundled library is on the autoloader. Repeated calls
        // are no-ops since composer's autoload guards itself.
        $autoload = __DIR__ . '/../../thirdparty/vendor/autoload.php';
        if (!file_exists($autoload)) {
            debugging(
                'local_aigrader: pdf parser vendor autoloader missing at ' . $autoload,
                DEBUG_DEVELOPER
            );
            return null;
        }
        require_once($autoload);

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseContent($raw);
            $text   = $pdf->getText();
        } catch (\Throwable $e) {
            debugging(
                'local_aigrader: PDF parse failed for ' . $file->get_filename()
                . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }

        $text = self::tidy($text);

        if (mb_strlen($text) < self::MIN_USEFUL_CHARS) {
            // Likely a scanned / image-only PDF. The dispatcher will mark this
            // submission for manual review the same way it does for any other
            // unprocessable input.
            return null;
        }

        return $text;
    }

    /**
     * Normalise PDF-extracted text: collapse runs of whitespace, drop control
     * characters that the library sometimes emits for ligatures / symbols.
     *
     * @param string $text Raw text from getText().
     * @return string Cleaned text suitable for the LLM prompt.
     */
    private static function tidy(string $text): string {
        // Drop NUL and most C0 control characters except tab and newline.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        // Collapse 3+ newlines into 2.
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        // Trim trailing whitespace per line.
        $lines = preg_split('/\r?\n/', $text);
        $lines = array_map('rtrim', $lines);
        return trim(implode("\n", $lines));
    }
}
