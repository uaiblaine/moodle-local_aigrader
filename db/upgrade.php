<?php
/**
 * Upgrade steps for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion Previous version of the plugin currently installed.
 * @return bool
 */
function xmldb_local_aigrader_upgrade($oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // v0.1.2 — introduce the initial DB schema (3 tables).
    if ($oldversion < 2026051403) {
        $tables = [
            'local_aigrader_assign',
            'local_aigrader_submission',
            'local_aigrader_log',
        ];
        foreach ($tables as $tablename) {
            if (!$dbman->table_exists($tablename)) {
                $dbman->install_one_table_from_xmldb_file(
                    __DIR__ . '/install.xml',
                    $tablename
                );
            }
        }
        upgrade_plugin_savepoint(true, 2026051403, 'local', 'aigrader');
    }

    return true;
}
