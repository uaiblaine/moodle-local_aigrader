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
 * Restore hook for AI Grader Pro: re-create the per-assignment
 * configuration row (local_aigrader_assign) when restoring a mod_assign
 * activity that originally had AI Grader Pro enabled.
 *
 * Paired with backup_local_aigrader_plugin. See that file's docblock for
 * scope (config only, no submission/log data in v1.0.26).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the restore steps for local_aigrader when inside a mod_assign
 * restore task.
 */
class restore_local_aigrader_plugin extends restore_local_plugin {
    /**
     * Tell the restore engine which XML paths inside the assign module we
     * care about. For every matching node it finds, it calls the
     * `process_<element_name>` method with the decoded data.
     *
     * @return restore_path_element[] Paths this plugin contributes.
     */
    protected function define_module_plugin_structure() {
        // Only restore inside assign activities — mirrors the backup hook.
        if ($this->task->get_modulename() !== 'assign') {
            return [];
        }

        $paths = [];
        $paths[] = new restore_path_element(
            'aigrader_config',
            $this->get_pathfor('/aigrader_config')
        );
        return $paths;
    }

    /** @var array Rows stashed at process time, written in after_restore_module(). */
    protected $stashedconfigs = [];

    /**
     * Stash one `<aigrader_config>` element from the backup XML.
     *
     * The module.xml structure step runs BEFORE the activity structure step
     * creates the new assign instance, so at this point
     * $this->task->get_activityid() does not yet reference the new assign.
     * Writing here would bind the row to the wrong (old) instance — the row
     * is therefore stashed and written in after_restore_module(), which the
     * restore plan launches only after every step of the task has executed.
     *
     * @param array $data Decoded XML data for the element.
     */
    public function process_aigrader_config($data) {
        $this->stashedconfigs[] = (array) $data;
    }

    /**
     * Write the stashed configuration once the new assign instance exists,
     * bound to the assignid the restore task created.
     */
    public function after_restore_module() {
        global $DB;

        foreach ($this->stashedconfigs as $stashed) {
            $data = (object) $stashed;

            // The original `assignid` from the source site is meaningless on
            // the destination. Bind to the assign this task restored.
            $data->assignid = (int) $this->task->get_activityid();

            // Re-map the teacher who last edited the config. If the user does
            // not exist on the destination site (cross-site restore without
            // the user data option), get_mappingid returns false; in that
            // case we fall back to the user performing the restore.
            $mappeduser = $this->get_mappingid('user', $data->usermodified ?? 0);
            if ($mappeduser) {
                $data->usermodified = (int) $mappeduser;
            } else {
                global $USER;
                $data->usermodified = (int) $USER->id;
            }

            // Shift timestamps according to the restore's date offset (course
            // start-date moved? these follow).
            $data->timecreated  = $this->apply_date_offset($data->timecreated ?? 0);
            $data->timemodified = $this->apply_date_offset($data->timemodified ?? 0);

            // Drop the source-site `id` so DB autoincrement picks a new one.
            unset($data->id);

            // Defensive guard: if a config already exists for this assignid
            // (possible when a coursemodule callback pre-created a row for
            // the freshly restored assign), update in place instead of insert.
            $existing = $DB->get_record('local_aigrader_assign', ['assignid' => $data->assignid]);
            if ($existing) {
                $data->id = $existing->id;
                $DB->update_record('local_aigrader_assign', $data);
                continue;
            }

            $DB->insert_record('local_aigrader_assign', $data);
        }
        $this->stashedconfigs = [];
    }
}
