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

namespace local_aigrader\backup;

use advanced_testcase;

/**
 * Backup and restore coverage for the per-assignment configuration row.
 *
 * Regression coverage for two defects fixed in v1.0.28-beta:
 * the module-type guard fataled on every module backup (get_element() called
 * on the connectionpoint string), and the source-table condition used the
 * course-module id instead of the assign instance id, so the config row was
 * silently absent from backups.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_aigrader_plugin
 * @covers     \restore_local_aigrader_plugin
 */
final class duplicate_test extends advanced_testcase {
    /**
     * Duplicating an assign carries the aigrader config to the new instance.
     *
     * duplicate_module() drives a real module-level backup and restore, which
     * exercises both plugin classes end to end.
     *
     * @return void
     */
    public function test_duplicate_assign_carries_config(): void {
        global $DB;
        require_once(__DIR__ . '/../../../../course/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // The coursemodule_edit_post_actions callback auto-creates a config row
        // when the assign is created, so upsert rather than blindly insert.
        $data = (object) [
            'assignid' => (int) $assign->id,
            'enabled' => 1,
            'criteria_text' => 'Clarity of argument and correct citations.',
            'source' => 'manual',
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        if ($existing = $DB->get_record('local_aigrader_assign', ['assignid' => (int) $assign->id])) {
            $data->id = $existing->id;
            $DB->update_record('local_aigrader_assign', $data);
        } else {
            $DB->insert_record('local_aigrader_assign', $data);
        }

        $cm = get_coursemodule_from_instance('assign', $assign->id, $course->id, false, MUST_EXIST);
        $newcm = duplicate_module($course, $cm);

        $copy = $DB->get_record(
            'local_aigrader_assign',
            ['assignid' => (int) $newcm->instance],
            '*',
            MUST_EXIST
        );
        $this->assertSame('Clarity of argument and correct citations.', $copy->criteria_text);
        $this->assertEquals(1, $copy->enabled);
        $this->assertNotEquals((int) $assign->id, (int) $newcm->instance);

        // The original row must be untouched.
        $this->assertCount(2, $DB->get_records('local_aigrader_assign'));
    }

    /**
     * Duplicating a non-assign module neither fatals nor creates config rows.
     *
     * This is the exact path that used to die: the backup engine calls the
     * plugin class for EVERY module type, and the guard must skip quietly.
     *
     * @return void
     */
    public function test_duplicate_forum_skips_plugin(): void {
        global $DB;
        require_once(__DIR__ . '/../../../../course/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $newcm = duplicate_module($course, $cm);

        $this->assertNotEmpty($newcm);
        $this->assertSame(0, $DB->count_records('local_aigrader_assign'));
    }
}
