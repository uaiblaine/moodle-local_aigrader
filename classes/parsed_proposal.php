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
 * Value object for a parsed LLM grading proposal.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

// Properties below intentionally mirror local_aigrader_log/submission column.
// names (criterion_scores, raw_response, cleaned_json) so the value object can
// Round-trip cleanly to and from the database. The Moodle naming convention is.
// Suppressed for this whole class so we do not duplicate names in two casings.
// phpcs:disable moodle.NamingConventions.ValidVariableName.MemberNameUnderscore
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore

/**
 * Holds the result of parsing an LLM JSON response.
 *
 * Construct via the static factories: ::success() for a valid proposal or
 * ::error() when the response could not be parsed.
 */
class parsed_proposal {
    /** @var bool Whether parsing succeeded. */
    public bool $success;

    /** @var string|null Human-readable error message if $success is false. */
    public ?string $error;

    /** @var string Raw text returned by the LLM, kept for audit. */
    public string $raw_response;

    /** @var float Final grade on a 0-10 scale. */
    public float $grade;

    /** @var array<string,float> Score per criterion slug. */
    public array $criterion_scores;

    /** @var string[] List of positive points the LLM identified. */
    public array $strengths;

    /** @var string[] List of areas to improve the LLM identified. */
    public array $improvements;

    /** @var string Free-text justification for the final grade. */
    public string $justification;

    /** @var string ISO code of the language used for the human-readable fields. */
    public string $language;

    /** @var string The JSON we successfully parsed after stripping fences. */
    public string $cleaned_json;

    /**
     * Private constructor: use the success() or error() static factories.
     */
    private function __construct() {
    }

    /**
     * Build a successful result.
     *
     * @param float $grade Final grade on a 0-10 scale.
     * @param array<string,float> $criterionscores Map of slug => score.
     * @param string[] $strengths Positive points.
     * @param string[] $improvements Areas to improve.
     * @param string $justification Free-text justification.
     * @param string $language ISO language code of the textual fields.
     * @param string $cleanedjson The JSON that was successfully parsed.
     * @param string $rawresponse The original LLM response (for audit).
     * @return parsed_proposal
     */
    public static function success(
        float $grade,
        array $criterionscores,
        array $strengths,
        array $improvements,
        string $justification,
        string $language,
        string $cleanedjson,
        string $rawresponse
    ): self {
        $p = new self();
        $p->success          = true;
        $p->error            = null;
        $p->grade            = $grade;
        $p->criterion_scores = $criterionscores;
        $p->strengths        = $strengths;
        $p->improvements     = $improvements;
        $p->justification    = $justification;
        $p->language         = $language;
        $p->cleaned_json     = $cleanedjson;
        $p->raw_response     = $rawresponse;
        return $p;
    }

    /**
     * Build an error result.
     *
     * @param string $message Human-readable error message.
     * @param string $rawresponse The original LLM response (for audit).
     * @return parsed_proposal
     */
    public static function error(string $message, string $rawresponse): self {
        $p = new self();
        $p->success          = false;
        $p->error            = $message;
        $p->grade            = 0.0;
        $p->criterion_scores = [];
        $p->strengths        = [];
        $p->improvements     = [];
        $p->justification    = '';
        $p->language         = '';
        $p->cleaned_json     = '';
        $p->raw_response     = $rawresponse;
        return $p;
    }

    /**
     * Serialise the canonical proposal back to JSON for storage in
     * local_aigrader_submission.proposed_feedback.
     *
     * @return string JSON-encoded proposal.
     */
    public function as_json(): string {
        return json_encode([
            'criterion_scores'  => $this->criterion_scores,
            'final_grade'       => $this->grade,
            'strengths'         => $this->strengths,
            'improvements'      => $this->improvements,
            'justification'     => $this->justification,
            'feedback_language' => $this->language,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
