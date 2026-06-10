<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for the group-mode resolution and SQL filtering helper.
 *
 * These pin down the visibility contract the manage screen relies on, with
 * particular attention to the one case that would otherwise leak the whole
 * cohort: a teacher confined to separate groups, without
 * moodle/site:accessallgroups, who belongs to no group. Core's
 * groups_get_activity_group() returns 0 ("all groups") for that user, and
 * group_helper must instead treat it as "see nobody" (lockedout).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for \local_aigrader\local\group_helper.
 *
 * @covers \local_aigrader\local\group_helper
 * @covers \local_aigrader\local\group_state
 */
final class group_helper_test extends \advanced_testcase {
    /**
     * Reset the database and session after every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Build a course with a group-aware assignment, two groups (A, B), a
     * teacher and two students (A in group A, B in group B). The teacher is
     * deliberately left out of every group; tests add membership when needed.
     *
     * @param int $groupmode One of NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS.
     * @return array Associative array of the created fixtures.
     */
    private function make_world(int $groupmode): array {
        $gen = $this->getDataGenerator();

        $course = $gen->create_course();
        $module = $gen->create_module('assign', [
            'course'    => $course->id,
            'groupmode' => $groupmode,
        ]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        $context = \context_module::instance($cm->id);

        $groupa = $gen->create_group(['courseid' => $course->id]);
        $groupb = $gen->create_group(['courseid' => $course->id]);

        $teacher  = $gen->create_and_enrol($course, 'editingteacher');
        $studenta = $gen->create_and_enrol($course, 'student');
        $studentb = $gen->create_and_enrol($course, 'student');

        $gen->create_group_member(['groupid' => $groupa->id, 'userid' => $studenta->id]);
        $gen->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);

        return [
            'course'   => $course,
            'cm'       => $cm,
            'context'  => $context,
            'groupa'   => $groupa,
            'groupb'   => $groupb,
            'teacher'  => $teacher,
            'studenta' => $studenta,
            'studentb' => $studentb,
        ];
    }

    /**
     * Remove moodle/site:accessallgroups from editingteacher in this course so
     * separate-groups mode actually restricts (the archetype grants it by
     * default). Without this, every teacher would short-circuit to "all groups".
     *
     * @param \stdClass $course Course whose context the override is applied to.
     */
    private function deny_accessallgroups(\stdClass $course): void {
        global $DB;
        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        assign_capability(
            'moodle/site:accessallgroups',
            CAP_PREVENT,
            $role->id,
            \context_course::instance($course->id)->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * No group mode: everyone is visible and nothing is filtered.
     */
    public function test_nogroups_sees_everyone(): void {
        $w = $this->make_world(NOGROUPS);
        $this->setUser($w['teacher']);

        $state = group_helper::resolve($w['cm'], $w['course'], $w['context'], true);

        $this->assertSame(NOGROUPS, $state->groupmode);
        $this->assertSame(0, $state->currentgroup);
        $this->assertFalse($state->lockedout);
        $this->assertFalse($state->uses_groups());
        $this->assertFalse($state->is_restricted());

        $join = group_helper::members_join($state, 'u.id', $w['context']);
        $this->assertSame('', $join->joins);
        $this->assertSame('', $join->wheres);
        $this->assertFalse($join->cannotmatchanyrows);

        $this->assertTrue(group_helper::can_access_user($state, (int) $w['studentb']->id));
    }

    /**
     * Separate groups, teacher is a member of group A: confined to group A.
     */
    public function test_separate_groups_member_is_restricted_to_their_group(): void {
        $w = $this->make_world(SEPARATEGROUPS);
        $this->deny_accessallgroups($w['course']);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $w['groupa']->id,
            'userid'  => $w['teacher']->id,
        ]);
        $this->setUser($w['teacher']);

        $state = group_helper::resolve($w['cm'], $w['course'], $w['context'], true);

        $this->assertSame(SEPARATEGROUPS, $state->groupmode);
        $this->assertSame((int) $w['groupa']->id, $state->currentgroup);
        $this->assertFalse($state->canaccessall);
        $this->assertFalse($state->lockedout);
        $this->assertTrue($state->is_restricted());

        $this->assertTrue(group_helper::can_access_user($state, (int) $w['studenta']->id));
        $this->assertFalse(group_helper::can_access_user($state, (int) $w['studentb']->id));
    }

