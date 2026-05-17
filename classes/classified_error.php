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
 * Value object for {@see \local_aigrader\error_classifier::classify()}.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.NamingConventions.ValidVariableName.MemberNameUnderscore
// We intentionally use no-underscore short names here because this is a tiny
// public value object and the fields map 1:1 to template variables.
namespace local_aigrader;

/**
 * Result of classifying an error message.
 */
class classified_error {
    /** @var string One of error_classifier::KIND_* constants. */
    public string $kind;

    /** @var string The original raw error message, unmodified. */
    public string $raw;

    /** @var array<string,mixed> Extracted parameters (limit, requested, model, ...). */
    public array $params;

    /**
     * Constructor.
     *
     * @param string $kind One of error_classifier::KIND_* constants.
     * @param string $raw Original raw error message.
     * @param array $params Extracted parameters (string key => mixed value).
     */
    public function __construct(string $kind, string $raw, array $params) {
        $this->kind   = $kind;
        $this->raw    = $raw;
        $this->params = $params;
    }

    /**
     * i18n key for the short headline shown in the banner title.
     */
    public function headline_string_key(): string {
        return 'err_' . $this->kind . '_headline';
    }

    /**
     * i18n key for the longer body shown in the banner.
     */
    public function body_string_key(): string {
        return 'err_' . $this->kind . '_body';
    }

    /**
     * i18n key for the suggested action shown in the banner.
     */
    public function action_string_key(): string {
        return 'err_' . $this->kind . '_action';
    }

    /**
     * True iff Moodle's adhoc task scheduler will retry this automatically.
     *
     * Rate limits and transient server errors recover on their own; payload
     * and auth errors do not — the teacher has to intervene.
     */
    public function is_transient(): bool {
        return in_array($this->kind, [
            error_classifier::KIND_RATE_LIMITED,
            error_classifier::KIND_PROVIDER_ERROR,
            error_classifier::KIND_NETWORK_ERROR,
        ], true);
    }
}
