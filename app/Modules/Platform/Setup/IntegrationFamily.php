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
     * THE FIELDS THAT DECIDE WHERE A SAVED CREDENTIAL IS SENT - Gate C round 3.
     *
     * D-159 originally protected the credential and nothing else. That is half
     * a control. A credential has a destination, and moving the destination
     * while leaving the credential alone sends the credential somewhere new:
     *
     *   an SMTP password is saved
     *     -> somebody changes only the mail server address
     *     -> the password is untouched, so nothing asks who they are
     *     -> the next test hands that password to a host they chose.
     *
     * So when a secret is ESTABLISHED, changing any of these is privileged in
     * exactly the same way replacing the secret is.
     *
     * THIS IS NOT meaningfulFields(). That answers "does the last test result
     * still mean anything", which is a question about staleness and includes
     * anything the provider sees. This answers "does this change who holds our
     * credential", which is a question about trust. They overlap today and they
     * are not the same question - `deployment` on an Azure OpenAI resource is
     * part of the authenticated path, while a presentation-only field could be
     * meaningful without being privileged.
     *
     * WHAT IS DELIBERATELY ABSENT. Email's `from_address` and `from_name` are
     * display identity, not connection identity: changing them cannot cause the
     * stored password to be offered to a different server. They stay
     * unprivileged so that editing a sender name does not send an administrator
     * to Microsoft - a confirmation demanded for something harmless is a
     * confirmation people learn to click through.
     *
     * IDENTITY IS ABSENT TOO, and that is correction 1's answer rather than an
     * omission: the normal console has no identity write path at all, and P1-02
     * owns the privileged change with its own verify-then-activate flow.
     *
     * @return list<string>
     */
    public function destinationFields(): array
    {
        return match ($this) {
            // P1-02 owns every identity change after installation.
            self::Identity => [],

            // Where the mail goes and who it authenticates as.
            self::Email => ['host', 'port', 'encryption', 'username'],

            // Which service answers, and - on a deployment-bound provider -
            // which deployment the key is presented to.
            self::Ai => ['provider', 'endpoint', 'deployment'],

            // The directory and application the client secret authenticates
            // to, and the workspace that trust reaches.
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

    /**
     * The name a person sees. Never the enum value.
     *
     * THESE ARE D-148's WORDS, RESTORED. The ruling named the four integrations
     * "Identity/SSO, Email & Notifications, AI Provider, Microsoft Fabric", and
     * the implementation drifted to "Email delivery" and "AI service" - close
     * enough to look deliberate, different enough that the screen, the decision
     * record and the body of the test message SemantIQ sends all disagreed
     * about what the feature is called.
     *
     * ONE NAME PER FAMILY, FROM HERE. The tab strip, the section heading and
     * the First-Run step list all read this method, so a fifth spelling cannot
     * appear without changing this line. TestEmailSender::BODY already said
     * "Email & Notifications connection", which is how the drift was noticed.
     */
    public function inWords(): string
    {
        return match ($this) {
            self::Identity => 'Microsoft Entra ID',
            self::Email => 'Email & Notifications',
            self::Ai => 'AI Provider',
            self::Fabric => 'Microsoft Fabric',
        };
    }

    /**
     * What this integration is FOR, in a sentence, for the section heading.
     *
     * Not a status and not an instruction. Every other feature's section head
     * carries one of these - System Health's "Checked when this page was
     * opened", Company Profile's "Create the organisation before adding any
     * structure" - and a tab that opened onto a bare card was the one place in
     * System Administration that told the reader nothing about what they had
     * just opened.
     */
    public function describedAs(): string
    {
        return match ($this) {
            self::Identity => 'How people sign in to SemantIQ. Managed on the Identity & SSO '
                .'screen, which is also where it is checked.',
            self::Email => 'The mail server SemantIQ sends notifications and system messages '
                .'through.',
            self::Ai => 'The AI service SemantIQ will use. Saving these details stores them and '
                .'checks they are accepted; no request for a generated answer is ever made here.',
            self::Fabric => 'The Microsoft Fabric workspace SemantIQ will connect to. Nothing is '
                .'read from it on this screen.',
        };
    }

    /**
     * Where this family's tab lives.
     *
     * THE FIRST TAB IS THE BARE PATH, exactly as Company Profile is
     * /console/organisation and Microsoft Entra ID is /console/identity. Every
     * Pattern B strip in this product is shaped that way, and a fourth shape
     * invented here would be the thing the Product Owner asked us to stop
     * doing.
     */
    public function consolePath(): string
    {
        return $this === self::Identity
            ? '/console/integrations'
            : '/console/integrations/'.$this->value;
    }
}
