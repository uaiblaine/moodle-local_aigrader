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
 * Unit tests for the raw-error -> classified-error mapping.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

/**
 * @covers \local_aigrader\error_classifier
 */
final class error_classifier_test extends \advanced_testcase {

    /**
     * The real Groq 413 string that prompted this feature.
     */
    public function test_groq_413_payload_too_large(): void {
        $raw = '413: Request too large for model `llama-3.3-70b-versatile` in '
            . 'organization `org_01kas5k6kffehbcg578v39g1bz` service tier '
            . '`on_demand` on tokens per minute (TPM): Limit 12000, Requested '
            . '14003, please reduce your message size and try again. Need more '
            . 'tokens? Upgrade to Dev Tier today at https://console.groq.com/'
            . 'settings/billing';

        $c = error_classifier::classify($raw);

        $this->assertSame(error_classifier::KIND_PAYLOAD_TOO_LARGE, $c->kind);
        $this->assertSame(12000, $c->params['limit']);
        $this->assertSame(14003, $c->params['requested']);
        $this->assertSame('llama-3.3-70b-versatile', $c->params['model']);
        $this->assertFalse($c->is_transient(), 'payload errors require teacher action');
    }

    /**
     * Generic context-length error from openai-compatible APIs.
     */
    public function test_context_length_exceeded(): void {
        $raw = 'context_length_exceeded: This model\'s maximum context length is 8192 tokens.';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_PAYLOAD_TOO_LARGE, $c->kind);
    }

    public function test_unauthorized_401(): void {
        $raw = '401: Incorrect API key provided: sk-XXXX. You can find your API key at https://...';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_UNAUTHORIZED, $c->kind);
        $this->assertFalse($c->is_transient());
    }

    public function test_invalid_api_key_phrase(): void {
        $raw = 'Invalid API key';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_UNAUTHORIZED, $c->kind);
    }

    public function test_rate_limited_429(): void {
        $raw = '429: Too Many Requests. Rate limit reached for requests.';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_RATE_LIMITED, $c->kind);
        $this->assertTrue($c->is_transient(), 'rate limits resolve themselves');
    }

    public function test_provider_500(): void {
        $raw = '500: Internal Server Error';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_PROVIDER_ERROR, $c->kind);
        $this->assertTrue($c->is_transient());
    }

    public function test_provider_503(): void {
        $raw = '503: Service Unavailable';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_PROVIDER_ERROR, $c->kind);
    }

    public function test_network_timeout(): void {
        $raw = 'cURL error 28: Operation timed out after 30000 milliseconds';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_NETWORK_ERROR, $c->kind);
        $this->assertTrue($c->is_transient());
    }

    public function test_network_connection_refused(): void {
        $raw = 'Connection refused';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_NETWORK_ERROR, $c->kind);
    }

    public function test_parse_error_from_manager(): void {
        // The manager prefixes parse failures with "parse_error: ".
        $raw = 'parse_error: missing required key "criterion_scores"';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_PARSE_ERROR, $c->kind);
    }

    public function test_empty_string_falls_back_to_unknown(): void {
        $c = error_classifier::classify('');
        $this->assertSame(error_classifier::KIND_UNKNOWN, $c->kind);
    }

    public function test_null_falls_back_to_unknown(): void {
        $c = error_classifier::classify(null);
        $this->assertSame(error_classifier::KIND_UNKNOWN, $c->kind);
    }

    public function test_unrelated_message_falls_back_to_unknown(): void {
        $c = error_classifier::classify('The LLM said something weird and we did not know what.');
        $this->assertSame(error_classifier::KIND_UNKNOWN, $c->kind);
    }

    /**
     * 413 should win over 429 when both keywords appear, because the corrective
     * action for "Request too large" is different (reduce input vs. wait).
     * Groq actually returns 413 for some per-minute TPM cases.
     */
    public function test_413_takes_precedence_over_429_keywords(): void {
        // Groq's actual message mentions "tokens per minute" but the status is 413.
        $raw = '413: Request too large ... tokens per minute (TPM): Limit 12000';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_PAYLOAD_TOO_LARGE, $c->kind);
    }

    /**
     * The summarizer should drop Groq's billing tail and cap length.
     */
    public function test_summarize_strips_billing_tail(): void {
        $raw = 'Limit reached. Need more tokens? Upgrade to Dev Tier today at https://example.com/billing';
        $summary = error_classifier::summarize_raw($raw);
        $this->assertStringNotContainsString('Upgrade', $summary);
        $this->assertStringNotContainsString('Dev Tier', $summary);
        $this->assertSame('Limit reached.', $summary);
    }

    public function test_summarize_caps_long_messages(): void {
        $raw = str_repeat('a', 500);
        $summary = error_classifier::summarize_raw($raw);
        $this->assertLessThanOrEqual(200, strlen($summary));
        $this->assertStringEndsWith('...', $summary);
    }

    /**
     * The classifier with partial info (no limit/requested numbers) should
     * still classify correctly so the renderer can fall back to body_partial.
     */
    public function test_payload_too_large_without_numbers(): void {
        $raw = 'Request too large: please shorten your input';
        $c = error_classifier::classify($raw);
        $this->assertSame(error_classifier::KIND_PAYLOAD_TOO_LARGE, $c->kind);
        $this->assertArrayNotHasKey('limit', $c->params);
        $this->assertArrayNotHasKey('requested', $c->params);
    }
}
