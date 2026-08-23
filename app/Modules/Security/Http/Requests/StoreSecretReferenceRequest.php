<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use App\Modules\Audit\Support\Redaction;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Enums\SecretProvider;
use App\Modules\Security\Enums\SecretType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

/**
 * Validates a secret reference. Feature ADM-012.
 *
 * THE RULE THIS CLASS EXISTS FOR: a secret must never reach the database. The
 * field it would arrive in is called "reference identifier", and the difference
 * between a pointer and the thing it points at is not obvious to somebody in a
 * hurry with a Key Vault page open in another tab.
 *
 * Three defences, deliberately layered:
 *
 * 1. HERE, at the boundary, so the person gets a message they can act on rather
 *    than a 500. Every free-text field is checked, not only the obvious one:
 *    somebody pasting a client secret pastes it into whichever box they are
 *    looking at, and a leaked secret in a "purpose" field has leaked just as
 *    thoroughly.
 * 2. IN THE MODEL, so a console command, a seeder or a queued job that never
 *    passes a request is covered too.
 * 3. IN THE COLUMN, at 190 characters, because a pointer is short and a
 *    credential usually is not.
 *
 * The detector is `Redaction::scrub()` - the audit trail's own. Reusing it
 * rather than writing a second one is deliberate: two detectors drift, and the
 * day they disagree is the day one of them is wrong.
 */
class StoreSecretReferenceRequest extends FormRequest
{
    /**
     * The free-text fields a credential could be pasted into.
     *
     * @var list<string>
     */
    protected const CREDENTIAL_RISK_FIELDS = ['name', 'reference_identifier', 'purpose', 'environment'];

    /**
     * Authorisation is the route's `permission:admin.secrets.manage`
     * middleware, and the model refuses a credential-shaped value whatever
     * reaches it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', $this->uniqueNameRule()],
            'reference_type' => ['required', Rule::enum(SecretType::class)],
            'provider' => ['required', Rule::enum(SecretProvider::class)],
            'reference_identifier' => ['required', 'string', 'max:190'],
            'purpose' => ['required', 'string', 'max:500'],
            'environment' => ['required', 'string', 'max:40'],
            'owner_user_id' => ['nullable', 'integer'],
            'expires_on' => ['nullable', 'date'],
            /*
             * Rotation before expiry, not after. A rotation date past the
             * expiry date is a reminder that arrives once the credential has
             * already stopped working, which is the one time it is useless.
             */
            'rotation_due_on' => ['nullable', 'date', 'before_or_equal:expires_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rotation_due_on.before_or_equal' => 'Rotation should fall on or before the expiry date. A reminder that arrives after the credential has lapsed is too late to be useful.',
            'name.unique' => 'A reference with that name already exists. Two references with the same name is how the wrong one gets rotated.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reference_type' => 'secret type',
            'reference_identifier' => 'reference identifier',
            'owner_user_id' => 'owner',
            'expires_on' => 'expiry date',
            'rotation_due_on' => 'rotation date',
        ];
    }

    /**
     * The credential check, run after the ordinary rules have passed.
     *
     * `after` rather than a custom rule on each field, so the message names the
     * offending field once and does not repeat itself four times when somebody
     * pastes the same string into several boxes.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (static::CREDENTIAL_RISK_FIELDS as $field) {
                    $value = $this->input($field);

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    if (Redaction::scrub($value) === $value) {
                        continue;
                    }

                    /*
                     * The message never quotes the value. Echoing it back would
                     * write the credential into a validation bag, a rendered
                     * page and possibly a log - the exact outcome the check
                     * exists to prevent. `failedValidation()` below keeps it out
                     * of the flashed input for the same reason.
                     */
                    $validator->errors()->add($field, 'That looks like a credential. Record WHERE the credential is '
                        .'kept - a name, a path, an identifier - never the credential itself.');
                }
            },
        ];
    }

    /**
     * Refuse the form WITHOUT flashing a credential back into the session.
     *
     * A failed form request normally redirects back with the whole input, and
     * the form repopulates from it - so a refused credential would be written
     * to the session store on the server and rendered straight back into the
     * HTML of the next page. That is the outcome this whole class exists to
     * prevent, and it was still happening until a test looked for the value on
     * the page afterwards rather than only checking that the save was refused.
     *
     * Merging into `$this` is not enough: a form request is a COPY of the
     * request, and the exception handler flashes the ORIGINAL. The response is
     * therefore built here, with the offending fields blanked and everything
     * else preserved so the person does not lose the rest of the form.
     */
    protected function failedValidation(Validator $validator): void
    {
        $input = $this->all();

        foreach (static::CREDENTIAL_RISK_FIELDS as $field) {
            $value = $input[$field] ?? null;

            if (is_string($value) && $value !== '' && Redaction::scrub($value) !== $value) {
                $input[$field] = '';
            }
        }

        throw new ValidationException(
            $validator,
            redirect($this->getRedirectUrl())
                ->withInput($input)
                ->withErrors($validator->errors()->messages(), $this->errorBag),
        );
    }

    /**
     * One name per organisation.
     *
     * The unique rule is scoped by hand rather than through the model, because
     * a global unique across every organisation would leak the existence of
     * another customer's reference by refusing a name.
     */
    protected function uniqueNameRule(): Unique
    {
        return Rule::unique('secret_references', 'name')
            ->where('organisation_id', app(OrganisationContext::class)->currentId());
    }
}
