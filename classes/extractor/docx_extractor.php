<?php
/**
 * Extractor for .docx files (Microsoft Word 2007+).
 *
 * A .docx is a ZIP archive whose body lives in word/document.xml. We unzip,
 * read that XML, convert paragraph closings to newlines and strip the rest
 * of the tags. This works without external dependencies (no PhpWord needed)
 * and is sufficient for grading purposes (body text only — we do not
 * extract images, tables structure, comments or tracked changes).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

defined('MOODLE_INTERNAL') || die();

class docx_extractor {

    /**
     * @return string|null Extracted plain text or null on failure / empty.
     */
    public static function extract_file(\stored_file $file): ?string {
        $tmppath = self::copy_to_temp($file);
        if ($tmppath === null) {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmppath) !== true) {
            @unlink($tmppath);
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmppath);

        if (!$xml) {
            return null;
        }

        return self::xml_to_text($xml);
    }

    /**
     * Convert WordprocessingML XML to plain text.
     */
    private static function xml_to_text(string $xml): ?string {
        // Replace paragraph endings, line breaks and tabs with their text equivalents
        // BEFORE stripping tags — otherwise we lose paragraph separation.
        $xml = preg_replace('#</w:p>#i', "\n", $xml);
        $xml = preg_replace('#<w:br\s*/?>#i', "\n", $xml);
        $xml = preg_replace('#<w:tab\s*/?>#i', "\t", $xml);
        $xml = preg_replace('#</w:tr>#i', "\n", $xml);

        // Strip all remaining XML tags.
        $text = strip_tags($xml);

        // Decode XML entities (&amp; &lt; &#x...).
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8');

        // Normalise whitespace: collapse 3+ newlines to 2, strip trailing spaces.
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $lines = preg_split('/\n/', $text);
        $lines = array_map('rtrim', $lines);
        $text  = implode("\n", $lines);
        $text  = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Copy a stored_file to a temp path. Returns null on failure.
     */
    private static function copy_to_temp(\stored_file $file): ?string {
        try {
            $tmppath = tempnam(sys_get_temp_dir(), 'aigrader_docx_');
            $file->copy_content_to($tmppath);
            return $tmppath;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
