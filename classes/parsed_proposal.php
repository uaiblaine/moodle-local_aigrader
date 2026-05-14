<?php
/**
 * Value object for a parsed LLM grading proposal.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

defined('MOODLE_INTERNAL') || die();

class parsed_proposal {

    public bool $success;
    public ?string $error;
    public string $raw_response;

    public float $grade;
    /** @var array<string,float> */
    public array $criterion_scores;
    /** @var string[] */
    public array $strengths;
    /** @var string[] */
    public array $improvements;
    public string $justification;
    public string $language;

    /** @var string Cleaned JSON that we successfully parsed. */
    public string $cleaned_json;

    private function __construct() {
    }

    public static function success(
        float $grade,
        array $criterion_scores,
        array $strengths,
        array $improvements,
        string $justification,
        string $language,
        string $cleaned_json,
        string $raw_response
    ): self {
        $p = new self();
        $p->success          = true;
        $p->error            = null;
        $p->grade            = $grade;
        $p->criterion_scores = $criterion_scores;
        $p->strengths        = $strengths;
        $p->improvements     = $improvements;
        $p->justification    = $justification;
        $p->language         = $language;
        $p->cleaned_json     = $cleaned_json;
        $p->raw_response     = $raw_response;
        return $p;
    }

    public static function error(string $message, string $raw_response): self {
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
        $p->raw_response     = $raw_response;
        return $p;
    }

    /**
     * Serialise the canonical proposal back to JSON for storage in
     * local_aigrader_submission.proposed_feedback.
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
