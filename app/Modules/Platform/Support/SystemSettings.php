<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Audit\Support\Redaction;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\SettingType;
use App\Modules\Platform\Models\SystemSetting;
use InvalidArgumentException;

/**
 * Reads and writes runtime configuration. Feature ADM-021.
 *
 * The only supported way in or out of `system_settings`. It resolves a value in
 * one order and one place:
 *
 *   an override for this organisation
 *     -> a platform-wide override
 *       -> the catalogue default in config/platform.php
 *
 * Four guards, none of which a caller can skip.
 *
 * UNKNOWN KEYS THROW. Not "return null": a typo that reads as null is a feature
 * silently taking its fallback path, and a typo on the way in would create a
 * setting nobody declared or reviewed.
 *
 * NO SECRET MAY BE WRITTEN. A key whose name reads as secret-bearing is refused
 * outright, whatever the catalogue says. CLAUDE.md forbids a credential in an
 * application table, and the check is here rather than in a review comment.
 *
 * THE EDITING TIER IS CHECKED HERE. The route and the rail check it too. Three
 * layers is not redundancy: a console command and a queued job reach this class
 * without passing either of the other two.
 *
 * EVERY WRITE IS AUDITED, with the OLD and NEW values summarised through
 * `Redaction`, never stored raw.
 */
class SystemSettings
{
    /** Memoised per request. Settings are read many times and change rarely. */
    private array $cache = [];

    public function __construct(
        private readonly OrganisationContext $organisations,
        private readonly AuditLogger $audit,
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
         * Indexed out of the whole array rather than looked up with
         * `config('platform.settings.'.$key)`. Setting keys contain dots and
         * `config()` reads a dot as nesting, so that form asks for
         * settings -> app -> display_name and finds nothing. The same trap
         * already cost this repository once, in the navigation policy names.
         */
        $definition = ((array) config('platform.settings', []))[$key] ?? null;

        if (! is_array($definition)) {
            throw new InvalidArgumentException(
                'Unknown setting "'.$key.'". Settings must be declared in config/platform.php before they can be used.'
            );
        }

        return $definition;
    }

    /**
     * Every declared key in a category, in catalogue order.
     *
     * @return array<string, array<string, mixed>>
     */
    public function inCategory(string $category): array
    {
        $declared = (array) config('platform.settings', []);

        return array_filter(
            $declared,
            fn (array $definition): bool => ($definition['category'] ?? null) === $category,
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
         * The global scope already limits this to the current organisation's
         * rows plus platform-wide ones. Ordering by `organisation_id`
         * descending puts the organisation's own override first, so a customer
         * value wins over a platform default. Null sorts last on both MySQL and
         * SQLite for a DESC order, which is the behaviour relied on here.
         */
        $stored = SystemSetting::query()
            ->where('key', $key)
            ->orderByDesc('organisation_id')
            ->value('value');

        $value = $type instanceof SettingType ? $type->cast($stored) : null;

        return $this->cache[$key] = $value ?? $definition['default'];
    }

    /**
     * Change a value, if the actor may and the value is allowed.
     *
     * Returns true when something actually changed. A write of the same value
     * is not audited, because an audit trail full of non-changes is a trail
     * nobody reads.
     *
     * @throws InvalidArgumentException when the key is unknown, secret-bearing,
     *                                  or outside the actor's authority.
     */
    public function set(string $key, string|int|bool|null $value, User $actor, ?string $reason = null): bool
    {
        $definition = $this->definition($key);

        /*
         * The rule that must hold whatever the catalogue says. A setting called
         * `smtp.password` would be a credential in an application table, which
         * CLAUDE.md forbids outright, and gate 3's secret references are where
         * such a thing belongs.
         */
        if (Redaction::isSensitiveKey($key) || ($definition['sensitive'] ?? false) === true) {
            throw new InvalidArgumentException(
                'Setting "'.$key.'" reads as secret-bearing. Credentials belong in the server environment or a '
                .'secret reference, never in system_settings.'
            );
        }

        $required = $definition['editable'] ?? Role::SystemAdmin;

        if (! $required instanceof Role || ! $actor->hasAtLeast($required)) {
            $this->audit->denied(
                action: 'system.setting.updated',
                module: 'Platform',
                resourceType: 'system_setting',
                resourceId: $key,
                reason: 'Actor does not hold the tier this setting requires.',
            );

            throw new InvalidArgumentException('You do not have authority to change "'.$key.'".');
        }

        $before = $this->get($key);

        if ($before === $value) {
            return false;
        }

        $type = $definition['type'];
        $stored = $value === null || ! $type instanceof SettingType ? null : $type->toStorage($value);

        /*
         * Written against the organisation in force, matched on the same
         * columns as the table's unique index, so a concurrent write collides
         * at the database rather than producing two rows that disagree about
         * the current value.
         *
         * `forceFill` because `organisation_id` and `key` are deliberately not
         * mass-assignable: neither is ever taken from a request, and
         * `updateOrCreate` would silently drop both.
         */
        $organisationId = $this->organisations->require()->id;

        SystemSetting::query()
            ->firstOrNew(['organisation_id' => $organisationId, 'key' => $key])
            ->forceFill([
                'organisation_id' => $organisationId,
                'key' => $key,
                'value' => $stored,
                'updated_by_user_id' => $actor->getKey(),
            ])
            ->save();

        unset($this->cache[$key]);

        if (($definition['audited'] ?? true) === true) {
            $this->audit->record(
                action: 'system.setting.updated',
                module: 'Platform',
                resourceType: 'system_setting',
                resourceId: $key,
                before: ['value' => $before],
                after: ['value' => $value],
                reason: $reason,
            );
        }

        return true;
    }

    /**
     * Drop the memoised values.
     *
     * Needed by a long-running process that changes a setting and then reads it
     * back, and by tests. Not needed within one request, where the memoisation
     * is invalidated by `set()` itself.
     */
    public function flush(): void
    {
        $this->cache = [];
    }
}
