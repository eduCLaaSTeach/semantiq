<?php

declare(strict_types=1);

namespace App\Modules\Identity\Concerns;

use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

/**
 * Scopes a record to the organisation that owns it, and fails closed.
 *
 * Applied to every table carrying `organisation_id`. It does two things a
 * hand-written `where` cannot: it cannot be forgotten on a new query, and it
 * stamps the owner at creation so a row cannot be written unattributed.
 *
 * THE FAIL-CLOSED RULE. When no organisation context is in force, the scope
 * matches NOTHING - not everything. The wrong end of that choice is a screen
 * showing another customer's rows; the right end is an empty screen somebody
 * investigates. CLAUDE.md states the same rule as "cross-organisation access is
 * denied by default", and this is where the default lives.
 *
 * Platform-wide rows - `organisation_id IS NULL` - are visible alongside the
 * current organisation's own, because a platform default is not owned by any
 * customer and hiding it would make the settings screen unreadable on a
 * single-tenant instance. They are only ever DEFAULTS: no customer data is
 * written with a null owner, and `withoutOrganisationScope()` is the only way
 * to see across customers, which is deliberately explicit and never used by a
 * request path.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToOrganisation
{
    /**
     * Laravel calls this once per model, by convention, on boot.
     */
    public static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $id = app(OrganisationContext::class)->currentId();

                if ($id === null) {
                    /*
                     * No context, no rows. Deliberately a contradiction rather
                     * than an omitted clause: an omitted clause would return
                     * every organisation's rows the day a second one exists.
                     */
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder->where(function (Builder $query) use ($model, $id): void {
                    $column = $model->qualifyColumn('organisation_id');

                    $query->where($column, $id)->orWhereNull($column);
                });
            }
        });

        /*
         * Stamp the owner at creation. A row written with no context is refused
         * rather than saved unattributed, because an unattributed row in an
         * audit or configuration table looks like evidence and is not.
         *
         * The one documented exception is a record that can legitimately exist
         * before any organisation is resolved - see organisationIsOptional().
         */
        static::creating(function (Model $model): void {
            if ($model->getAttribute('organisation_id') !== null) {
                return;
            }

            $context = app(OrganisationContext::class);

            /** @var static $model */
            if ($model->organisationIsOptional()) {
                $model->setAttribute('organisation_id', $context->currentId());

                return;
            }

            $model->setAttribute('organisation_id', $context->require()->id);
        });
    }

    /**
     * Whether this record may be written with no organisation at all.
     *
     * False everywhere by default, and it should stay false for anything a
     * customer owns. The exception exists for records that are genuinely
     * created before an organisation can be resolved - a failed sign-in from an
     * unknown address is the case that forced it, and refusing to record that
     * would lose exactly the evidence an incident review needs.
     *
     * A model overriding this must say in its own docblock why, because the
     * override is the boundary of the fail-closed rule.
     */
    public function organisationIsOptional(): bool
    {
        return false;
    }

    /**
     * Read across every organisation.
     *
     * Only for platform operations that are genuinely instance-wide: a
     * migration, a retention sweep, a support diagnostic. Never reachable from
     * a request path, and every call site should say in a comment why crossing
     * the boundary is correct there.
     */
    public static function withoutOrganisationScope(): Builder
    {
        return static::query()->withoutGlobalScopes();
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
