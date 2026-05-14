<?php
/**
 * Sitewide admin settings page for AI Grader Pro.
 *
 * Appears in: Site administration > Plugins > Local plugins > AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'local_aigrader_settings',
        get_string('pluginname', 'local_aigrader')
    );

    // 1. Global on/off switch.
    $settings->add(new admin_setting_configcheckbox(
        'local_aigrader/enabled',
        get_string('setting_enabled', 'local_aigrader'),
        get_string('setting_enabled_desc', 'local_aigrader'),
        1
    ));

    // 2. Auto-import criteria from gradingform_rubric when available.
    $settings->add(new admin_setting_configcheckbox(
        'local_aigrader/rubric_autoimport',
        get_string('setting_rubric_autoimport', 'local_aigrader'),
        get_string('setting_rubric_autoimport_desc', 'local_aigrader'),
        1
    ));

    // 3. Institution-wide system prompt prefix.
    $settings->add(new admin_setting_configtextarea(
        'local_aigrader/default_system_prompt',
        get_string('setting_default_system_prompt', 'local_aigrader'),
        get_string('setting_default_system_prompt_desc', 'local_aigrader'),
        '',
        PARAM_RAW,
        60,
        8
    ));

    $ADMIN->add('localplugins', $settings);
}
