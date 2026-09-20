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

    /**
     * WHICH FAMILIES THE NORMAL CONSOLE MAY WRITE - D-148.
     *
     * IDENTITY IS NOT ONE OF THEM, and that is an ownership boundary rather
     * than a caution. P1-02 owns Microsoft Entra configuration after
     * installation: its screens, its re-check, its masking and reveal rules.
     * Rendering the same editable form under Platform Integrations would make
     * this a SECOND Identity administration surface - two places to change one
     * thing, two sets of validation, and two chances for one of them to be
     * more permissive.
     *
     * FIRST-RUN IS THE EXPLICIT EXCEPTION, and it is an exception about WHO
     * rather than about ownership: the local Bootstrap principal is not a User
     * and can reach no `/console/*` route at all, so P1-02's screens are
     * unreachable to it. First-Run renders the setup form and still calls
     * P1-02's owning writer - it does not create a second model.
     *
     * @return list<self>
     */
    public static function writableOnTheConsole(): array
    {
        return [self::Email, self::Ai, self::Fabric];
    }

    public function isWritableOnTheConsole(): bool
    {
        return in_array($this, self::writableOnTheConsole(), true);
    }

    /**
     * Fields that are a CHOICE, with the words a person reads.
     *
     * These were free-text inputs whose LABEL carried the permitted values -
     * "AI service (azure_openai or openai)". The browser sweep caught it: a
     * raw enum value on a user-facing surface is the first item on the
     * professional-polish gate, and it was there because the field had the
     * wrong control. A choice typed as free text also lets an administrator
     * enter "Azure OpenAI" and be told nothing until the connection test says
     * the provider cannot be checked.
     *
     * The stored VALUE stays the machine name, because that is what the
     * adapters match on. Only what is displayed changes.
     *
     * @return array<string, string> stored value => the words shown
     */
    public function choices(string $field): array
    {
        return match (true) {
            $this === self::Ai && $field === 'provider' => [
                'azure_openai' => 'Azure OpenAI',
                'openai' => 'OpenAI',
            ],
            $this === self::Email && $field === 'encryption' => [
                'tls' => 'STARTTLS (usually port 587)',
                'ssl' => 'SSL/TLS (usually port 465)',
            ],
            default => [],
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
