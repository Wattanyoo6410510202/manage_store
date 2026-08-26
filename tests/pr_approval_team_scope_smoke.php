<?php
$authorizationPath = __DIR__ . '/../pr_approval_authorization.php';
if (is_file($authorizationPath)) {
    require_once $authorizationPath;
}

function assert_pr_approval_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

if (
    !function_exists('pr_approval_is_team_gm')
    || !function_exists('pr_approval_subordinate_roles')
    || !function_exists('pr_approval_gm_manages_role')
    || !function_exists('pr_approval_creator_scope_sql')
    || !function_exists('pr_approval_can_approve_supervisor_step')
    || !function_exists('pr_approval_can_reject_request')
    || !function_exists('pr_approval_supervisor_notification_team_id')
    || !function_exists('pr_approval_resolve_actor_context')
    || !function_exists('pr_approval_can_access_pending_page')
    || !function_exists('pending_approval_resolve_authorized_view')
) {
    fwrite(STDERR, "FAIL: PR approval team authorization is not implemented\n");
    exit(1);
}

assert_pr_approval_same(true, pr_approval_can_access_pending_page('gmshotel'), 'team GMs must access PR approvals');
assert_pr_approval_same(true, pr_approval_can_access_pending_page('procure'), 'procurement must access PR approvals');
assert_pr_approval_same(true, pr_approval_can_access_pending_page('gmacc'), 'GMACC must access PR approvals');
assert_pr_approval_same(false, pr_approval_can_access_pending_page('tech_shotel'), 'assigned technical staff must not inherit PR access');
assert_pr_approval_same(false, pr_approval_can_access_pending_page('viewer'), 'viewer must not receive PR approval access');
assert_pr_approval_same('inspection', pending_approval_resolve_authorized_view('tech_shotel', true, 'pr'), 'inspection-only users opening PR must be routed to inspection work');
assert_pr_approval_same(null, pending_approval_resolve_authorized_view('tech_shotel', false, 'pr'), 'users without either permission must not access pending work');
assert_pr_approval_same('pr', pending_approval_resolve_authorized_view('procure', false, 'inspection'), 'PR-only users opening inspection must be routed to PR approvals');
assert_pr_approval_same('inspection', pending_approval_resolve_authorized_view('gmshotel', true, 'inspection'), 'users with both permissions may open inspection work');

assert_pr_approval_same(true, pr_approval_is_team_gm('gm_sale'), 'department GM roles must be treated as team supervisors');
assert_pr_approval_same(false, pr_approval_is_team_gm('gmacc'), 'GMACC must not be treated as the requester supervisor');
assert_pr_approval_same(false, pr_approval_is_team_gm('gmhok'), 'GMHOK backup access must not be restricted to one team');
assert_pr_approval_same(false, pr_approval_is_team_gm('mgr'), 'non-GM approval roles must not receive the GM team scope');
assert_pr_approval_same(['staff_hr'], pr_approval_subordinate_roles('gmhr'), 'GMHR must manage only HR staff roles');
assert_pr_approval_same(['sale', 'marketing'], pr_approval_subordinate_roles('gm_sale'), 'sales GM must manage sales and marketing roles');
assert_pr_approval_same(['staff_shotel', 'maid_shotel', 'tech_shotel', 'cater_shotel'], pr_approval_subordinate_roles('gmshotel'), 'Shotel GM must manage Shotel staff roles');
assert_pr_approval_same(['staff_manonta'], pr_approval_subordinate_roles('gmmanonta'), 'Manonta GM must manage Manonta staff');
assert_pr_approval_same(['staff_nijuni'], pr_approval_subordinate_roles('gmnijuni'), 'Nijuni GM must manage Nijuni staff');
assert_pr_approval_same([], pr_approval_subordinate_roles('gm'), 'unused generic GM role must not receive an implicit staff mapping');
assert_pr_approval_same(true, pr_approval_gm_manages_role('gm_sale', 'marketing'), 'sales GM must manage marketing users');
assert_pr_approval_same(false, pr_approval_gm_manages_role('gmhr', 'marketing'), 'GMHR must not manage marketing users');

