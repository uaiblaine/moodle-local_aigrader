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
 * Manual privacy provider validation script.
 * Run from the moodle root: php local/aigrader/cli/validate-privacy.php
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

echo "=== get_metadata() ===\n";
$collection = new \core_privacy\local\metadata\collection('local_aigrader');
$result = \local_aigrader\privacy\provider::get_metadata($collection);
$items = $result->get_collection();
echo "Items declared: " . count($items) . "\n";
foreach ($items as $item) {
    echo "  - " . $item->get_name() . " (" . get_class($item) . ")\n";
}

echo "\n=== get_contexts_for_userid(2) ===\n";
$contextlist = \local_aigrader\privacy\provider::get_contexts_for_userid(2);
$contexts = $contextlist->get_contextids();
echo "Contexts where userid=2 has data: " . count($contexts) . "\n";
foreach ($contexts as $ctxid) {
    $ctx = context::instance_by_id($ctxid);
    echo "  - id=$ctxid (" . $ctx->get_context_name() . ")\n";
}

echo "\n=== get_users_in_context() for first context ===\n";
if (!empty($contexts)) {
    $ctx = context::instance_by_id($contexts[0]);
    $userlist = new \core_privacy\local\request\userlist($ctx, 'local_aigrader');
    \local_aigrader\privacy\provider::get_users_in_context($userlist);
    $users = $userlist->get_userids();
    echo "Users with data in context " . $contexts[0] . ": " . count($users) . "\n";
    foreach ($users as $uid) {
        echo "  - userid=$uid\n";
    }
}

echo "\n=== Validation OK. Provider is callable end-to-end. ===\n";
