<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Security\Enums\SecretProvider;
use App\Modules\Security\Enums\SecretStatus;
use App\Modules\Security\Enums\SecretType;
use App\Modules\Security\Exceptions\CredentialShapedValue;
use App\Modules\Security\Http\Requests\StoreSecretReferenceRequest;
use App\Modules\Security\Http\Requests\UpdateSecretReferenceRequest;
use App\Modules\Security\Models\SecretReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Secret References. Feature ADM-012.
 *
 * Records WHERE a credential is kept, never the credential. The question it
 * answers is "what does this system depend on, where does each thing live, who
 * owns it, and when does it lapse" - which is what turns an expired client
 * secret from a Monday morning outage into a diary entry.
 *
 * THERE IS NO RESOLVE. Nothing here fetches a value from Key Vault, from the
 * environment or from anywhere else, and no route offers to. An application
 * that can resolve a reference is an application that holds credentials at
 * runtime; resolving belongs with the integration work in gate 5, behind its
 * own architecture decision.
 *
 * REFERENCES ARE RETIRED, NEVER DELETED. A credential that used to exist is
 * part of the history an incident review reads, and a deleted row answers no
 * questions. There is deliberately no destroy route.
 */
class SecretReferenceController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        /*
         * The global organisation scope does the tenancy work here, so there is
         * no explicit `where` to forget. Ordered by expiry with the nulls last,
         * so the references that need attention come first and the ones nobody
         * gave a date to sit at the bottom where their absence is visible.
         */
        $references = SecretReference::query()
            ->with('owner')
            ->orderByRaw('retired_at IS NOT NULL')
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->orderBy('name')
            ->get();

        return view('pages.admin.secret-references', [
            'references' => $references,
            'expiringCount' => $references->filter(
                fn (SecretReference $reference): bool => in_array(
                    $reference->status(),
                    [SecretStatus::ExpiringSoon, SecretStatus::Expired],
                    true,
                ),
            )->count(),
            'rotationDueCount' => $references->filter(
                fn (SecretReference $reference): bool => $reference->status() === SecretStatus::RotationDue,
            )->count(),
            'untrackedCount' => $references->filter(
                fn (SecretReference $reference): bool => $reference->status() === SecretStatus::Unknown,
            )->count(),
            'horizonDays' => SecretStatus::EXPIRY_HORIZON_DAYS,
        ]);
    }

    public function create(UserRegistry $registry): View
    {
        return view('pages.admin.secret-reference-form', $this->formData($registry, null));
    }

    public function store(StoreSecretReferenceRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::user();

        try {
            $reference = new SecretReference;
            $reference->fill($this->attributesFrom($request->validated()));
            $reference->forceFill([
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $reference->save();
        } catch (CredentialShapedValue $exception) {
            /*
             * The request already checks this, so reaching here means the
             * request and the model disagree - which is worth recording. The
             * refusal itself is audited without the value.
             */
            $this->audit->denied(
                action: 'security.secret_reference.created',
                module: 'Security',
                resourceType: 'secret_reference',
                reason: 'A credential-shaped value reached the model after passing the form request.',
            );

            return back()->withInput()->withErrors(['reference_identifier' => $exception->getMessage()]);
        }

        $this->audit->record(
            action: 'security.secret_reference.created',
            module: 'Security',
            resourceType: 'secret_reference',
            resourceId: $reference->getKey(),
            after: $this->summary($reference),
            reason: 'A new secret reference was recorded.',
        );

        return redirect()
            ->route('admin.security.secrets')
            ->with('status', 'Secret reference "'.$reference->name.'" saved. No credential value is stored.');
    }

    public function edit(UserRegistry $registry, SecretReference $secretReference): View
    {
        return view('pages.admin.secret-reference-form', $this->formData($registry, $secretReference));
    }

    public function update(UpdateSecretReferenceRequest $request, SecretReference $secretReference): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::user();

        $before = $this->summary($secretReference);

        try {
            $secretReference->fill($this->attributesFrom($request->validated()));
            $secretReference->forceFill(['updated_by_user_id' => $actor->getKey()]);
            $secretReference->save();
        } catch (CredentialShapedValue $exception) {
            $this->audit->denied(
                action: 'security.secret_reference.updated',
                module: 'Security',
                resourceType: 'secret_reference',
                resourceId: $secretReference->getKey(),
                reason: 'A credential-shaped value reached the model after passing the form request.',
            );

            return back()->withInput()->withErrors(['reference_identifier' => $exception->getMessage()]);
        }

        $this->audit->record(
            action: 'security.secret_reference.updated',
            module: 'Security',
            resourceType: 'secret_reference',
            resourceId: $secretReference->getKey(),
            before: $before,
            after: $this->summary($secretReference->refresh()),
            reason: 'A secret reference was changed.',
        );

        return redirect()
            ->route('admin.security.secrets')
            ->with('status', 'Secret reference "'.$secretReference->name.'" updated.');
    }

    /**
     * Take a reference out of use without losing it.
     */
    public function retire(SecretReference $secretReference): RedirectResponse
    {
        if ($secretReference->isRetired()) {
            return back()->with('status', 'That reference was already retired.');
        }

        $secretReference->forceFill([
            'retired_at' => Carbon::now()->utc(),
            'updated_by_user_id' => Auth::id(),
        ])->save();

        $this->audit->record(
            action: 'security.secret_reference.retired',
            module: 'Security',
            resourceType: 'secret_reference',
            resourceId: $secretReference->getKey(),
            after: ['status' => SecretStatus::Retired->value],
            reason: 'A secret reference was retired. The row is kept, because a credential that used to exist is part of the history.',
        );

        return redirect()
            ->route('admin.security.secrets')
            ->with('status', 'Secret reference "'.$secretReference->name.'" retired. The record is kept for the audit trail.');
    }

    /**
     * What the form needs, for both create and edit.
     *
     * @return array<string, mixed>
     */
    private function formData(UserRegistry $registry, ?SecretReference $reference): array
    {
        return [
            'reference' => $reference,
            'types' => SecretType::cases(),
            'providers' => SecretProvider::cases(),
            /* Scoped to this organisation, like every other person list. */
            'owners' => $registry->query()->orderBy('name')->get(['id', 'name', 'email']),
            'environments' => ['production', 'staging', 'development'],
        ];
    }

    /**
     * The validated attributes, as the model wants them.
     *
     * `owner_user_id` is passed through as given and constrained by the
     * database's foreign key rather than by a lookup here, so an id belonging
     * to another organisation fails at the constraint instead of being silently
     * accepted. The list the form offers is already scoped.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFrom(array $validated): array
    {
        return [
            'name' => trim((string) $validated['name']),
            'reference_type' => SecretType::from((string) $validated['reference_type']),
            'provider' => SecretProvider::from((string) $validated['provider']),
            'reference_identifier' => trim((string) $validated['reference_identifier']),
            'purpose' => trim((string) $validated['purpose']),
            'environment' => trim((string) $validated['environment']),
            'owner_user_id' => $validated['owner_user_id'] ?? null,
            'expires_on' => $validated['expires_on'] ?? null,
            'rotation_due_on' => $validated['rotation_due_on'] ?? null,
        ];
    }

    /**
     * The audit summary for a reference.
     *
     * `reference_identifier` IS included, because it is a pointer and the whole
     * point of the trail is to show that a pointer changed. It has already been
     * proved not to be credential-shaped by the model, and `Redaction` runs
     * over it again on the way into the trail.
     *
     * The key is `reference_type` rather than `secret_type` for a reason that
     * looks like pedantry and is not: `Redaction::summarise()` replaces the
     * value of any key containing "secret", so a key called `secret_type` would
     * record "[redacted] -> [redacted]" instead of "Client secret ->
     * Certificate". SEC-DEC-044.
     *
     * @return array<string, mixed>
     */
    private function summary(SecretReference $reference): array
    {
        return [
            'name' => $reference->name,
            'reference_type' => $reference->reference_type->value,
            'provider' => $reference->provider->value,
            'reference_identifier' => $reference->reference_identifier,
            'environment' => $reference->environment,
            'owner_user_id' => $reference->owner_user_id,
            'expires_on' => $reference->expires_on?->toDateString(),
            'rotation_due_on' => $reference->rotation_due_on?->toDateString(),
            'status' => $reference->status()->value,
        ];
    }
}
