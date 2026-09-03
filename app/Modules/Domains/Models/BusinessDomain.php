<?php

declare(strict_types=1);

namespace App\Modules\Domains\Models;

use App\Modules\Organisation\Models\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A business intelligence domain: a name for part of the organisation's
 * intelligence estate, and a person accountable for it.
 *
 * IT GRANTS NOTHING. Not by existing, not by being enabled, and not to its
 * owner. There is no role here, no entitlement, no scope, no sensitivity and no
 * method that could be read as one - P1-05 owns every one of those, and this
 * model must not anticipate that decision.
 *
 * TWO COLUMNS ARE DELIBERATELY ABSENT and their absence is asserted:
 *
 *   owner_user_id - the ownership-history table is the single source of truth
 *   for who owns this now (DESIGN §6.1). A column beside it would be a second
 *   writable record of one fact, able to disagree, with nothing in the schema
 *   to say which is right.
 *
 *   sensitivity_expectation - D-47 defers the whole sensitivity dimension to
 *   P1-05. Not the ceiling, not an inert statement, not the vocabulary.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property DomainKind $kind
 * @property DomainStatus $status
 * @property AccessExpectation $access_expectation
 */
final class BusinessDomain extends Model
{
    /**
     * `code` and `kind` are NOT fillable, on purpose.
     *
     * They are the domain's identity and its origin. Both are written once with
     * forceFill at creation and there is no path - route, request field or
     * service parameter - that can change either afterwards. An extra `code` in
     * a PUT has nowhere to go; it is not sanitised out, it simply is not
     * accepted.
     */
    protected $fillable = ['name', 'description', 'access_expectation'];

    protected function casts(): array
    {
        return [
            'kind' => DomainKind::class,
            'status' => DomainStatus::class,
            'access_expectation' => AccessExpectation::class,
        ];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<DomainOwnership, $this> */
    public function ownerships(): HasMany
    {
        return $this->hasMany(DomainOwnership::class);
    }

    /**
     * The current ownership period, if there is one.
     *
     * THE OPEN ROW IS THE ONLY DEFINITION OF "current owner" in the system.
     * Nothing caches it, nothing duplicates it, and no code answers this
     * question from anywhere else.
     *
     * @return HasOne<DomainOwnership, $this>
     */
    public function currentOwnership(): HasOne
    {
        return $this->hasOne(DomainOwnership::class)->whereNull('ended_at');
    }

    public function isEnabled(): bool
    {
        return $this->status->isEnabled();
    }

    public function isBaseline(): bool
    {
        return $this->kind->isBaseline();
    }
}
