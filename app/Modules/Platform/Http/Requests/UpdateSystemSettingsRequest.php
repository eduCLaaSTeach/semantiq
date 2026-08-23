<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Enums\SettingType;
use App\Modules\Platform\Support\SystemSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a System Configuration form. Feature ADM-021.
 *
 * The rules are BUILT FROM THE CATALOGUE in config/platform.php rather than
 * written out here. Two copies of "what a valid value looks like" is one copy
 * too many: the day somebody widens a field in the catalogue and forgets this
 * class, the form starts rejecting a value the rest of the application accepts,
 * or - much worse - accepting one it does not.
 *
 * Only keys in the requested category are validated, and anything else posted
 * is ignored rather than rejected, so a stale form cannot be used to reach a
 * setting on another screen.
 *
 * WHY THE KEYS ARE SLUGGED. Setting keys contain dots. Laravel's validator and
 * `input()` both read a dot as nesting, so a field named
 * `settings[app.display_name]` would be looked up as `settings -> app ->
 * display_name` and never found. The form therefore posts `app__display_name`
 * and this class maps it back. The alternative - renaming every setting to
 * avoid dots - would let a framework detail dictate the configuration
 * vocabulary.
 */
class UpdateSystemSettingsRequest extends FormRequest
{
    /**
     * Authorisation is the route's `policy:system-admin` middleware, and the
     * per-setting editing tier is checked again inside `SystemSettings::set()`
     * where console and queue callers also pass. Repeating it here would be a
     * third copy of the same rule with no third caller to protect.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Turn a setting key into something a form field and a validation rule can
     * both address.
     */
    public static function slug(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array']];

        foreach ($this->settingsInScope() as $key => $definition) {
            $field = 'settings.'.self::slug($key);
            $type = $definition['type'] ?? null;

            $declared = (array) ($definition['rules'] ?? []);

            /*
             * A choice is constrained to its declared options here rather than
             * in the catalogue, so adding an option is one edit and cannot
             * leave a stale `in:` list behind.
             */
            if ($type === SettingType::Choice) {
                $declared[] = Rule::in(array_keys((array) ($definition['choices'] ?? [])));
            }

            /*
             * An unchecked checkbox posts nothing at all, so a boolean must
             * tolerate absence or every save would fail on the first "off".
             * `normalise()` turns the absence into an explicit false.
             */
            if ($type === SettingType::Boolean) {
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
        $names = [];

        foreach ($this->settingsInScope() as $key => $definition) {
            $names['settings.'.self::slug($key)] = strtolower((string) ($definition['label'] ?? $key));
        }

        return $names;
    }

    /**
     * The validated values, keyed by their real setting key.
     *
     * Only catalogue keys in scope come back, so an extra field posted by a
     * crafted request is dropped rather than passed on to the writer.
     *
     * @return array<string, string|int|bool|null>
     */
    public function normalise(): array
    {
        $submitted = (array) $this->validated()['settings'];
        $values = [];

        foreach ($this->settingsInScope() as $key => $definition) {
            $field = self::slug($key);
            $type = $definition['type'] ?? null;

            if ($type === SettingType::Boolean) {
                /* Absent means off - see rules(). */
                $values[$key] = (bool) ($submitted[$field] ?? false);

                continue;
            }

            if (! array_key_exists($field, $submitted)) {
                continue;
            }

            $raw = $submitted[$field];

            $values[$key] = match ($type) {
                SettingType::Integer => $raw === null || $raw === '' ? null : (int) $raw,
                /* An emptied optional text field stores an empty string rather
                 * than null, so "cleared by an administrator" stays
                 * distinguishable from "never set". */
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
    private function settingsInScope(): array
    {
        return app(SystemSettings::class)->inCategory($this->routeCategory());
    }

    /**
     * Which screen posted this.
     *
     * Taken from the ROUTE, never from the request body. Reading it from the
     * body would let a crafted post name a category it was not authorised to
     * open and edit settings from another screen.
     */
    private function routeCategory(): string
    {
        return (string) $this->route('category');
    }
}
