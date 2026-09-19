<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Support;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;

/**
 * What the reviewer was actually looking at, and whether it is still that.
 *
 * D-93 is STRICT: a reviewer attested to a composition, so a changed
 * composition is a different fact and the item supersedes rather than applying
 * a decision to access nobody reviewed. Without this, somebody could widen a
 * grant while its review was open and have the review approve the widened
 * version.
 *
 * SCOPES ARE SORTED BY ID BEFORE HASHING. Two equal compositions must not be
 * able to hash differently, which is the same discipline P1-06 used when it
 * asserted byte-identical payloads: an ordering difference is exactly the kind
 * of thing that produces a false "changed" and trains people to ignore it.
 */
final class Composition
{
    /**
     * The live composition of a domain entitlement.
     *
     * @return array<string, mixed>
     */
    public static function ofEntitlement(DomainEntitlement $entitlement): array
    {
        $scopes = $entitlement->scopes()
            ->whereNull('ended_at')
            ->orderBy('id')
            ->get()
            ->map(static fn ($scope): array => [
                'id' => (int) $scope->id,
                'type' => $scope->scope_type instanceof ScopeType ? $scope->scope_type->value : (string) $scope->scope_type,
                'target_id' => $scope->team_id !== null ? (int) $scope->team_id
                    : ($scope->business_unit_id !== null ? (int) $scope->business_unit_id : null),
            ])
            ->all();

        $ceiling = $entitlement->ceilings()->whereNull('ended_at')->orderByDesc('id')->first();

        return [
            'role_code' => $entitlement->assignment?->role_code instanceof RoleCode
                ? $entitlement->assignment->role_code->value
                : (string) $entitlement->assignment?->role_code,
            'business_domain_id' => (int) $entitlement->business_domain_id,
            'scopes' => $scopes,
            'ceiling' => $ceiling === null ? null : [
                'id' => (int) $ceiling->id,
                'sensitivity' => $ceiling->sensitivity instanceof Sensitivity
                    ? $ceiling->sensitivity->value
                    : (string) $ceiling->sensitivity,
            ],
        ];
    }

    /**
     * Platform privilege has no composition beneath it - the role IS the
     * access - so its fingerprint can only change if the role does, which
     * cannot happen within one assignment period.
     *
     * @return array<string, mixed>
     */
    public static function ofPrivilege(string $roleCode): array
    {
        return ['role_code' => $roleCode];
    }

    /** @param array<string, mixed> $composition */
    public static function fingerprint(array $composition): string
    {
        return hash('sha256', (string) json_encode($composition, JSON_THROW_ON_ERROR));
    }
}
