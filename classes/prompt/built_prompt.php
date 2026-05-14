<?php
/**
 * Value object representing a fully composed LLM prompt for grading.
 *
 * Holds the chat-style system and user messages plus metadata (submissionid,
 * assignid, courseid, target language, source format...). The manager will
 * pass this to the AI Subsystem.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\prompt;

defined('MOODLE_INTERNAL') || die();

class built_prompt {

    public string $system_message;
    public string $user_message;
    /** @var array<string,mixed> */
    public array $metadata;

    public function __construct(string $system, string $user, array $metadata = []) {
        $this->system_message = $system;
        $this->user_message   = $user;
        $this->metadata       = $metadata;
    }

    public function total_chars(): int {
        return mb_strlen($this->system_message) + mb_strlen($this->user_message);
    }

    /**
     * OpenAI/Anthropic-compatible chat messages array.
     *
     * @return array<int, array{role:string,content:string}>
     */
    public function as_chat_messages(): array {
        return [
            ['role' => 'system', 'content' => $this->system_message],
            ['role' => 'user',   'content' => $this->user_message],
        ];
    }

    /**
     * SHA-256 hash of the full prompt. Used for dedup and the audit log
     * column local_aigrader_log.prompt_hash.
     */
    public function hash(): string {
        return hash('sha256', $this->system_message . "\n" . $this->user_message);
    }
}
