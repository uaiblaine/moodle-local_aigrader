<?php
/**
 * English language strings for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Pro';

// Capability descriptions (shown in Site administration > Permissions).
$string['aigrader:use'] = 'Use AI-assisted grading on assignments';
$string['aigrader:configure'] = 'Configure AI Grader Pro on an assignment';
$string['aigrader:viewlog'] = 'View AI Grader Pro audit log';

// Privacy.
$string['privacy:metadata'] = 'AI Grader Pro stores audit logs of AI-assisted grading actions, including prompts sent to the configured LLM provider, model responses, proposed grades and teacher edits. See plugin documentation for full details.';