assert_pr_approval_same(
    " AND u_creator.sup_id = 14 AND u_creator.role IN ('sale','marketing')",
    pr_approval_creator_scope_sql('gm_sale', 14, 'u_creator'),
    'GM lists must be scoped by the PR creator team'
);
assert_pr_approval_same(
    ' AND 1=0',
    pr_approval_creator_scope_sql('gm_sale', 0, 'u_creator'),
    'GM users without a team must not see approval requests'
);
assert_pr_approval_same(
    ' AND 1=0',
    pr_approval_creator_scope_sql('gm', 14, 'u_creator'),
    'an unmapped generic GM role must not receive unscoped approval-list access'
);
assert_pr_approval_same(
    '',
    pr_approval_creator_scope_sql('admin', 14, 'u_creator'),
    'non-team-GM roles must keep their existing unscoped list behavior'
);

assert_pr_approval_same(
    true,
    pr_approval_can_approve_supervisor_step('gm_sale', 14, 14, 'marketing'),
    'a GM must approve requests created by users in the same team'
);
assert_pr_approval_same(
    false,
    pr_approval_can_approve_supervisor_step('gm_sale', 14, 22, 'marketing'),
    'a GM must not approve requests created by users in another team'
);
assert_pr_approval_same(
    false,
    pr_approval_can_approve_supervisor_step('gmacc', 14, 14, 'staff_hr'),
    'GMACC must not approve the supervisor step through the GM prefix'
);
assert_pr_approval_same(
    true,
    pr_approval_can_approve_supervisor_step('admin', 0, 22, 'marketing'),
    'administrators must keep their existing backup approval permission'
);
assert_pr_approval_same(
    true,
    pr_approval_can_approve_supervisor_step('gmhok', 0, 22, 'marketing'),
    'GMHOK must keep the existing global backup approval permission'
);
assert_pr_approval_same(
    false,
    pr_approval_can_approve_supervisor_step('gmhr', 14, 14, 'marketing'),
    'matching company alone must not let GMHR approve marketing requests'
);
assert_pr_approval_same(
    true,
    pr_approval_can_reject_request('gmhr', 14, 14, 'staff_hr', false),
    'GMHR must reject an unapproved HR request in the same company'
);
assert_pr_approval_same(
    false,
    pr_approval_can_reject_request('gmhr', 14, 14, 'marketing', false),
    'GMHR must not reject a marketing request in the same company'
);
assert_pr_approval_same(
    false,
    pr_approval_can_reject_request('gmhr', 14, 22, 'staff_hr', false),
    'GMHR must not reject an HR request from another company'
);
assert_pr_approval_same(
    false,
    pr_approval_can_reject_request('gmhr', 14, 14, 'staff_hr', true),
    'a department GM must not reject after the supervisor step is complete'
);
assert_pr_approval_same(
    true,
    pr_approval_can_reject_request('procure', 0, 14, 'staff_hr', true),
    'procurement must keep its downstream rejection permission'
);
assert_pr_approval_same(
    true,
    pr_approval_can_reject_request('gmhok', 0, 14, 'staff_hr', true),
    'GMHOK must keep global backup rejection permission'
);
assert_pr_approval_same(
    14,
    pr_approval_supervisor_notification_team_id(14, 81),
    'supervisor notifications must follow the requester team instead of the selected supplier'
);
assert_pr_approval_same(
    ['role' => 'gm_sale', 'sup_id' => 22],
    pr_approval_resolve_actor_context(
        ['role' => 'gm_old', 'sup_id' => 14],
        ['role' => 'gm_sale', 'sup_id' => 22]
    ),
    'current database team and role must replace stale session values'
);
assert_pr_approval_same(
    ['role' => '', 'sup_id' => 0],
    pr_approval_resolve_actor_context(['role' => 'gm_sale', 'sup_id' => 14], null),
    'a missing database user must not retain stale session authorization'
);

echo "PR approval team scope: PASS\n";
