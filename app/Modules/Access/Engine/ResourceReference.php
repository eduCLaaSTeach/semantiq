<?php

declare(strict_types=1);

namespace App\Modules\Access\Engine;

/**
 * WHICH record is being asked about, described structurally.
 *
 * P1-05 has no business data, so this carries the structural facts a scope is
 * tested against rather than a row: whose record it is, which team it belongs
 * to, which business unit. Phase 2 supplies the per-data-product mapping and
 * must IMPLEMENT this contract rather than reinterpret it - leaving "own"
 * undefined would let Phase 2 invent it, which is a second implementation by
 * another name.
 *
 * D-67: a record is `own` when its subject or assigned user is this identity.
 */
final class ResourceReference
{
    /**
     * @param  list<int>  $teamIds  Every team the record belongs to.
     * @param  list<int>  $businessUnitIds  Every business unit the record belongs to.
     */
    public function __construct(
        public readonly ?int $subjectUserId = null,
        public readonly array $teamIds = [],
        public readonly array $businessUnitIds = [],
        public readonly ?int $organisationId = null,
    ) {}

    public function belongsTo(int $userId): bool
    {
        return $this->subjectUserId === $userId;
    }

    public function isInTeam(int $teamId): bool
    {
        return in_array($teamId, $this->teamIds, true);
    }

    public function isInBusinessUnit(int $businessUnitId): bool
    {
        return in_array($businessUnitId, $this->businessUnitIds, true);
    }
}
