<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applied to every customer-owned model, so tenancy is a property of the model
 * rather than something each query has to remember.
 *
 * Two halves, and both matter:
 *
 *  - Reads are filtered by a global scope, so a forgotten `where` cannot leak
 *    another organisation's rows.
 *  - Writes are stamped on create, so a forgotten assignment cannot produce an
 *    orphan row that the scope then hides from everyone.
 *
 * The scope FAILS CLOSED. With no active organisation, queries match nothing.
 * The alternative, matching everything, turns one missing session into a full
 * cross-tenant read, which the sovereignty standard lists as a release blocker.
 *
 * Requirement IDs: NFR-SEC-02.
 */
trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope(new OrganisationScope);

        /*
         * Stamp on create so callers never have to. A row created inside a
         * withoutScoping block, such as a backfill, keeps whatever the caller
         * set explicitly.
         */
        static::creating(function ($model): void {
            if ($model->organisation_id !== null) {
                return;
            }

            $model->organisation_id = app(OrganisationContext::class)->id();
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}

/**
 * The global scope enforcing the organisation boundary on reads.
 */
class OrganisationScope implements Scope
{
    public function apply(Builder $builder, $model): void
    {
        $context = app(OrganisationContext::class);

        // A deliberate, explicit escape hatch. Never the default.
        if ($context->isScopingDisabled()) {
            return;
        }

        $organisationId = $context->id();

        /*
         * No context means no rows. Written as an impossible predicate rather
         * than as an early return, so the caller still receives a query they
         * can chain on, and so the intent survives in the generated SQL when
         * someone is reading a slow query log wondering why nothing matched.
         */
        if ($organisationId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.organisation_id', $organisationId);
    }
}
