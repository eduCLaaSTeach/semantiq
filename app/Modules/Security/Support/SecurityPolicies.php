<?php

declare(strict_types=1);

namespace App\Modules\Security\Support;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Audit\Support\Redaction;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Enums\PolicyValueType;
use App\Modules\Security\Exceptions\SecurityStorageNotInitialised;
use App\Modules\Security\Models\SecurityPolicy;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Reads and writes security policy. Features ADM-009, ADM-010 and ADM-011.
 *
 * The only supported way in or out of `security_policies`. It resolves a value
 * in one order and one place:
 *
 *   an override for this organisation
 *     -> a platform-wide override
 *       -> the catalogue default in config/security.php
 *
 * SIX GUARDS, none of which a caller can skip.
 *
 * 1. UNKNOWN KEYS THROW, on the way in and on the way out. A typo that read as
 *    null would be a security control silently taking its fallback path, and a
 *    typo on the way in would create a policy nobody declared or reviewed.
 *
 * 2. NO SECRET MAY BE WRITTEN. A key that reads as secret-bearing is refused
 *    whatever the catalogue says, and so is a VALUE that looks like a
 *    credential. These are policy switches and thresholds; a credential belongs
 *    in the server environment behind an ADM-012 reference.
 *
 * 3. THE VALUE IS VALIDATED AGAINST THE CATALOGUE. Here, not in the form
 *    request. A console command, a queued job and a future API all reach this
 *    class without passing a request, and an idle timeout of minus one would be
 *    a session that never expires.
 *
 * 4. THE EDITING TIER IS CHECKED HERE. The route and the rail check it too.
 *    Three layers is not redundancy for the same reason as above.
 *
 * 5. A HIGH-RISK CHANGE REQUIRES A WRITTEN REASON. Rule 4 of gate 3. A policy
 *    weakened without an explanation is one nobody dares change back, because
 *    nobody knows what it was protecting against.
 *
 * 6. EVERY WRITE IS AUDITED, with the old and new values summarised through
 *    `Redaction`. There is no unaudited path, and a change that fails still
 *    records the denial.
 *
 * DEPLOYMENT ORDER. The deploy workflow ships code and does not run migrations,
 * so there is a window in which this class exists and `security_policies` does
 * not. `SecurityHeaders` reads a policy on EVERY response, so without a
 * fallback the first request after a deploy takes the whole site down - sign-in
 * included, so nobody can get in to notice. Measured, not guessed.
 *
 * The two halves are treated differently on purpose:
 *
 *   READS fall back to the catalogue default, which is not a compromise: with
 *   no table there can be no override, so the default IS the value in force.
 *   The secure defaults stay authoritative.
 *
 *   WRITES FAIL CLOSED with `SecurityStorageNotInitialised`. Accepting a write
 *   and discarding it would tell an administrator their security policy had
 *   changed when nothing had, which is worse than any error message.
 *
 * WHAT THIS CLASS DOES NOT DO: enforce anything. It answers what the policy
 * says. `EnforceSessionPolicy`, `SecurityHeaders`, `SignInController` and
 * `SessionRegistry` are what act on the answer, and each of them is tested
 * against behaviour rather than against the stored value.
 */
class SecurityPolicies
{
    /** Memoised per request. Policy is read many times and changes rarely. */
    private array $cache = [];

    public function __construct(
        private readonly OrganisationContext $organisations,
        private readonly AuditLogger $audit,
        private readonly SecurityCapabilities $capabilities,
        private readonly SecurityStorage $storage,
    ) {}

    /**
     * The catalogue entry for a key.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the key is not declared.
     */
    public function definition(string $key): array
    {
        /*
         * Indexed out of the whole array rather than `config('security.policies.'.$key)`.
         * Policy keys contain dots and `config()` reads a dot as nesting, so
         * that form asks for policies -> sign_in -> mode and finds nothing.
         * The same trap has already cost this repository twice: once in the
         * navigation policy names and once in the platform settings catalogue.
         */
        $definition = ((array) config('security.policies', []))[$key] ?? null;

        if (! is_array($definition)) {
            throw new InvalidArgumentException(
                'Unknown security policy "'.$key.'". Policies must be declared in config/security.php before they can be used.'
            );
        }

        return $definition;
    }

