<?php
/**
 * English language strings for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Pro';

// Capability descriptions (shown in Site administration > Permissions).
$string['aigrader:use'] = 'Use AI-assisted grading on assignments';
$string['aigrader:configure'] = 'Configure AI Grader Pro on an assignment';
$string['aigrader:viewlog'] = 'View AI Grader Pro audit log';

// Admin settings page.
$string['setting_enabled'] = 'Enable plugin';
$string['setting_enabled_desc'] = 'Global on/off switch for AI Grader Pro. When disabled, teachers cannot trigger new AI grading on any assignment. Existing audit logs are preserved.';

$string['setting_rubric_autoimport'] = 'Auto-import from grading rubric';
$string['setting_rubric_autoimport_desc'] = 'When an assignment uses Moodle\'s rubric grading method, automatically pre-populate the AI Grader Pro evaluation criteria with the rubric content. Teachers can still edit the imported criteria before enabling AI grading.';

$string['setting_default_system_prompt'] = 'Default system prompt';
$string['setting_default_system_prompt_desc'] = 'Optional institution-wide instruction prepended to the system prompt of every grading request. Use this to enforce consistent tone or policy across all teachers. Example: "Provide constructive feedback in academic register, maximum 200 words." Leave empty to use only the plugin\'s default system prompt.';

// Privacy.
$string['privacy:metadata'] = 'AI Grader Pro stores audit logs of AI-assisted grading actions, including prompts sent to the configured LLM provider, model responses, proposed grades and teacher edits. See plugin documentation for full details.';
