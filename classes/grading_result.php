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
 * Return value for manager::grade_submission().
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

// Property names mirror the local_aigrader_log column names so the value.
// object can be passed straight to insert_record().
// phpcs:disable moodle.NamingConventions.ValidVariableName.MemberNameUnderscore

/**
 * Container for the outcome of a grade_submission() pipeline run.
 */
class grading_result {
    /** @var bool Whether the full pipeline succeeded. */
    public bool $success = false;

    /** @var string|null Error message when $success is false. */
    public ?string $error = null;

    /** @var int The assign_submission.id this run targeted. */
    public int $submissionid;

    /** @var parsed_proposal|null The parsed proposal when $success is true. */
    public ?parsed_proposal $proposal = null;

    /** @var string Name of the AI Subsystem provider used. */
    public string $llm_provider = '';

    /** @var string Identifier of the model used. */
    public string $llm_model = '';

    /** @var int Tokens consumed by the prompt. */
    public int $tokens_input = 0;

    /** @var int Tokens consumed by the response. */
    public int $tokens_output = 0;

    /** @var float Estimated USD cost (best-effort). */
    public float $cost_usd = 0.0;

    /** @var int Wall-clock duration of the LLM call in milliseconds. */
    public int $duration_ms = 0;

    /** @var string SHA-256 of the full prompt for dedup/lookups. */
    public string $prompt_hash = '';

    /** @var int|null id written to local_aigrader_submission. */
    public ?int $submission_record_id = null;

    /** @var int|null id written to local_aigrader_log. */
    public ?int $log_record_id = null;

    /**
     * Constructor.
     *
     * @param int $submissionid The assign_submission.id targeted by this run.
     */
    public function __construct(int $submissionid) {
        $this->submissionid = $submissionid;
    }

    /** @var bool True when the submission was short-circuited as needing manual review. */
    public bool $needs_review = false;

    /**
     * Mark the run as successful.
     */
    public function mark_success(): void {
        $this->success      = true;
        $this->error        = null;
        $this->needs_review = false;
    }

    /**
     * Mark the run as failed and store the reason.
     *
     * @param string $message Human-readable error message.
     */
    public function mark_error(string $message): void {
        $this->success      = false;
        $this->error        = $message;
        $this->needs_review = false;
    }

    /**
     * Mark the run as needing manual teacher review (e.g. the submission only
     * contained files in unsupported formats so we did not call the LLM).
     * Counts as "not success" — callers should not expect $proposal to be set.
     *
     * @param string $message Human-readable reason shown to the teacher.
     */
    public function mark_needs_review(string $message): void {
        $this->success      = false;
        $this->error        = $message;
        $this->needs_review = true;
    }
}
