<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Backup hook for AI Grader Pro: include the per-assignment configuration
 * row (local_aigrader_assign) inside `mod_assign` activity backups so the
 * teacher's evaluation criteria and per-assignment overrides survive a
 * course backup → restore cycle.
 *
 * The plugin extends mod_assign's edit form via
 * `local_aigrader_coursemodule_standard_elements` and
 * `local_aigrader_coursemodule_edit_post_actions`. Without a backup hook,
 * the data those callbacks save into local_aigrader_assign is lost the
 * first time a course is duplicated, restored on another site, or rolled
 * over to a new term.
 *
 * Scope of v1.0.26: only the per-assignment configuration is backed up.
 * The student-data tables (local_aigrader_submission and
 * local_aigrader_log) are intentionally NOT included in this revision —
 * adding them is planned for v1.0.27 once we have the assign_submission
 * id-mapping plumbing in place. See TESTPLAN.md scenario 19.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the backup steps for local_aigrader when inside a mod_assign
 * backup task.
 */
class backup_local_aigrader_plugin extends backup_local_plugin {
    /**
     * Inject our XML structure into the assign module element.
     *
     * The Moodle backup engine calls this method once for every module
     * inside the course being backed up. We short-circuit unless the
     * current module is `assign` — local_aigrader only attaches to
     * assignment activities, so nothing of ours lives in (e.g.) quiz or
     * forum backups.
     *
     * @return backup_plugin_element|null Element to attach (or null to skip).
     */
    protected function define_module_plugin_structure() {
        // Only act on mod_assign — every other module type is irrelevant.
        if ($this->connectionpoint->get_element()->get_name() !== 'assign') {
            return null;
        }

        // Root element this plugin contributes to the assign XML node.
        $plugin = $this->get_plugin_element();

        // Wrapper element keeps our nested tree under a clear namespace
        // (avoids collisions with other plugins that may also extend
        // mod_assign backups in the same backup file).
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        // Per-assignment configuration row. We omit `id` and `assignid` from
        // the serialised attribute list — they're re-generated on restore
        // (id auto-increments; assignid is mapped to the new assign).
        $config = new backup_nested_element('aigrader_config', ['id'], [
            'enabled',
            'criteria_text',
            'source',
            'model_override',
            'language_override',
            'usermodified',
            'timecreated',
            'timemodified',
        ]);
        $wrapper->add_child($config);

        // Source: pull the single row whose assignid matches the assign being
        // backed up. backup::VAR_PARENTID is the assign.id at this point in
        // the traversal tree.
        $config->set_source_table(
            'local_aigrader_assign',
            ['assignid' => backup::VAR_PARENTID]
        );

        // The `usermodified` column holds a user.id of the teacher who last
        // edited the criteria. Annotate so the user mapping table picks it
        // up and the restore can re-map it to whatever id that user has on
        // the destination site.
        $config->annotate_ids('user', 'usermodified');

        return $plugin;
    }
}
