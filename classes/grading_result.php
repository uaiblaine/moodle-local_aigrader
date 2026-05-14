<?php
/**
 * Return value for manager::grade_submission().
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

defined('MOODLE_INTERNAL') || die();

class grading_result {

    public bool $success = false;
    public ?string $error = null;

    public int $submissionid;
    public ?parsed_proposal $proposal = null;

    public string $llm_provider = '';
    public string $llm_model = '';
    public int $tokens_input = 0;
    public int $tokens_output = 0;
    public float $cost_usd = 0.0;
    public int $duration_ms = 0;
    public string $prompt_hash = '';

    /** Local id of the row written to local_aigrader_submission. */
    public ?int $submission_record_id = null;

    /** Local id of the row written to local_aigrader_log. */
    public ?int $log_record_id = null;

    public function __construct(int $submissionid) {
        $this->submissionid = $submissionid;
    }

    public function mark_success(): void {
        $this->success = true;
        $this->error   = null;
    }

    public function mark_error(string $message): void {
        $this->success = false;
        $this->error   = $message;
    }
}
