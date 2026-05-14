<?php
/**
 * Value object returned by every extractor in AI Grader Pro.
 *
 * Wraps the extracted text plus metadata about format, warnings, truncation
 * and errors. Never throws — failures are encoded as $error.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

defined('MOODLE_INTERNAL') || die();

class extraction_result {

    /** Hard cap for extracted text size (matches ADR-001 section 3.9). */
    public const MAX_CHARS = 300000;

    /** Format identifiers persisted into local_aigrader_submission.submission_format. */
    public const FORMAT_ONLINETEXT  = 'onlinetext';
    public const FORMAT_TXT         = 'txt';
    public const FORMAT_DOCX        = 'docx';
    public const FORMAT_CODE        = 'code';
    public const FORMAT_ZIP         = 'zip';
    public const FORMAT_IPYNB       = 'ipynb';
    public const FORMAT_MIXED       = 'mixed';
    public const FORMAT_UNSUPPORTED = 'unsupported';

    public string $text;
    public string $format;
    /** @var string[] */
    public array $warnings;
    public int $chars;
    public bool $truncated;
    public ?string $error;

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
     */
    public static function error(string $message, string $format = self::FORMAT_UNSUPPORTED): self {
        return new self('', $format, [], false, $message);
    }

    public function is_ok(): bool {
        return $this->error === null && $this->text !== '';
    }
}
