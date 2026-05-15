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
 * Value object returned by every extractor in AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

/**
 * Wraps the text returned by an extractor plus metadata.
 *
 * Never throws — failures are encoded as the $error property. Build instances
 * via the static factories: ::success() or ::error().
 */
class extraction_result {
    /** Hard cap for extracted text size (matches ADR-001 section 3.9). */
    public const MAX_CHARS = 300000;

    /** Format identifier for online text submissions. */
    public const FORMAT_ONLINETEXT  = 'onlinetext';

    /** Format identifier for plain .txt files. */
    public const FORMAT_TXT         = 'txt';

    /** Format identifier for .docx files. */
    public const FORMAT_DOCX        = 'docx';

    /** Format identifier for source-code files. */
    public const FORMAT_CODE        = 'code';

    /** Format identifier for .zip archives. */
    public const FORMAT_ZIP         = 'zip';

    /** Format identifier for Jupyter notebooks (.ipynb). */
    public const FORMAT_IPYNB       = 'ipynb';

    /** Format identifier used when multiple types are combined. */
    public const FORMAT_MIXED       = 'mixed';

    /** Format identifier used when no extractor could process the submission. */
    public const FORMAT_UNSUPPORTED = 'unsupported';

    /** @var string Extracted plain text (empty when $error is set). */
    public string $text;

    /** @var string One of the FORMAT_* constants. */
    public string $format;

    /** @var string[] Human-readable warnings (skipped files, truncations...). */
    public array $warnings;

    /** @var int Number of characters in $text. */
    public int $chars;

    /** @var bool True if the text was truncated to MAX_CHARS. */
    public bool $truncated;

    /** @var string|null Error message when extraction failed. */
    public ?string $error;

    /**
     * Private constructor: use ::success() or ::error().
     *
     * @param string $text Extracted text.
     * @param string $format One of the FORMAT_* constants.
     * @param string[] $warnings Warning messages.
     * @param bool $truncated Whether truncation was applied.
     * @param string|null $error Error message or null.
     */
    private function __construct(
        string $text,
        string $format,
        array $warnings,
        bool $truncated,
        ?string $error
    ) {
        $this->text      = $text;
        $this->format    = $format;
        $this->warnings  = $warnings;
        $this->truncated = $truncated;
        $this->error     = $error;
        $this->chars     = mb_strlen($text);
    }

    /**
     * Build a successful result. Truncates the text if it exceeds MAX_CHARS
     * and appends a warning.
     *
     * @param string $text Extracted text.
     * @param string $format One of the FORMAT_* constants.
     * @param string[] $warnings Optional warnings.
     * @return extraction_result
     */
    public static function success(string $text, string $format, array $warnings = []): self {
        $truncated = false;
        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
            $truncated = true;
            $warnings[] = 'Extracted text truncated to ' . self::MAX_CHARS . ' characters';
        }
        return new self($text, $format, $warnings, $truncated, null);
    }

    /**
     * Build an error result. The submission cannot be graded by this extractor.
     *
     * @param string $message Human-readable error message.
     * @param string $format Format identifier (defaults to FORMAT_UNSUPPORTED).
     * @return extraction_result
     */
    public static function error(string $message, string $format = self::FORMAT_UNSUPPORTED): self {
        return new self('', $format, [], false, $message);
    }

    /**
     * Whether the result is usable for grading.
     *
     * @return bool True if no error AND there is text to grade.
     */
    public function is_ok(): bool {
        return $this->error === null && $this->text !== '';
    }
}
