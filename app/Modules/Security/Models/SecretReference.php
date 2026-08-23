<?php

declare(strict_types=1);

namespace App\Modules\Security\Models;

use App\Models\User;
use App\Modules\Audit\Support\Redaction;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use App\Modules\Security\Enums\SecretProvider;
use App\Modules\Security\Enums\SecretStatus;
use App\Modules\Security\Enums\SecretType;
use App\Modules\Security\Exceptions\CredentialShapedValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pointer to a credential held somewhere else. Feature ADM-012.
 *
 * NOTHING ON THIS MODEL HOLDS A SECRET, and the model enforces that rather than
 * asserting it. `saving` refuses any value that `Redaction::scrub()` would
 * alter: if scrubbing changes the string, it looked like a credential to the
 * same code that protects the audit trail, and it does not go in the database.
 *
 * That check is here, at the model, and not only in the form request. A console
 * command, a seeder, a queued job and a future API all reach the model without
 * passing a request, and "we validated it at the boundary" is only true of the
 * boundaries somebody remembered.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property SecretType $reference_type
 * @property SecretProvider $provider
 * @property string $reference_identifier
 * @property string $purpose
 * @property string $environment
 * @property int|null $owner_user_id
 * @property Carbon|null $expires_on
 * @property Carbon|null $rotation_due_on
 * @property Carbon|null $retired_at
 */
class SecretReference extends Model
{
    use BelongsToOrganisation;

    /**
     * The free-text columns a credential could be pasted into.
     *
     * `name` and `purpose` are on the list as well as the obvious one. Somebody
     * pasting a client secret into a field will paste it into whichever field
     * they happen to be looking at, and a leaked secret in a "purpose" column
     * has leaked just as thoroughly.
     *
     * @var list<string>
     */
    private const CREDENTIAL_RISK_COLUMNS = [
        'name',
        'reference_identifier',
        'purpose',
        'environment',
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'reference_type',
        'provider',
        'reference_identifier',
        'purpose',
        'environment',
        'owner_user_id',
        'expires_on',
        'rotation_due_on',
        'retired_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reference_type' => SecretType::class,
            'provider' => SecretProvider::class,
            'expires_on' => 'date',
            'rotation_due_on' => 'date',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * The last line of defence against a credential reaching this table.
     */
    protected static function booted(): void
    {
        static::saving(function (self $reference): void {
            foreach (self::CREDENTIAL_RISK_COLUMNS as $column) {
                $value = $reference->getAttribute($column);

                if (! is_string($value) || $value === '') {
                    continue;
                }

                /*
                 * `scrub()` replaces anything credential-shaped - a bearer
                 * token, a JWT, an inline `secret=...`, a connection string
                 * with a password, a long opaque blob. If it changed the
                 * string, the string looked like a credential.
                 *
                 * Deliberately reusing the audit trail's own detector rather
                 * than writing a second one. Two detectors drift, and the day
                 * they disagree is the day one of them is wrong.
                 */
                if (Redaction::scrub($value) !== $value) {
                    throw CredentialShapedValue::in($column);
                }
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The lifecycle state, derived from the dates rather than stored.
     *
     * See `SecretStatus::derive()` for why a stored status would go stale.
     */
    public function status(): SecretStatus
    {
        return SecretStatus::derive(
            $this->expires_on,
            $this->rotation_due_on,
            $this->retired_at,
        );
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    /** References still in use, newest concern first. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }

    /**
     * References that expire within the horizon, or already have.
     *
     * Retired ones are excluded: an expired credential nobody uses any more is
     * not a finding, and listing it trains people to ignore the list.
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNull('retired_at')
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', Carbon::today()->addDays($days));
    }

    /** References whose rotation date has arrived. */
    public function scopeRotationDue(Builder $query): Builder
    {
        return $query
            ->whereNull('retired_at')
            ->whereNotNull('rotation_due_on')
            ->whereDate('rotation_due_on', '<=', Carbon::today());
    }

    /**
     * References with no expiry recorded at all.
     *
     * Its own scope because it is its own problem: a credential nobody gave an
     * expiry to is a credential nobody is tracking, and the overview reports it
     * as Not Verified rather than as healthy. Rule 9.
     */
    public function scopeUntracked(Builder $query): Builder
    {
        return $query
            ->whereNull('retired_at')
            ->whereNull('expires_on')
            ->whereNull('rotation_due_on');
    }
}