    /**
     * THE security test: separate groups, no accessallgroups, no membership.
     * Such a teacher must see and act on nobody, never the whole cohort.
     */
    public function test_separate_groups_without_membership_is_locked_out(): void {
        $w = $this->make_world(SEPARATEGROUPS);
        $this->deny_accessallgroups($w['course']);
        // Teacher is intentionally not added to any group.
        $this->setUser($w['teacher']);

        $state = group_helper::resolve($w['cm'], $w['course'], $w['context'], true);

        $this->assertTrue($state->lockedout);
        $this->assertSame(0, $state->currentgroup);
        $this->assertFalse($state->canaccessall);
        $this->assertTrue($state->is_restricted());

        // The members-join must match no rows at all.
        $join = group_helper::members_join($state, 'u.id', $w['context']);
        $this->assertTrue($join->cannotmatchanyrows);
        $this->assertSame('1 = 0', $join->wheres);

        // And the per-row guard must deny even a really-enrolled student.
        $this->assertFalse(group_helper::can_access_user($state, (int) $w['studenta']->id));
        $this->assertFalse(group_helper::can_access_user($state, (int) $w['studentb']->id));
    }

    /**
     * Separate groups but the user holds accessallgroups (e.g. an admin or
     * manager): sees the whole cohort, never locked out.
     */
    public function test_separate_groups_with_accessallgroups_sees_everyone(): void {
        $w = $this->make_world(SEPARATEGROUPS);
        $this->setAdminUser();

        $state = group_helper::resolve($w['cm'], $w['course'], $w['context'], true);

        $this->assertSame(SEPARATEGROUPS, $state->groupmode);
        $this->assertTrue($state->canaccessall);
        $this->assertFalse($state->lockedout);
        $this->assertSame(0, $state->currentgroup);

        $join = group_helper::members_join($state, 'u.id', $w['context']);
        $this->assertSame('', $join->wheres);
        $this->assertFalse($join->cannotmatchanyrows);

        $this->assertTrue(group_helper::can_access_user($state, (int) $w['studentb']->id));
    }

    /**
     * Visible groups, teacher with no membership: filtered to a default group
     * but never locked out — visible mode always lets a teacher see students.
     */
    public function test_visible_groups_without_membership_is_not_locked_out(): void {
        $w = $this->make_world(VISIBLEGROUPS);
        $this->deny_accessallgroups($w['course']);
        $this->setUser($w['teacher']);

        $state = group_helper::resolve($w['cm'], $w['course'], $w['context'], true);

        $this->assertSame(VISIBLEGROUPS, $state->groupmode);
        $this->assertFalse($state->lockedout);
        $this->assertTrue($state->uses_groups());
    }

    /**
     * Prove the members-join actually filters a real query down to the active
     * group's members and nobody else.
     */
    public function test_members_join_filters_query_to_active_group(): void {
        global $DB;
        $w = $this->make_world(SEPARATEGROUPS);

        $state = new group_state(SEPARATEGROUPS, (int) $w['groupa']->id, false, false);
        $join = group_helper::members_join($state, 'u.id', $w['context']);

        $candidates = [(int) $w['studenta']->id, (int) $w['studentb']->id];
        [$insql, $inparams] = $DB->get_in_or_equal($candidates, SQL_PARAMS_NAMED, 'cand');
        $where = $join->wheres !== '' ? " AND ({$join->wheres})" : '';

        $sql = "SELECT u.id
                  FROM {user} u
                       {$join->joins}
                 WHERE u.id $insql $where";
        $got = $DB->get_fieldset_sql($sql, array_merge($inparams, $join->params));

        $this->assertEqualsCanonicalizing(
            [(int) $w['studenta']->id],
            array_map('intval', $got)
        );
    }

    /**
     * can_access_user() applies exactly the same boundary as members_join()
     * across the three meaningful states, independent of session resolution.
     */
    public function test_can_access_user_matrix(): void {
        $w = $this->make_world(SEPARATEGROUPS);

        // Locked out denies everyone, even a genuine group member.
        $locked = new group_state(SEPARATEGROUPS, 0, false, true);
        $this->assertFalse(group_helper::can_access_user($locked, (int) $w['studenta']->id));

        // A specific active group admits its members and rejects outsiders.
        $ina = new group_state(SEPARATEGROUPS, (int) $w['groupa']->id, false, false);
        $this->assertTrue(group_helper::can_access_user($ina, (int) $w['studenta']->id));
        $this->assertFalse(group_helper::can_access_user($ina, (int) $w['studentb']->id));

        // "All groups" (0) without lockout admits anyone.
        $all = new group_state(VISIBLEGROUPS, 0, false, false);
        $this->assertTrue(group_helper::can_access_user($all, (int) $w['studentb']->id));
    }
}
