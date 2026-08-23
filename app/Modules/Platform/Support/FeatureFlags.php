<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Models\FeatureFlag;
use InvalidArgumentException;

/**
 * Reads and toggles feature flags. Feature ADM-021.
 *
 * A flag says whether a capability is AVAILABLE. It never says who may use it -
 * that is the tier, the permission and the domain entitlement, and none of them
 * consult this class. Nothing here may be used to grant access, and a reviewer
 * should treat any code that reads a flag to decide authorisation as a defect.
 *
 * AN UNKNOWN FLAG IS OFF. `enabled()` returns false for a key with no
 * declaration rather than throwing, because a flag is read on hot paths where a
 * removed declaration should degrade to "capability unavailable" rather than to
 * a 500. Writing an undeclared flag still throws: reading a missing switch is a
 * degraded state, creating one is a mistake.
 *
 * SOME SWITCHES ARE SAFE IN ONE DIRECTION ONLY. The catalogue's `requires` key
 * names a precondition, checked here. The case that forced it: turning off
 * local password sign-in on an instance with no working Microsoft sign-in locks
 * every administrator out of their own platform.
 */
class FeatureFlags
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(
        private readonly OrganisationContext $organisations,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Whether a capability is switched on.
     */
    public function enabled(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        /* Indexed out of the whole array: flag keys contain dots and
         * `config()` reads a dot as nesting. See SystemSettings::definition(). */
        $definition = $this->declaration($key);

        if ($definition === null) {
            /* Undeclared means off. See the class docblock. */
            return $this->cache[$key] = false;
        }

        $stored = FeatureFlag::query()
            ->where('key', $key)
            ->orderByDesc('organisation_id')
            ->value('enabled');

        return $this->cache[$key] = $stored === null
            ? (bool) ($definition['default'] ?? false)
            : (bool) $stored;
    }

    /**
     * Every declared flag with its current state, in catalogue order.
     *
     * @return array<string, array{definition: array<string, mixed>, enabled: bool}>
     */
    public function all(): array
    {
        $flags = [];

        foreach ((array) config('platform.flags', []) as $key => $definition) {
            $flags[$key] = [
                'definition' => $definition,
                'enabled' => $this->enabled($key),
            ];
        }

        return $flags;
    }

    /**
     * Turn a capability on or off.
     *
     * Returns true when the state actually changed.
     *
     * @throws InvalidArgumentException when the flag is unknown, the actor lacks
     *                                  the tier, or a precondition fails.
     */
    public function set(string $key, bool $enabled, User $actor, ?string $reason = null): bool
    {
        $definition = $this->declaration($key);

        if ($definition === null) {
            throw new InvalidArgumentException(
                'Unknown feature flag "'.$key.'". Flags must be declared in config/platform.php.'
            );
        }

        $required = $definition['editable'] ?? Role::SystemAdmin;

        if (! $required instanceof Role || ! $actor->hasAtLeast($required)) {
            $this->audit->denied(
                action: 'feature_flag.toggled',
                module: 'Platform',
                resourceType: 'feature_flag',
                resourceId: $key,
                reason: 'Actor does not hold the tier this flag requires.',
            );

            throw new InvalidArgumentException('You do not have authority to change "'.$key.'".');
        }

        $before = $this->enabled($key);

        if ($before === $enabled) {
            return false;
        }

        $this->assertPreconditionHolds($key, $definition, $enabled);

        /*
         * `forceFill` because `organisation_id` and `key` are deliberately not
         * mass-assignable - see SystemSettings::set() for the same reasoning.
         */
        $organisationId = $this->organisations->require()->id;

        FeatureFlag::query()
            ->firstOrNew(['organisation_id' => $organisationId, 'key' => $key])
            ->forceFill([
                'organisation_id' => $organisationId,
                'key' => $key,
                'enabled' => $enabled,
                'reason' => $reason,
                'updated_by_user_id' => $actor->getKey(),
            ])
            ->save();

        unset($this->cache[$key]);

        $this->audit->record(
            action: 'feature_flag.toggled',
            module: 'Platform',
            resourceType: 'feature_flag',
            resourceId: $key,
            before: ['enabled' => $before],
            after: ['enabled' => $enabled],
            reason: $reason,
        );

        return true;
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * The catalogue entry for a flag, or null when nothing declares it.
     *
     * @return array<string, mixed>|null
     */
    public function declaration(string $key): ?array
    {
        $declared = ((array) config('platform.flags', []))[$key] ?? null;

        return is_array($declared) ? $declared : null;
    }

    /**
     * Check the catalogue's precondition for the direction being requested.
     *
     * @param  array<string, mixed>  $definition
     *
     * @throws InvalidArgumentException
     */
    private function assertPreconditionHolds(string $key, array $definition, bool $enabled): void
    {
        $requirement = ($definition['requires'] ?? [])[$enabled ? 'on' : 'off'] ?? null;

        if ($requirement === null) {
            return;
        }

        $holds = match ($requirement) {
            /*
             * "Configured" means the three values the authorisation-code flow
             * cannot run without. Their presence is checked, never their
             * contents, and nothing reads the secret itself.
             */
            'microsoft_sso_configured' => $this->microsoftSsoConfigured(),
            /* An unrecognised requirement refuses the change. A precondition
             * nobody implemented must not read as satisfied. */
            default => false,
        };

        if (! $holds) {
            $this->audit->denied(
                action: 'feature_flag.toggled',
                module: 'Platform',
                resourceType: 'feature_flag',
                resourceId: $key,
                reason: 'Precondition "'.$requirement.'" does not hold.',
            );

            throw new InvalidArgumentException(
                $requirement === 'microsoft_sso_configured'
                    ? 'Microsoft sign-in is not configured. Turning off local sign-in now would leave no way in.'
                    : 'This change has a precondition that is not met.'
            );
        }
    }

    /**
     * Whether Microsoft sign-in has everything it needs to run.
     *
     * Presence only. The values are never read into a variable here, never
     * logged and never returned, because this method's answer reaches a screen.
     */
    private function microsoftSsoConfigured(): bool
    {
        foreach (['tenant', 'client_id', 'client_secret', 'redirect'] as $part) {
            if (blank(config('services.microsoft.'.$part))) {
                return false;
            }
        }

        return true;
    }
}
