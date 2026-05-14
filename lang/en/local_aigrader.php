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

// Assignment edit form (mod_assign).
$string['form_enabled'] = 'Enable AI-assisted grading for this assignment';
$string['form_enabled_help'] = 'When checked, teachers can trigger AI Grader Pro on this assignment\'s submissions. The AI proposes a grade and feedback; the teacher reviews and decides. Nothing is published to the student until the teacher approves.';

$string['form_criteria'] = 'Evaluation criteria';
$string['form_criteria_help'] = 'Plain-language description of how the AI should evaluate submissions to this assignment. Write the same instructions you would give to a teaching assistant. Mention specific criteria, their relative weight, and the tone of feedback you want. Example:

Evaluate this essay (800-1000 words) on digital education using these criteria:
- Thesis clarity (25%): is the position defensible?
- Quality of evidence (30%): are sources academic and well-cited?
- Structure (25%): introduction, development, conclusion
- Language (20%): academic register, spelling

Tone: constructive and specific, in Spanish.';

$string['form_criteria_imported_notice'] = 'Criteria pre-filled from the rubric configured under "Grade > Advanced grading". You may edit them before enabling AI grading.';

$string['form_model_override'] = 'Model override (optional)';
$string['form_model_override_help'] = 'If set, this assignment uses this specific model instead of the default configured in the AI provider. Useful when you want a more capable (or cheaper) model for a particular task. Leave empty to use the global default.';

$string['form_language_override'] = 'Feedback language (optional)';
$string['form_language_override_help'] = 'If set, AI feedback for this assignment will be in this language instead of the course language. Leave on "Auto" to use the course\'s language.';

$string['form_lang_auto'] = 'Auto (use course language)';

// Form errors.
$string['error_criteria_required'] = 'Evaluation criteria are required when AI-assisted grading is enabled. Describe how the AI should evaluate the submissions.';

// Rubric importer.
$string['rubric_export_header'] = 'Criteria (auto-imported from the assignment\'s advanced grading rubric):';

// Privacy.
$string['privacy:metadata'] = 'AI Grader Pro stores audit logs of AI-assisted grading actions, including prompts sent to the configured LLM provider, model responses, proposed grades and teacher edits. See plugin documentation for full details.';
