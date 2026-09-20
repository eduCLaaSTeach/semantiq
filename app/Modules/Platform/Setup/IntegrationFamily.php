<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup;

/**
 * THE CLOSED SET. Four families, and no way to invent a fifth.
 *
 * A caller cannot spell a new family into existence, because `family` is this
 * type rather than a string everywhere it is decided. The database column is a
 * string, but nothing writes it except a value that came from here.
 *
 * THE FIELD ALLOWLIST IS PART OF THE TYPE, not a validation rule somebody has
 * to remember to apply. A key that is not listed for a family cannot be stored:
 * that is what stops a future field arriving unvalidated, and it is why the
 * configuration column can be JSON without being a dumping ground.
 *
 * meaningfulFields() IS THE INVALIDATION QUESTION, asked in one place.
 * Changing a value that reaches the provider invalidates the last test result;
 * changing one that does not, does not. Answering it per call site would mean
 * four answers that drift.
 */
enum IntegrationFamily: string
{
    case Identity = 'identity';

    case Email = 'email';

    case Ai = 'ai';

    case Fabric = 'fabric';

    /**
     * Every non-secret field this family may store. Anything else is refused.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Identity => ['tenant_id', 'client_id', 'redirect_uri'],
            self::Email => ['host', 'port', 'encryption', 'username', 'from_address', 'from_name'],
            self::Ai => ['provider', 'endpoint', 'deployment'],
            self::Fabric => ['tenant_id', 'client_id', 'workspace_id'],
        };
    }

    /**
     * The fields whose change invalidates the last test result.
     *
     * EVERY FIELD THAT REACHES THE PROVIDER. Presentation-only fields do not:
     * changing the display name on an email sender does not make yesterday's
     * successful SMTP handshake untrue, and clearing the result for it would
     * train administrators to ignore Not checked.
     *
     * Every SECRET write is meaningful without appearing here, because a
     * secret by definition reaches the provider. IntegrationConfigurationWriter
     * invalidates on any secret write unconditionally.
     *
     * @return list<string>
     */
    public function meaningfulFields(): array
    {
        return match ($this) {
            self::Identity => ['tenant_id', 'client_id', 'redirect_uri'],
            self::Email => ['host', 'port', 'encryption', 'username'],
            self::Ai => ['provider', 'endpoint', 'deployment'],
            self::Fabric => ['tenant_id', 'client_id', 'workspace_id'],
        };
    }

    /**
     * The named secrets this family may hold. A name outside this list is
     * refused, so "store one more thing, encrypted" is not available as a
     * shortcut around the typed field allowlist above.
     *
     * @return list<string>
     */
    public function secrets(): array
    {
        return match ($this) {
            self::Identity => ['client_secret'],
            self::Email => ['password'],
            self::Ai => ['api_key'],
            self::Fabric => ['client_secret'],
        };
    }

    /** The name a person sees. Never the enum value. */
    public function inWords(): string
    {
        return match ($this) {
            self::Identity => 'Microsoft Entra ID',
            self::Email => 'Email delivery',
            self::Ai => 'AI service',
            self::Fabric => 'Microsoft Fabric',
        };
    }
}
