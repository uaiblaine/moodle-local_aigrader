<?php
/**
 * Manual privacy provider validation script.
 * Run from the moodle root: php local/aigrader/cli/validate-privacy.php
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
