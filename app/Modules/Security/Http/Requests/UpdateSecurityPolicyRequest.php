<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use App\Modules\Security\Enums\PolicyValueType;
use App\Modules\Security\Support\SecurityPolicies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one of the three security policy forms. Features ADM-009 to ADM-011.
 *
 * The rules are BUILT FROM THE CATALOGUE in `config/security.php` rather than
 * written out here, for the same reason `UpdateSystemSettingsRequest` does it:
 * two copies of "what a valid value looks like" is one copy too many, and the
 * day they disagree the form either rejects something the service accepts or
 * accepts something it does not.
 *
 * WHICH SCREEN is taken from the ROUTE NAME, never from the request body.
 * Reading it from the body would let a crafted post name a screen it was not
 * authorised to open and reach a policy it never rendered.
 *
 * WHY THE KEYS ARE SLUGGED. Policy keys contain dots, and Laravel's validator
 * reads a dot as nesting, so a field named `policies[sign_in.mode]` would be
 * looked up as `policies -> sign_in -> mode` and never found. The form posts
 * `sign_in__mode` and this class maps it back.
 *
 * THE REASON FIELD is validated here only for length. Whether one is REQUIRED
 * depends on which values actually changed, which this class cannot know
 * because it has not read the current values - so the requirement is enforced
 * in `SecurityPolicies::set()`, where the before-and-after comparison happens
 * and where a console or queued caller passes too.
 */
class UpdateSecurityPolicyRequest extends FormRequest
{
    /**
     * Authorisation is the route's `permission:admin.security.update`
     * middleware, and the per-policy editing tier is checked again inside
     * `SecurityPolicies::set()` where console and queue callers also pass.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** Turn a policy key into something a form field and a rule can address. */
    public static function slug(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'policies' => ['required', 'array'],
            /*
             * Long enough for a real explanation and bounded to the column.
             * `nullable` because most keys do not need one; the ones that do
             * are refused by the service with a message naming the field.
             */
            'reason' => ['nullable', 'string', 'max:500'],
        ];

        foreach ($this->policiesInScope() as $key => $definition) {
            $field = 'policies.'.self::slug($key);
            $type = $definition['type'] ?? null;

            $declared = (array) ($definition['rules'] ?? []);

            /* A choice is constrained to its declared options here, so adding
             * an option is one edit and cannot leave a stale list behind. */
            if ($type === PolicyValueType::Choice) {
                $declared[] = Rule::in(array_keys((array) ($definition['choices'] ?? [])));
            }

            /* An unchecked checkbox posts nothing at all, so a boolean must
             * tolerate absence or every save would fail on the first "off". */
            if ($type === PolicyValueType::Boolean) {
                array_unshift($declared, 'sometimes');
            }

            $rules[$field] = $declared;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = ['reason' => 'reason for this change'];

        foreach ($this->policiesInScope() as $key => $definition) {
            $names['policies.'.self::slug($key)] = strtolower((string) ($definition['label'] ?? $key));
        }

        return $names;
    }

    /** The written reason, or null when none was given. */
    public function reason(): ?string
    {
        $reason = trim((string) ($this->validated()['reason'] ?? ''));

        return $reason === '' ? null : $reason;
    }

    /**
     * The validated values, keyed by their real policy key.
     *
     * Only catalogue keys belonging to THIS screen come back, so an extra field
     * posted by a crafted request is dropped rather than passed to the writer.
     *
     * @return array<string, string|int|bool|null>
     */
    public function normalise(): array
    {
        $submitted = (array) ($this->validated()['policies'] ?? []);
        $values = [];

        foreach ($this->policiesInScope() as $key => $definition) {
            $field = self::slug($key);
            $type = $definition['type'] ?? null;

            if ($type === PolicyValueType::Boolean) {
                $values[$key] = (bool) ($submitted[$field] ?? false);

                continue;
            }

            if (! array_key_exists($field, $submitted)) {
                continue;
            }

            $raw = $submitted[$field];

            $values[$key] = match ($type) {
                PolicyValueType::Integer => $raw === null || $raw === '' ? null : (int) $raw,
                /* An emptied optional field stores an empty string rather than
                 * null, so "cleared deliberately" stays distinguishable from
                 * "never set". On an allow-list that difference is the
                 * difference between "allow everything" and "not configured". */
                default => $raw === null ? '' : (string) $raw,
            };
        }

        return $values;
    }

    /**
     * The catalogue entries this request is allowed to touch.
     *
     * @return array<string, array<string, mixed>>
     */
    private function policiesInScope(): array
    {
        return app(SecurityPolicies::class)->forScreen($this->screen());
    }

    /**
     * Which screen posted this, from the route name rather than the body.
     */
    private function screen(): string
    {
        return match ($this->route()?->getName()) {
            'admin.security.authentication.update' => 'authentication',
            'admin.security.sessions.update' => 'sessions',
            'admin.security.api.update' => 'api',
            /* An unrecognised route means no policies are in scope, so nothing
             * validates and nothing is written. Failing to an empty set rather
             * than to a default screen keeps a new route from silently
             * inheriting another screen's fields. */
            default => '',
        };
    }
}
