<?php

function pr_approval_is_team_gm(string $role): bool
{
    $normalizedRole = strtolower(trim($role));

    return strpos($normalizedRole, 'gm') === 0
        && !in_array($normalizedRole, ['gmacc', 'gmhok'], true);
}

function pr_approval_subordinate_roles(string $gmRole): array
{
    $roleMap = [
        'gmhr' => ['staff_hr'],
        'gm_sale' => ['sale', 'marketing'],
        'gmshotel' => ['staff_shotel', 'maid_shotel', 'tech_shotel', 'cater_shotel'],
        'gmmanonta' => ['staff_manonta'],
        'gmnijuni' => ['staff_nijuni'],
    ];

    return $roleMap[strtolower(trim($gmRole))] ?? [];
}

function pr_approval_gm_manages_role(string $gmRole, string $requesterRole): bool
{
    return in_array(
        strtolower(trim($requesterRole)),
        pr_approval_subordinate_roles($gmRole),
        true
    );
}

function pr_approval_creator_scope_sql(string $role, int $approverSupId, string $creatorAlias): string
{
    if (!pr_approval_is_team_gm($role)) {
        return '';
    }

    if ($approverSupId <= 0) {
        return ' AND 1=0';
    }

    $subordinateRoles = pr_approval_subordinate_roles($role);
    if ($subordinateRoles === []) {
        return ' AND 1=0';
    }

    $quotedRoles = array_map(
        static fn(string $subordinateRole): string => "'" . $subordinateRole . "'",
        $subordinateRoles
    );

    return ' AND ' . $creatorAlias . '.sup_id = ' . $approverSupId
        . ' AND ' . $creatorAlias . '.role IN (' . implode(',', $quotedRoles) . ')';
}

function pr_approval_can_approve_supervisor_step(
    string $role,
    int $approverSupId,
    int $requesterSupId,
    string $requesterRole
): bool {
    $normalizedRole = strtolower(trim($role));

    if (in_array($normalizedRole, ['admin', 'gmhok'], true)) {
        return true;
    }

    return pr_approval_is_team_gm($normalizedRole)
        && $approverSupId > 0
        && $requesterSupId > 0
        && $approverSupId === $requesterSupId
        && pr_approval_gm_manages_role($normalizedRole, $requesterRole);
}

function pr_approval_can_reject_request(
    string $role,
    int $approverSupId,
    int $requesterSupId,
    string $requesterRole,
    bool $supervisorStepComplete
): bool {
    $normalizedRole = strtolower(trim($role));

    if (in_array($normalizedRole, ['admin', 'gmhok'], true)) {
        return true;
    }

    if (pr_approval_is_team_gm($normalizedRole)) {
        return !$supervisorStepComplete
            && pr_approval_can_approve_supervisor_step(
                $normalizedRole,
                $approverSupId,
                $requesterSupId,
                $requesterRole
            );
    }

    return in_array($normalizedRole, ['mgr', 'mgr2', 'gmacc', 'procure'], true);
}

function pr_approval_supervisor_notification_team_id(int $requesterSupId, int $supplierId): int
{
    return $requesterSupId;
}

function pr_approval_resolve_actor_context(array $session, ?array $databaseUser): array
{
    if ($databaseUser === null) {
        return ['role' => '', 'sup_id' => 0];
    }

    return [
        'role' => (string)($databaseUser['role'] ?? ''),
        'sup_id' => (int)($databaseUser['sup_id'] ?? 0),
    ];
}