    /**
     * Every declared key for one screen, in catalogue order.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forScreen(string $screen): array
    {
        return array_filter(
            (array) config('security.policies', []),
            static fn (array $definition): bool => ($definition['screen'] ?? null) === $screen,
        );
    }

    /**
     * The value in force for a key.
     */
    public function get(string $key): string|int|bool|null
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $definition = $this->definition($key);
        $type = $definition['type'];

        /*
         * Before the migration has run there is no table, therefore no
         * override, therefore the catalogue default is not a fallback - it is
         * the answer. The check is a schema question, not a caught exception:
         * a broken connection or a permissions problem must still fail loudly
         * rather than be reported as "everything is fine, using defaults".
         */
        if (! $this->storage->policiesAreReady()) {
            return $this->cache[$key] = $definition['default'];
        }

        /*
         * The global scope already limits this to the current organisation's
         * rows plus platform-wide ones. Ordering by `organisation_id`
         * descending puts the organisation's own override first, so a customer
         * value wins over a platform default. Null sorts last on both MySQL and
         * SQLite for a DESC order, which is the behaviour relied on here.
         */
        $stored = SecurityPolicy::query()
            ->where('key', $key)
            ->orderByDesc('organisation_id')
            ->value('value');

        $value = $type instanceof PolicyValueType ? $type->cast($stored) : null;

