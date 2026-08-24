<?php

declare(strict_types=1);

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\ProfileStatus;
use App\Modules\Governance\Exceptions\GovernanceStorageNotInitialised;
use App\Modules\Governance\Models\DataProtectionProfile;
use App\Modules\Governance\Support\GovernanceStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reading and changing the data protection profile. Feature ADM-014.
 *
 * THE VERSIONING RULE, decision D4 and SEC-DEC-065, expressed as three methods:
 *
 *   inForce()    the approved version, or null. Null is a real and common
 *                answer - it is what every fresh install starts at - and it
 *                means Not Configured, never "the defaults are fine".
 *   draft()      the editable version, or null.
 *   saveDraft()  writes to the draft, creating one from the approved version's
 *                values if none exists yet.
 *   approve()    approves the draft and supersedes what it replaces, in one
 *                transaction.
 *
 * WHY A NEW DRAFT COPIES THE APPROVED VERSION. Somebody changing one field
 * should not have to retype the other six, and a draft that started empty would
 * make a small correction look like a wholesale rewrite in the version history.
 *
 * THE STORAGE GUARD IS THE FIRST THING EVERY METHOD DOES. SEC-DEC-072. A read
 * before the migration has run returns null, which the screen reports as Not
 * Configured with the migration banner above it - true, because with no table
 * there can be no approved profile. A WRITE throws, because accepting a change
 * and discarding it would tell an administrator their privacy position had
 * changed when nothing had.
 *
 * SEPARATION OF DUTIES, decision D13 and SEC-DEC-067. `approve()` requires
 * `admin.data_protection.approve`, which sits at System Administrator, while
 * `saveDraft()` requires `admin.data_protection.manage` at Administrator. The
 * route middleware checks the same permissions; this check exists so a console
 * command or a future API meets it too.
 */
class DataProtectionProfiles
{
    public function __construct(
        private readonly GovernanceStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The approved version, or null when nothing has been approved.
     */
    public function inForce(): ?DataProtectionProfile
    {
        if (! $this->storage->dataProtectionIsReady()) {
            return null;
        }

        return DataProtectionProfile::query()
            ->approved()
            ->orderByDesc('version')
            ->first();
    }

    /**
     * The editable version, or null when there is no draft.
     */
    public function draft(): ?DataProtectionProfile
    {
        if (! $this->storage->dataProtectionIsReady()) {
            return null;
        }

        return DataProtectionProfile::query()
            ->draft()
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Every version, newest first, for the history panel.
     *
     * @return Collection<int, DataProtectionProfile>
     */
    public function history(int $limit = 20)
    {
        if (! $this->storage->dataProtectionIsReady()) {
            return collect();
        }

        return DataProtectionProfile::query()
            ->with('approvedBy')
            ->orderByDesc('version')
            ->limit($limit)
            ->get();
    }

    /**
     * Write to the draft, creating one if there is none.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveDraft(array $values, User $actor): DataProtectionProfile
    {
        if (! $this->storage->dataProtectionIsReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The data protection profile');
        }

        $draft = $this->draft();
        $before = $draft?->only(array_keys($values));

        if ($draft === null) {
            $draft = $this->newDraft($actor);
            /* A brand new draft has no meaningful before state. Passing the
             * seeded values as `before` would record a change nobody made. */
            $before = null;
        }

        $draft->fill($values);
        $draft->updated_by_user_id = $actor->getKey();
        $draft->save();

        $this->audit->record(
            action: 'governance.data_protection_profile.updated',
            module: 'Governance',
            resourceType: 'data_protection_profile',
            resourceId: $draft->getKey(),
            before: $before,
            after: $draft->only(array_keys($values)) + ['version' => $draft->version],
        );

        return $draft;
    }

    /**
     * Approve the draft. It becomes immutable and supersedes what it replaces.
     *
     * A reason is required. This is a high-risk change under ADM-007's risk
     * model, and a privacy position approved with no stated reason is an
     * approval nobody can review later.
     */
    public function approve(User $actor, string $reason): DataProtectionProfile
    {
        if (! $this->storage->dataProtectionIsReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The data protection profile');
        }

        $draft = $this->draft();

        if ($draft === null) {
            throw new RuntimeException('There is no draft to approve.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Approving a data protection profile requires a stated reason.');
        }

        return DB::transaction(function () use ($draft, $actor, $reason): DataProtectionProfile {
            $previous = $this->inForce();

            $draft->forceFill([
                'status' => ProfileStatus::Approved,
                'approved_at' => now()->utc(),
                'approved_by_user_id' => $actor->getKey(),
            ])->save();

            if ($previous !== null) {
                /*
                 * The one update an approved version still permits, and the
                 * model hook allows exactly these columns. See
                 * IsVersionedProfile.
                 */
                $previous->forceFill([
                    'status' => ProfileStatus::Superseded,
                    'superseded_at' => now()->utc(),
                    'superseded_by_id' => $draft->getKey(),
                ])->save();

                $this->audit->record(
                    action: 'governance.data_protection_profile.superseded',
                    module: 'Governance',
                    resourceType: 'data_protection_profile',
                    resourceId: $previous->getKey(),
                    after: ['version' => $previous->version, 'replaced_by_version' => $draft->version],
                    reason: $reason,
                );
            }

            $this->audit->record(
                action: 'governance.data_protection_profile.approved',
                module: 'Governance',
                resourceType: 'data_protection_profile',
                resourceId: $draft->getKey(),
                after: [
                    'version' => $draft->version,
                    'applicable_regime' => $draft->applicable_regime,
                    'privacy_officer_designated' => $draft->privacy_officer_designated,
                    'breach_notification_due_days' => $draft->breach_notification_due_days,
                ],
                reason: $reason,
            );

            return $draft;
        });
    }

    /**
     * A fresh draft, numbered after the highest version that exists.
     *
     * Copies the approved version's values when there is one, and the
     * catalogue's defaults when there is not. The catalogue supplies the
     * breach-notification figure, which was accepted for implementation, and
     * deliberately supplies NO basis text - that is compliance-owned, and a
     * plausible sentence written here would be a compliance claim nobody made.
     */
    private function newDraft(User $actor): DataProtectionProfile
    {
        $approved = $this->inForce();

        $highest = (int) DataProtectionProfile::query()->max('version');

        $draft = new DataProtectionProfile;

        $draft->forceFill([
            'version' => $highest + 1,
            'status' => ProfileStatus::Draft,
            'applicable_regime' => $approved?->applicable_regime
                ?? config('governance.regime.default'),
            'regime_basis' => $approved?->regime_basis
                ?? config('governance.regime.basis_default'),
            'privacy_officer_designated' => $approved?->privacy_officer_designated ?? false,
            'breach_notification_due_days' => $approved?->breach_notification_due_days
                ?? config('governance.breach_notification.due_days_default'),
            'breach_notification_basis' => $approved?->breach_notification_basis
                ?? config('governance.breach_notification.basis_default'),
            'notes' => $approved?->notes,
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ]);

        $draft->save();

        $this->audit->record(
            action: 'governance.data_protection_profile.created',
            module: 'Governance',
            resourceType: 'data_protection_profile',
            resourceId: $draft->getKey(),
            after: ['version' => $draft->version, 'copied_from_version' => $approved?->version],
        );

        return $draft;
    }
}
