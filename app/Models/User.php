<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Modules\Identity\Enums\UserType;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Models\BusinessUnit;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Models\Team;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A person who signs in to CLaaS SemantiQ.
 *
 * An account can arrive two ways. Federated accounts are mirrors of a Microsoft
 * Entra directory account: the directory proves who they are and this row exists
 * only so the application has something to attach authorisation and audit to.
 * Their password column stays null, because there is no password to hold.
 *
 * Local accounts carry a hashed password and are the fallback for people the
 * customer's directory does not hold.
 *
 * @property string|null $entra_object_id
 * @property string|null $entra_tenant_id
 * @property Carbon|null $last_signed_in_at
 * @property Role $role
 * @property bool $is_auditor
 * @property LifecycleStatus $status
 * @property UserType $user_type
 * @property string $authentication_source
 * @property int|null $organisation_id
 * @property Carbon|null $access_start
 * @property Carbon|null $access_end
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_signed_in_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_auditor' => 'boolean',
            'status' => LifecycleStatus::class,
            'user_type' => UserType::class,
            'access_start' => 'date',
            'access_end' => 'date',
        ];
    }

    /**
     * Column defaults, mirrored so a freshly created model reports the same
     * values the database would give it.
     *
     * Without this, `User::create([...])->status` is null until the row is
     * reloaded, and a status check on a just-created account silently reads as
     * "not active". The database defaults are the source of truth; these
     * mirror them and a test asserts the two agree.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'user_type' => 'internal',
        'authentication_source' => 'local',
        'is_auditor' => false,
    ];

    /**
     * Limit a query to the organisation currently in force.
     *
     * EXPLICIT, WHERE EVERY OTHER SCOPED MODEL USES A GLOBAL SCOPE, and the
     * difference is deliberate. `users` is the authentication table: Laravel's
     * user provider loads an account by id on every single request, and a
     * global scope that fails closed there would turn "no organisation context
     * resolved" into "nobody in the world can sign in, including the
     * administrator who would fix it". Fail-closed is the right default for
     * reading data and the wrong one for the lookup that decides who you are.
     *
     * So the boundary is enforced at every place that LISTS or ADMINISTERS
     * accounts, which is where cross-customer exposure would actually happen,
     * and it is enforced by calling this. A test asserts one organisation
     * cannot see another's accounts through the registry.
     *
     * Recorded as SEC-DEC-022.
     */
    public function scopeInCurrentOrganisation(Builder $query): Builder
    {
        $id = app(OrganisationContext::class)->currentId();

        if ($id === null) {
            /* No context, no rows - the same fail-closed answer the global
             * scope gives elsewhere. Only the automatic application differs. */
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scoped) use ($id): void {
            $scoped->where('users.organisation_id', $id)
                ->orWhereNull('users.organisation_id');
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** Scope, never permission. */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /** Scope, never permission. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The additional roles this account holds. ADM-005.
     *
     * Additive only within the primary tier's ceiling. Holding a role whose
     * tier is higher than this account's own grants nothing - see
     * `App\Modules\Identity\Support\Authorization`.
     */
    public function accessRoles(): BelongsToMany
    {
        return $this->belongsToMany(AccessRole::class, 'user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Whether this account may authenticate right now.
     *
     * Two independent reasons it might not, and BOTH are checked here so no
     * sign-in path has to remember the second one:
     *
     *  - VAL-USER-DISABLED-001: the status must permit it. Disabled, locked,
     *    expired and invited accounts may not sign in.
     *  - VAL-USER-WINDOW-001: today must be inside the access window. A
     *    contractor's access ending on a date is a promise the system keeps
     *    without anybody remembering to.
     *
     * Deliberately says nothing about WHY. The caller shows one generic
     * message, because a sign-in form that distinguishes "disabled" from
     * "wrong password" is a form that confirms who works here.
     */
    public function mayAuthenticate(): bool
    {
        if (! $this->status->permitsAuthentication()) {
            return false;
        }

        $today = Carbon::today();

        if ($this->access_start !== null && $today->lt($this->access_start)) {
            return false;
        }

        if ($this->access_end !== null && $today->gt($this->access_end)) {
            return false;
        }

        return true;
    }

    /**
     * Whether the access window has closed.
     *
     * Separate from `mayAuthenticate()` because it is the question a SCREEN
     * asks - to show an account as expired - rather than the question a
     * sign-in asks.
     */
    public function accessWindowHasClosed(): bool
    {
        return $this->access_end !== null && Carbon::today()->gt($this->access_end);
    }

    /**
     * The business domains this account may see.
     */
    public function domainEntitlements(): HasMany
    {
        return $this->hasMany(DomainEntitlement::class);
    }

    /**
     * Whether this account may see a business domain.
     *
     * Deliberately independent of the tier. ROLE_MODEL.md section 1 is explicit
     * that a role alone never grants business data, and section 4 spells out the
     * consequence: a Platform Administrator holds no domains by default and
     * sees only the technical data needed to operate the platform. Letting the
     * top tier imply every domain would quietly undo that.
     */
    public function isEntitledTo(BusinessDomain $domain): bool
    {
        return $this->domainEntitlements()
            ->where('domain', $domain->value)
            ->exists();
    }

    /**
     * Every domain this account may see, in the enum's own order so the
     * interface does not reorder itself as entitlements are granted.
     *
     * @return list<BusinessDomain>
     */
    public function entitledDomains(): array
    {
        /*
         * pluck() runs the model's casts, so this comes back as BusinessDomain
         * instances rather than strings. Comparing them against ->value with a
         * strict check silently matched nothing, and the symptom was a domain
         * card grid that stayed empty for somebody who was properly entitled.
         */
        $held = $this->domainEntitlements()
            ->pluck('domain')
            ->map(fn (BusinessDomain|string $domain): string => $domain instanceof BusinessDomain ? $domain->value : $domain)
            ->all();

        return array_values(array_filter(
            BusinessDomain::cases(),
            fn (BusinessDomain $domain): bool => in_array($domain->value, $held, true),
        ));
    }

    /**
     * Initials for the avatar.
     *
     * First and last word, so "Salil Mhatre" reads SM rather than SMH for a
     * middle name. A one-word name still yields one letter rather than nothing,
     * and an empty name falls back to the address, because an avatar with no
     * character in it looks like a rendering fault.
     */
    public function initials(): string
    {
        $source = trim($this->name) !== '' ? trim($this->name) : $this->email;
        $words = preg_split('/\s+/', $source) ?: [];
        $words = array_values(array_filter($words));

        if ($words === []) {
            return '?';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr((string) end($words), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * Whether this account holds at least the authority of the given tier.
     *
     * Tiers are cumulative, so a System Administrator satisfies every check an
     * Administrator satisfies.
     */
    public function hasAtLeast(Role $minimum): bool
    {
        return $this->role->atLeast($minimum);
    }

    /**
     * Whether this account's identity is proved by the directory rather than by
     * a password held here.
     */
    public function isFederated(): bool
    {
        return $this->entra_object_id !== null;
    }
}