        return $this->cache[$key] = $value ?? $definition['default'];
    }

    /** A boolean policy, resolved. */
    public function enabled(string $key): bool
    {
        return $this->get($key) === true;
    }

    /** An integer policy, resolved. */
    public function number(string $key): int
    {
        return (int) $this->get($key);
    }

    /** A text policy, resolved. */
    public function text(string $key): string
    {
        return (string) $this->get($key);
    }

    /**
     * A list policy, split into its entries.
     *
     * @return list<string>
     */
    public function entries(string $key): array
    {
        return PolicyValueType::listEntries((string) $this->get($key));
    }

    /**
     * Whether the stored value is ACTUALLY IN FORCE here.
     *
     * Distinct from `get()`, and the distinction is the whole of decision D3. A
     * policy row saying "one session at a time" is a stated intention; whether
     * this deployment can apply it depends on the session driver. Every screen
     * asks this before showing a control as active, so no page ever claims a
     * control is protecting something when it is not.
     */
    public function inForce(string $key): bool
    {
        $required = $this->definition($key)['requires'] ?? null;

        return $required === null || $this->capabilities->has((string) $required);
    }

    /**
     * Why a key is not in force, or null when it is.
     */
    public function blocker(string $key): ?string
    {
        $required = $this->definition($key)['requires'] ?? null;

        if ($required === null) {
            return null;
        }

        return $this->capabilities->blocker((string) $required);
    }

    /**
     * Change a policy, if the actor may, the value is allowed, and a reason has
     * been given where one is required.
     *
     * Returns true when something actually changed. A write of the same value
     * is not audited, because a trail full of non-changes is a trail nobody
     * reads - and on a security screen that matters more, not less.
     *
     * @throws InvalidArgumentException when the key is unknown or secret-bearing,
     *                                  the value is invalid or credential-shaped,
     *                                  the actor lacks authority, or a required
     *                                  reason is missing.
     */
    public function set(string $key, string|int|bool|null $value, User $actor, ?string $reason = null): bool
    {
        $definition = $this->definition($key);

        /*
         * Guard 0 - the storage exists. FIRST, before anything else is
         * evaluated, so nothing about the change is audited as though it might
         * have happened. Fails closed: see the class docblock for why a write
         * cannot take the read path's fallback.
         */
        if (! $this->storage->policiesAreReady()) {
            throw SecurityStorageNotInitialised::forWrite((string) ($definition['label'] ?? $key));
        }

        /* Guard 2a - the key. */
        if (Redaction::isSensitiveKey($key)) {
            throw new InvalidArgumentException(
                'Security policy "'.$key.'" reads as secret-bearing. Credentials belong in the server environment '
                .'or behind a secret reference, never in security_policies.'
            );
        }

        /* Guard 2b - the value. */
        if (is_string($value) && $value !== '' && Redaction::scrub($value) !== $value) {
            $this->audit->denied(
                action: 'security.policy.updated',
                module: 'Security',
                resourceType: 'security_policy',
                resourceId: $key,
                reason: 'The submitted value looked like a credential and was refused.',
            );

            throw new InvalidArgumentException(
                'That value looks like a credential. Security policy holds switches and thresholds, never secrets.'
            );
        }

        /* Guard 3 - the catalogue's own validation rules. */
        $this->validate($key, $definition, $value);

        /* Guard 4 - authority. */
        $required = $definition['editable'] ?? Role::SystemAdmin;

        if (! $required instanceof Role || ! $actor->hasAtLeast($required)) {
            $this->audit->denied(
                action: 'security.policy.updated',
                module: 'Security',
                resourceType: 'security_policy',
                resourceId: $key,
                reason: 'Actor does not hold the tier this security policy requires.',
            );

            throw new InvalidArgumentException('You do not have authority to change "'.$key.'".');
        }

        $before = $this->get($key);

        if ($before === $value) {
            return false;
        }

        /* Guard 5 - a reason, where the catalogue demands one. */
        $highRisk = ($definition['high_risk'] ?? false) === true;
        $reason = $reason === null ? null : trim($reason);

        if ($highRisk && ($reason === null || $reason === '')) {
            throw new InvalidArgumentException(
                'Changing "'.($definition['label'] ?? $key).'" needs a reason. It is a high-risk security policy, and '
                .'a change nobody explained is a change nobody can safely undo.'
            );
        }

        $type = $definition['type'];
        $stored = $value === null || ! $type instanceof PolicyValueType ? null : $type->toStorage($value);

        /*
         * Written against the organisation in force, matched on the same
         * columns as the table's unique index, so a concurrent write collides
         * at the database rather than producing two rows that disagree about
         * the current policy.
         *
         * `forceFill` because `organisation_id` and `key` are deliberately not
         * mass-assignable: neither is ever taken from a request, and
         * `updateOrCreate` would silently drop both.
         */
        $organisationId = $this->organisations->require()->id;

        SecurityPolicy::query()
            ->firstOrNew(['organisation_id' => $organisationId, 'key' => $key])
            ->forceFill([
                'organisation_id' => $organisationId,
                'key' => $key,
                'value' => $stored,
                'reason' => $reason,
                'updated_by_user_id' => $actor->getKey(),
            ])
            ->save();

        unset($this->cache[$key]);

        /* Guard 6 - always. There is no `audited` flag on this catalogue,
         * unlike the platform one: rule 3 of gate 3 is that ALL security policy
         * changes are audited, so there is nothing to switch off. */
        $this->audit->record(
            action: 'security.policy.updated',
            module: 'Security',
            resourceType: 'security_policy',
            resourceId: $key,
            before: ['policy' => $key, 'value' => $before],
            after: ['policy' => $key, 'value' => $value],
            reason: $reason,
        );

        return true;
    }

    /**
     * Drop every memoised value.
     *
     * For tests that migrate or roll back mid-run. NOTHING IN THE APPLICATION
     * CALLS IT: within a request the schema cannot change and a write already
     * evicts its own key, so a caller reaching for this in application code
     * would be working around a problem rather than fixing one.
     */
    public function forget(): void
    {
        $this->cache = [];
    }

    /** Whether policy can be CHANGED here yet, as opposed to only read. */
    public function storageIsReady(): bool
    {
        return $this->storage->policiesAreReady();
    }

    /** Why policy cannot be changed yet, or null when it can. */
    public function storageBlocker(): ?string
    {
        return $this->storage->policiesAreReady() ? null : $this->storage->blocker();
    }

    /**
     * Apply the catalogue's validation rules to a proposed value.
     *
     * A `Choice` is additionally checked against its declared options, because
     * the catalogue's rules say `string` and a crafted post could otherwise set
     * an authentication mode this application does not implement - which would
     * resolve to no branch at all in the sign-in path.
     *
     * @param  array<string, mixed>  $definition
     */
    private function validate(string $key, array $definition, string|int|bool|null $value): void
    {
        $rules = $definition['rules'] ?? [];

        if ($rules !== []) {
            $validator = Validator::make(['value' => $value], ['value' => $rules]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(sprintf(
                    '"%s" was not accepted: %s',
                    $definition['label'] ?? $key,
                    (string) $validator->errors()->first('value'),
                ));
            }
        }

        if (($definition['type'] ?? null) === PolicyValueType::Choice) {
            $choices = array_keys((array) ($definition['choices'] ?? []));

            if (! in_array((string) $value, $choices, true)) {
                throw new InvalidArgumentException(
                    '"'.($definition['label'] ?? $key).'" must be one of the offered options.'
                );
            }
        }
    }
}
