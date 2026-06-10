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
 * Value object describing the active group context of the manage screen.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\local;

/**
 * Immutable snapshot of the group filtering state for an assignment's manage
 * screen, as resolved by {@see group_helper::resolve()}. Consumers read the
 * fields directly; the two query methods name the exact boundaries that
 * {@see group_helper::members_join()} and {@see group_helper::can_access_user()}
 * enforce so the same rule is not re-spelled at every call site.
 */
class group_state {
    /** @var int Activity group mode: NOGROUPS, SEPARATEGROUPS or VISIBLEGROUPS. */
    public int $groupmode;

    /** @var int Active group id; 0 means "all groups" (no per-group filter). */
    public int $currentgroup;

    /** @var bool Whether the user holds moodle/site:accessallgroups in this context. */
    public bool $canaccessall;

    /**
     * @var bool True when the user is confined to separate groups but belongs
     *           to none. Such a user must see and act on nobody — never treat
     *           their currentgroup of 0 as "all". See group_helper::resolve().
     */
    public bool $lockedout;

    /**
     * Constructor.
     *
     * @param int $groupmode One of NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS.
     * @param int $currentgroup Active group id (0 = all groups).
     * @param bool $canaccessall Whether the user can access all groups.
     * @param bool $lockedout Whether the user is locked out of every group.
     */
    public function __construct(int $groupmode, int $currentgroup, bool $canaccessall, bool $lockedout) {
        $this->groupmode    = $groupmode;
        $this->currentgroup = $currentgroup;
        $this->canaccessall = $canaccessall;
        $this->lockedout    = $lockedout;
    }

    /**
     * Whether the activity uses groups at all (i.e. a group selector applies).
     *
     * @return bool
     */
    public function uses_groups(): bool {
        return $this->groupmode != NOGROUPS;
    }

    /**
     * Whether row visibility is restricted to a subset of participants.
     *
     * True both when locked out (match nobody) and when a specific group is
     * active (match that group); false only when every participant is visible.
     * This is the exact condition under which a members-join filters the
     * listing, so the manage screen uses it to decide whether an empty result
     * means "no submissions for this group" rather than "no submissions".
     *
     * @return bool
     */
    public function is_restricted(): bool {
        return $this->lockedout || $this->currentgroup > 0;
    }
}
