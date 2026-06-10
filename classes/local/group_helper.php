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
 * Group-mode resolution and SQL filtering for the AI Grader Pro manage screen.
 *
 * The manage screen lists assignment submissions, so it must obey the
 * activity's group mode exactly the way mod_assign's own grading table does:
 * a teacher in separate-groups mode without moodle/site:accessallgroups may
 * only see — and act on — students who share one of their groups.
 *
 * The whole feature pivots on one core function, groups_get_activity_group(),
 * which validates the requested ?group= against the user's allowed groups and
 * returns the active group id. There is one dangerous edge that core itself
 * flags (see _group_verify_activegroup() in lib/grouplib.php): a separate-
 * groups user with no accessallgroups capability who belongs to NO group gets
 * 0 back — the same value that elsewhere means "all groups". Treating that 0
 * as "all" would leak the entire cohort to a teacher who should see nobody, so
 * resolve() detects this case and flags it as `lockedout`. members_join() then
 * returns a never-matching join and can_access_user() denies every user, which
 * keeps both the listing queries and the write paths safe by construction.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\local;

use cm_info;
use context;
use core\dml\sql_join;
use stdClass;

/**
 * Stateless helper. All public methods are static.
 */
class group_helper {
    /**
     * Resolve the active group context for an assignment's manage screen.
     *
     * @param cm_info $cm Course module (assignment) being managed.
     * @param stdClass $course Course record the module belongs to.
     * @param context $context Module context used for the accessallgroups check.
     * @param bool $update When true, honour and persist a ?group= change in the
     *                     session (use on GET page loads); when false, only read
     *                     the already-active group (use on POST action handlers).
     * @return group_state Snapshot of the resolved group filtering state.
     */
    public static function resolve(cm_info $cm, stdClass $course, context $context, bool $update): group_state {
        $groupmode = (int) groups_get_activity_groupmode($cm, $course);

        // No groups on this activity: everyone is visible, nothing to filter.
        if ($groupmode == NOGROUPS) {
            return new group_state(NOGROUPS, 0, false, false);
        }

        $canaccessall = has_capability('moodle/site:accessallgroups', $context);

        // Returns the validated active group id (0 = all groups). In separate-
        // groups mode it can never resolve to a group the user is not in, and a
        // ?group=0 ("all") request is ignored unless the user can access all.
        $currentgroup = (int) groups_get_activity_group($cm, $update);

        // The leak guard described in the file docblock: separate groups, no
        // accessallgroups, and no group membership all at once => see nobody.
        $lockedout = ($groupmode == SEPARATEGROUPS && !$canaccessall && $currentgroup === 0);

        return new group_state($groupmode, $currentgroup, $canaccessall, $lockedout);
    }

    /**
     * Build the SQL join that restricts a participant listing to the visible
     * group, for splicing into the manage screen's listing queries.
     *
     * Returned fragments are appended to a query that already exposes the user
     * id column named by $useridcolumn (e.g. `s.userid`):
     *
     *   ...FROM {assign_submission} s {$join->joins}
     *      WHERE ... AND {$join->wheres}        // only when wheres is non-empty
     *
     * @param group_state $state Resolved state from {@see self::resolve()}.
     * @param string $useridcolumn Fully-qualified user id column, e.g. `s.userid`.
     * @param context $context Module/course context (required by core for the join).
     * @return sql_join Join, where and params; never-matching when locked out,
     *                  empty (no restriction) when every participant is visible.
     */
    public static function members_join(group_state $state, string $useridcolumn, context $context): sql_join {
        if ($state->lockedout) {
            // Match nobody. cannotmatchanyrows lets callers short-circuit too.
            return new sql_join('', '1 = 0', [], true);
        }

        if ($state->currentgroup > 0) {
            return groups_get_members_join($state->currentgroup, $useridcolumn, $context);
        }

        // All participants visible (NOGROUPS, or "all groups" in visible/aag).
        return new sql_join('', '', [], false);
    }

    /**
     * Whether the current user may see and act on a specific student, applying
     * exactly the same boundary as {@see self::members_join()}.
     *
     * Used to guard the per-row and bulk write paths so a tampered POST cannot
     * trigger grading on a submission outside the teacher's active group.
     *
     * @param group_state $state Resolved state from {@see self::resolve()}.
     * @param int $userid The student's user id.
     * @return bool True if the student is within the visible group boundary.
     */
    public static function can_access_user(group_state $state, int $userid): bool {
        if ($state->lockedout) {
            return false;
        }

        if ($state->currentgroup > 0) {
            return groups_is_member($state->currentgroup, $userid);
        }

        return true;
    }
}
