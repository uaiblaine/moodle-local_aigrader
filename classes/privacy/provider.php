<?php
/**
 * Privacy provider for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * IMPORTANT: this provider is currently declared as null_provider because
 * although the DB tables (local_aigrader_assign, local_aigrader_submission,
 * local_aigrader_log) exist from v0.1.2, the plugin does not yet contain
 * any code that inserts rows into them. As soon as the grading manager
 * starts writing data, this class MUST be upgraded to implement:
 *
 *   - \core_privacy\local\metadata\provider           (declare data schema)
 *   - \core_privacy\local\request\plugin\provider     (export + delete)
 *
 * Tracked as a TODO before the first feature that writes to DB.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
