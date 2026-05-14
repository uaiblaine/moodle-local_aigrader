<?php
/**
 * Privacy provider for AI Grader Pro.
 *
 * In v0.1 alpha (skeleton), no user data is stored yet, so we declare as null_provider.
 * Will switch to a full provider when DB tables (local_aigrader_log, etc.) are added.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Get the language string identifier with the component's reason for storing no personal data.
     *
     * @return string identifier
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
