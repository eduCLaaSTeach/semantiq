<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

/**
 * Which identity providers the Product Owner has APPROVED to sign people in.
 *
 * This is a decision, not a fact about the code, and the difference is the whole
 * reason this class exists.
 *
 * The first version of the Other Identity Providers screen enumerated every
 * IdentityProvider binding in the service container. That made "approved" mean
 * "present": anything a future developer happened to bind would have promoted
 * itself onto an administrator's screen as an approved way into the product. The
 * rule is that a second identity provider requires an explicit Product Owner
 * decision, and a design where code can grant itself that decision does not
 * implement the rule - it implements its opposite.
 *
 * So the catalogue is a literal. Adding to it is a visible, reviewable,
 * one-line diff that follows a numbered decision, which is exactly the property
 * a container scan did not have.
 *
 * ProviderInventory reads the other set - what is actually bound - and
 * IdentityArchitectureTest fails the build when something is in that set and
 * not in this one.
 */
final class ApprovedProviders
{
    /**
     * Release 1, entire. One entry.
     *
     * @var array<string, string>
     */
    private const APPROVED = [
        'microsoft' => 'Microsoft Entra ID',
    ];

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::APPROVED;
    }

    public static function isApproved(string $key): bool
    {
        return array_key_exists($key, self::APPROVED);
    }

    public static function nameFor(string $key): ?string
    {
        return self::APPROVED[$key] ?? null;
    }
}
