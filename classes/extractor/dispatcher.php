<?php
/**
 * Dispatcher: top-level extractor that handles online text + uploaded files.
 *
 * Replaces the previous flow where prompt builder called text_extractor::extract
 * directly. The dispatcher inspects the submission, routes each piece (online
 * text, .docx, .ipynb, .zip, code files…) to the appropriate sub-extractor,
 * combines them with path-aware headers and enforces global size caps.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

defined('MOODLE_INTERNAL') || die();

class dispatcher implements extractor_interface {

    /** Extensions we treat as code (read as text, language-tagged in the prompt). */
    private const CODE_EXTENSIONS = [
        'py', 'java', 'cpp', 'c', 'h', 'hpp', 'cs',
        'js', 'ts', 'sql', 'html', 'css', 'php', 'rb',
        'go', 'rs', 'kt', 'swift', 'json', 'xml', 'yaml',
        'yml', 'sh', 'bat',
    ];

    /** Plain text extensions. */
    private const TEXT_EXTENSIONS = ['txt', 'md'];

    /** Max characters per individual uploaded file (before truncation). */
    private const MAX_PER_FILE = 200000;

    public static function extract(int $submissionid): extraction_result {
        global $DB;

        if ($submissionid <= 0) {
            return extraction_result::error('Invalid submissionid: ' . $submissionid);
        }

        $assignsub = $DB->get_record('assign_submission', ['id' => $submissionid]);
        if (!$assignsub) {
            return extraction_result::error('No assign_submission for id=' . $submissionid);
        }

        $parts    = [];
        $warnings = [];
        $formats  = [];

        // 1. Online text submission (if any).
        $onlinerec = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submissionid]);
        if ($onlinerec && trim((string) $onlinerec->onlinetext) !== '') {
            $result = text_extractor::extract($submissionid);
            if ($result->is_ok()) {
                $parts[]   = '=== Online text submission ===';
                $parts[]   = $result->text;
                $parts[]   = '';
                $formats[] = extraction_result::FORMAT_ONLINETEXT;
                $warnings  = array_merge($warnings, $result->warnings);
            }
        }

        // 2. File submissions.
        try {
            $cm      = get_coursemodule_from_instance('assign', $assignsub->assignment, 0, false, IGNORE_MISSING);
            $context = $cm ? \context_module::instance($cm->id) : null;
        } catch (\Throwable $e) {
            $context = null;
        }

        if ($context) {
            $fs = \get_file_storage();
            $files = $fs->get_area_files(
                $context->id,
                'assignsubmission_file',
                'submission_files',
                $submissionid,
                'filepath, filename',
                false   // No directories.
            );

            foreach ($files as $file) {
                $entry = self::dispatch_file($file);
                if ($entry === null) {
                    continue;
                }
                $parts[]   = $entry['header'];
                $parts[]   = $entry['text'];
                $parts[]   = '';
                $formats[] = $entry['format'];
                if (!empty($entry['warnings'])) {
                    $warnings = array_merge($warnings, $entry['warnings']);
                }
            }
        }

        if (empty($parts)) {
            return extraction_result::error('No supported content found in submission id=' . $submissionid);
        }

        $combined = trim(implode("\n", $parts));

        // Determine the aggregate format identifier we report upstream.
        $uniqueformats = array_values(array_unique($formats));
        if (count($uniqueformats) === 1) {
            $format = $uniqueformats[0];
        } else {
            $format = extraction_result::FORMAT_MIXED;
        }

        return extraction_result::success($combined, $format, $warnings);
    }

    /**
     * Route a single stored_file to its appropriate extractor.
     *
     * @return array{header: string, text: string, format: string, warnings: string[]}|null
     */
    private static function dispatch_file(\stored_file $file): ?array {
        $filename = $file->get_filename();
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $warnings = [];

        if (in_array($ext, self::TEXT_EXTENSIONS, true)) {
            $text = (string) $file->get_content();
            return self::wrap_simple($filename, $text, extraction_result::FORMAT_TXT, $warnings);
        }

        if (in_array($ext, self::CODE_EXTENSIONS, true)) {
            $text = (string) $file->get_content();
            return self::wrap_simple($filename, $text, extraction_result::FORMAT_CODE, $warnings, $ext);
        }

        if ($ext === 'docx') {
            $text = docx_extractor::extract_file($file);
            if ($text === null) {
                return self::unsupported($filename, 'docx (could not extract — file may be malformed)');
            }
            return self::wrap_simple($filename, $text, extraction_result::FORMAT_DOCX, $warnings);
        }

        if ($ext === 'ipynb') {
            $text = ipynb_extractor::extract_file($file);
            if ($text === null) {
                return self::unsupported($filename, 'ipynb (could not parse JSON)');
            }
            return self::wrap_simple($filename, $text, extraction_result::FORMAT_IPYNB, $warnings);
        }

        if ($ext === 'zip') {
            $result = zip_extractor::extract_file($file);
            if (trim($result['text']) === '') {
                return self::unsupported($filename, 'zip (empty or only contained skipped files)');
            }
            return [
                'header'   => '=== ' . $filename . ' (zip archive) ===',
                'text'     => $result['text'],
                'format'   => extraction_result::FORMAT_ZIP,
                'warnings' => array_map(fn($w) => $filename . ': ' . $w, $result['warnings']),
            ];
        }

        // Unsupported extension: tell the docente, do not try to grade.
        return self::unsupported($filename, $ext === '' ? 'no extension' : $ext);
    }

    /**
     * @param string[] $warnings
     */
    private static function wrap_simple(string $filename, string $text, string $format, array $warnings, string $codelang = ''): ?array {
        $text = self::normalise_encoding($text);
        if (trim($text) === '') {
            return null;
        }
        if (mb_strlen($text) > self::MAX_PER_FILE) {
            $text = mb_substr($text, 0, self::MAX_PER_FILE);
            $warnings[] = $filename . ' truncated to ' . self::MAX_PER_FILE . ' characters';
        }

        $labelext = $codelang !== '' ? ' (' . $codelang . ')' : '';
        return [
            'header'   => '=== ' . $filename . $labelext . ' ===',
            'text'     => $text,
            'format'   => $format,
            'warnings' => $warnings,
        ];
    }

    private static function unsupported(string $filename, string $reason): array {
        return [
            'header'   => '=== ' . $filename . ' (UNSUPPORTED: ' . $reason . ') ===',
            'text'     => '[This file could not be processed by AI Grader Pro. The teacher will need to review it manually.]',
            'format'   => extraction_result::FORMAT_UNSUPPORTED,
            'warnings' => [$filename . ' unsupported: ' . $reason],
        ];
    }

    private static function normalise_encoding(string $content): string {
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        return $content;
    }
}
