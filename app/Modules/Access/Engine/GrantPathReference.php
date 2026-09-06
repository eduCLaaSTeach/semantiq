<?php

declare(strict_types=1);

namespace App\Modules\Access\Engine;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;

/**
 * WHICH grant authorised an allow. An allow is as explainable as a deny.
 *
 * The four ids are what makes the primary path DETERMINISTIC - §5.6 orders on
 * them ascending. "First" must never mean whatever the database returned first:
 * an unordered first makes a test pass on one machine and fail on another, and
 * makes two identical support questions get two different answers. The chosen
 * path has no effect on the permission outcome.
 */
final class GrantPathReference
{
    public function __construct(
        public readonly int $roleAssignmentId,
        public readonly RoleCode $role,
        public readonly int $domainEntitlementId,
        public readonly int $businessDomainId,
        public readonly int $entitlementScopeId,
        public readonly ScopeType $scopeType,
        public readonly ?int $scopeTargetId,
        public readonly int $entitlementCeilingId,
        public readonly Sensitivity $ceiling,
    ) {}

    /**
     * The deterministic sort key - assignment, entitlement, scope, ceiling, all
     * ascending.
     *
     * @return list<int>
     */
    public function order(): array
    {
        return [
            $this->roleAssignmentId,
            $this->domainEntitlementId,
            $this->entitlementScopeId,
            $this->entitlementCeilingId,
        ];
    }

    /** A sentence naming this path, for the simulator. Business language. */
    public function narrate(string $domainName): string
    {
        $where = match (true) {
            $this->scopeType === ScopeType::Team => 'one assigned team',
            $this->scopeType === ScopeType::BusinessUnit => 'one assigned business unit',
            $this->scopeType === ScopeType::Own => 'their own records',
            default => 'the whole domain',
        };

        return $this->role->label().' in '.$domainName
            .', covering '.$where
            .', up to '.mb_strtolower($this->ceiling->label()).' information.';
    }
}
