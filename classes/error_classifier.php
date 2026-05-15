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
 * Maps raw provider error strings to actionable error classifications.
 *
 * Errors stored in {local_aigrader_submission}.error_message and in
 * {local_aigrader_log}.response_json are raw — typically a status code plus
 * the provider's English error body, sometimes with billing URLs embedded.
 * That's useful for debugging but unhelpful (and ugly) in the teacher UI.
 *
 * This classifier inspects the raw string, picks the matching category,
 * and returns a structured result with i18n keys + extracted parameters
 * the renderer can use to build a friendly localised message and suggest
 * a concrete next step.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

/**
 * Stateless classifier. All public methods are static.
 */
class error_classifier {

    /** Submission exceeded the model's context / TPM limit (HTTP 413, "Request too large"). */
    public const KIND_PAYLOAD_TOO_LARGE = 'payload_too_large';

    /** Authentication failure — invalid or revoked API key (HTTP 401, 403). */
    public const KIND_UNAUTHORIZED = 'unauthorized';

    /** Provider rate limit hit (HTTP 429). Will retry automatically. */
    public const KIND_RATE_LIMITED = 'rate_limited';

    /** Provider returned 5xx — transient server error. Will retry automatically. */
    public const KIND_PROVIDER_ERROR = 'provider_error';

    /** Could not reach the provider (DNS failure, connection refused, timeout). */
    public const KIND_NETWORK_ERROR = 'network_error';

    /** LLM responded but we could not parse the JSON we asked for. */
    public const KIND_PARSE_ERROR = 'parse_error';

    /** Anything we did not recognise. The renderer falls back to the raw message. */
    public const KIND_UNKNOWN = 'unknown';

    /**
     * Classify a raw error string into a structured result.
     *
     * @param string|null $raw Raw error message as persisted by manager / task.
     * @return classified_error Structured result. Always returns an object,
     *         never null — KIND_UNKNOWN is the catch-all.
     */
    public static function classify(?string $raw): classified_error {
        $raw = (string) $raw;
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return new classified_error(self::KIND_UNKNOWN, $raw, []);
        }

        // --- Payload / token-limit errors ---------------------------------.
        // Examples we have seen:
        //   "413: Request too large for model `llama-3.3-70b-versatile`
        //    in organization `org_XXX` service tier `on_demand` on tokens
        //    per minute (TPM): Limit 12000, Requested 14003, please ..."
        //   "Request too large: context length exceeded"
        //   "context_length_exceeded"
        if (self::matches_any($trimmed, [
            '/\b413\b/',
            '/request\s+too\s+large/i',
            '/context[_ ]length[_ ]exceeded/i',
            '/maximum\s+context\s+length/i',
            '/payload\s+too\s+large/i',
            '/tokens\s+per\s+minute/i',
        ])) {
            $params = self::extract_token_limit_info($trimmed);
            return new classified_error(self::KIND_PAYLOAD_TOO_LARGE, $raw, $params);
        }

        // --- Authentication / authorisation ------------------------------.
        if (self::matches_any($trimmed, [
            '/\b401\b/',
            '/\b403\b/',
            '/unauthori[sz]ed/i',
            '/invalid[_ ]api[_ ]key/i',
            '/api\s+key.*(invalid|expired|missing|revoked)/i',
            '/authentication.*fail/i',
        ])) {
            return new classified_error(self::KIND_UNAUTHORIZED, $raw, []);
        }

        // --- Rate limiting (429) -----------------------------------------.
        // Note: some providers also return 413 with "TPM" in the body when the
        // request itself exceeds the per-minute budget; that's already caught
        // above as PAYLOAD_TOO_LARGE because the corrective action is the
        // same (reduce size or switch model). Here we only handle pure 429.
        if (self::matches_any($trimmed, [
            '/\b429\b/',
            '/rate[_ ]limit/i',
            '/too\s+many\s+requests/i',
        ])) {
            return new classified_error(self::KIND_RATE_LIMITED, $raw, []);
        }

        // --- 5xx provider errors -----------------------------------------.
        if (self::matches_any($trimmed, [
            '/\b50[0-9]\b/',
            '/\b51[0-9]\b/',
            '/internal\s+server\s+error/i',
            '/service\s+unavailable/i',
            '/bad\s+gateway/i',
            '/gateway\s+timeout/i',
        ])) {
            return new classified_error(self::KIND_PROVIDER_ERROR, $raw, []);
        }

        // --- Network / transport errors ----------------------------------.
        if (self::matches_any($trimmed, [
            '/connection\s+(refused|reset|timed?\s*out)/i',
            '/curl\s+error/i',
            '/could\s+not\s+resolve/i',
            '/network\s+(error|failure|unreachable)/i',
            '/no\s+route\s+to\s+host/i',
            '/timeout/i',
        ])) {
            return new classified_error(self::KIND_NETWORK_ERROR, $raw, []);
        }

        // --- Parser failures ---------------------------------------------.
        // Persisted by manager as "parse_error: <details>".
        if (stripos($trimmed, 'parse_error') !== false
            || stripos($trimmed, 'malformed json') !== false
            || stripos($trimmed, 'invalid json') !== false) {
            return new classified_error(self::KIND_PARSE_ERROR, $raw, []);
        }

        return new classified_error(self::KIND_UNKNOWN, $raw, []);
    }

    /**
     * Try to extract "Limit X, Requested Y" and the model name from a
     * payload-too-large error so the UI can show the actual numbers.
     *
     * @param string $raw
     * @return array<string,mixed> Keys present iff matched: limit, requested, model.
     */
    private static function extract_token_limit_info(string $raw): array {
        $params = [];
        if (preg_match('/limit\s+(\d{3,7})/i', $raw, $m)) {
            $params['limit'] = (int) $m[1];
        }
        if (preg_match('/requested\s+(\d{3,7})/i', $raw, $m)) {
            $params['requested'] = (int) $m[1];
        }
        if (preg_match('/model\s+`?([\w\-.\/:]+)`?/i', $raw, $m)) {
            $params['model'] = $m[1];
        }
        return $params;
    }

    /**
     * Return true if any of the given regexes matches the haystack.
     *
     * @param string $haystack
     * @param string[] $patterns
     * @return bool
     */
    private static function matches_any(string $haystack, array $patterns): bool {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Trim a long raw error for display in compact contexts (badge tooltip,
     * per-row table cell). Drops Groq's billing URL marketing tail and caps
     * at 200 chars.
     */
    public static function summarize_raw(string $raw): string {
        // Drop any tail like "...Need more tokens? Upgrade to Dev Tier at https://...".
        $raw = preg_replace('/\s*(need\s+more\s+tokens|upgrade\s+to.*tier).*/i', '', $raw);
        $raw = trim((string) $raw);
        if (strlen($raw) <= 200) {
            return $raw;
        }
        return substr($raw, 0, 197) . '...';
    }
}
