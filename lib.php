<?php
/**
 * Global functions for AI Grader Pro.
 *
 * Hooks into mod_assign's edit form via Moodle's coursemodule_* callbacks.
 * Each callback delegates to a static method on assign_form_handler.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_aigrader\form\assign_form_handler;

/**
 * Add AI Grader Pro fields to the assignment edit form.
 *
 * @param moodleform $formwrapper The mod_form being built.
 * @param MoodleQuickForm $mform Quickform reference.
 */
function local_aigrader_coursemodule_standard_elements($formwrapper, $mform): void {
    assign_form_handler::add_elements($formwrapper, $mform);
}

/**
 * Validate AI Grader Pro fields submitted via the assignment edit form.
 *
 * @param moodleform $formwrapper
 * @param array $data Submitted form data.
 * @return array Map of field => error message.
 */
function local_aigrader_coursemodule_validation($formwrapper, $data): array {
    return assign_form_handler::validate($formwrapper, $data);
}

/**
 * Persist AI Grader Pro config after an assignment is saved.
 *
 * @param stdClass $moduleinfo Saved module info (includes instance id).
 * @param stdClass $course
 * @return stdClass
 */
function local_aigrader_coursemodule_edit_post_actions($moduleinfo, $course) {
    return assign_form_handler::save($moduleinfo, $course);
}
