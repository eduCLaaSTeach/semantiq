<?php

declare(strict_types=1);

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\ProfileStatus;
use App\Modules\Governance\Exceptions\GovernanceStorageNotInitialised;
use App\Modules\Governance\Models\DataSovereigntyProfile;
use App\Modules\Governance\Support\GovernanceStorage;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reading and changing the data sovereignty profile. Feature ADM-015.
 *
 * The same versioning contract as ADM-014 - see `DataProtectionProfiles` - plus
 * one thing that profile does not have: a SEED.
 *
 * THE SEED, decision D12 and SEC-DEC-068. `ensureDraft()` creates the first
 * draft from the confirmed production facts rather than from nothing:
 * Singapore storage, Singapore backups, no external replication, every cross-geo
 * switch off. Those are not guesses - SEC-DEC-036 records that all three were
 * asked separately and confirmed - and making an administrator retype a verified
 * fact invites a typo into the sovereignty record.
 *
 * BUT IT IS SEEDED AS A DRAFT AND NEVER AS APPROVED. A profile nobody approved
 * is a guess with good provenance. `source_note` carries where the values came
 * from so a reader can tell a confirmed fact from a typed-in one, the screen
 * states plainly that it is unapproved, and `inForce()` returns null until a
 * person approves it - so nothing downstream can mistake the seed for a
 * decision.
 *
 * THE SEED IS WRITTEN BY THIS SERVICE, NOT BY A SEEDER MIGRATION. A migration
 * that wrote rows could be rolled back into orphaning them, and re-running it
 * would duplicate them. Creating on first visit has neither problem and stamps
 * the row with the organisation actually in context.
 */
class SovereigntyProfiles
{
    public function __construct(
        private readonly GovernanceStorage $storage,
        private readonly AuditLogger $audit,
        private readonly OrganisationContext $organisations,
    ) {}

    public function inForce(): ?DataSovereigntyProfile
    {
        if (! $this->storage->sovereigntyIsReady()) {
            return null;
        }

        return DataSovereigntyProfile::query()
            ->approved()
            ->orderByDesc('version')
            ->first();
    }

    public function draft(): ?DataSovereigntyProfile
    {
        if (! $this->storage->sovereigntyIsReady()) {
            return null;
        }

        return DataSovereigntyProfile::query()
            ->draft()
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return Collection<int, DataSovereigntyProfile>
     */
    public function history(int $limit = 20)
    {
        if (! $this->storage->sovereigntyIsReady()) {
            return collect();
        }

        return DataSovereigntyProfile::query()
            ->with('approvedBy')
            ->orderByDesc('version')
            ->limit($limit)
            ->get();
    }

    /**
     * The draft, seeding the first one if this organisation has none.
     *
     * Called by the screen on a READ, which is why it is careful: it seeds only
     * when there is no draft AND no approved version AND no superseded version
     * at all. An organisation that approved a profile and has no draft open is
     * in a settled state, and quietly opening a draft under it would make the
     * screen look like somebody had started editing.
     *
     * Returns null rather than seeding when the table does not exist, so a
     * deployment window shows the migration banner instead of a 500.
     */
    public function ensureDraft(?User $actor = null): ?DataSovereigntyProfile
    {
        if (! $this->storage->sovereigntyIsReady()) {
            return null;
        }

        $existing = $this->draft();

        if ($existing !== null) {
            return $existing;
        }

        if (DataSovereigntyProfile::query()->exists()) {
            /* Something has been approved or superseded. Not a fresh install,
             * so there is nothing to seed - a new draft here is a deliberate
             * act and belongs to `beginRevision()`. */
            return null;
        }

        if ($actor === null || $this->organisations->currentId() === null) {
            /* No actor to attribute the seed to, or no organisation to stamp
             * it with. Writing an unattributed sovereignty record would look
             * like evidence and would not be. */
            return null;
        }

        return $this->seed($actor);
    }

    /**
     * Open a new draft from the approved version, deliberately.
     *
     * Distinct from `ensureDraft()` because the two are different acts: one is
     * a screen loading, the other is somebody starting a revision.
     */
    public function beginRevision(User $actor): DataSovereigntyProfile
    {
        if (! $this->storage->sovereigntyIsReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The data sovereignty profile');
        }

        $existing = $this->draft();

        if ($existing !== null) {
            return $existing;
        }

        return $this->newDraftFrom($this->inForce(), $actor, null);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function saveDraft(array $values, User $actor): DataSovereigntyProfile
    {
        if (! $this->storage->sovereigntyIsReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The data sovereignty profile');
        }

        $draft = $this->draft() ?? $this->newDraftFrom($this->inForce(), $actor, null);

        $before = $draft->wasRecentlyCreated ? null : $draft->only(array_keys($values));

        $draft->fill($values);
        $draft->updated_by_user_id = $actor->getKey();
        $draft->save();

        $this->audit->record(
            action: 'governance.sovereignty_profile.updated',
            module: 'Governance',
            resourceType: 'sovereignty_profile',
            resourceId: $draft->getKey(),
            before: $before,
            after: $draft->only(array_keys($values)) + ['version' => $draft->version],
        );

        return $draft;
    }

    /**
     * Approve the draft.
     *
     * A reason is required, and it matters more here than on ADM-014: approving
     * a sovereignty profile that permits cross-geo processing is exactly the
     * kind of change ROLE_MODEL section 6 names as high-impact.
     */
    public function approve(User $actor, string $reason): DataSovereigntyProfile
    {
        if (! $this->storage->sovereigntyIsReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The data sovereignty profile');
        }

        $draft = $this->draft();

        if ($draft === null) {
            throw new RuntimeException('There is no draft to approve.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Approving a sovereignty profile requires a stated reason.');
        }

        return DB::transaction(function () use ($draft, $actor, $reason): DataSovereigntyProfile {
            $previous = $this->inForce();

            $draft->forceFill([
                'status' => ProfileStatus::Approved,
                'approved_at' => now()->utc(),
                'approved_by_user_id' => $actor->getKey(),
            ])->save();

            if ($previous !== null) {
                $previous->forceFill([
                    'status' => ProfileStatus::Superseded,
                    'superseded_at' => now()->utc(),
                    'superseded_by_id' => $draft->getKey(),
                ])->save();

                $this->audit->record(
                    action: 'governance.sovereignty_profile.superseded',
                    module: 'Governance',
                    resourceType: 'sovereignty_profile',
                    resourceId: $previous->getKey(),
                    after: ['version' => $previous->version, 'replaced_by_version' => $draft->version],
                    reason: $reason,
                );
            }

            $this->audit->record(
                action: 'governance.sovereignty_profile.approved',
                module: 'Governance',
                resourceType: 'sovereignty_profile',
                resourceId: $draft->getKey(),
                after: [
                    'version' => $draft->version,
                    'storage_geography' => $draft->storage_geography,
                    'processing_geography' => $draft->processing_geography,
                    'ai_processing_geography' => $draft->ai_processing_geography,
                    'backup_geography' => $draft->backup_geography,
                    'external_replication' => $draft->external_replication,
                    'crosses_a_border' => $draft->crossesABorder(),
                ],
                reason: $reason,
            );

            return $draft;
        });
    }

    /**
     * The seeded first draft. D12.
     */
    private function seed(User $actor): DataSovereigntyProfile
    {
        /** @var array<string, mixed> $seed */
        $seed = (array) config('governance.sovereignty_seed', []);

        $draft = $this->newDraftFrom(null, $actor, $seed);

        $this->audit->record(
            action: 'governance.sovereignty_profile.seeded',
            module: 'Governance',
            resourceType: 'sovereignty_profile',
            resourceId: $draft->getKey(),
            after: [
                'version' => $draft->version,
                'storage_geography' => $draft->storage_geography,
                'backup_geography' => $draft->backup_geography,
                'external_replication' => $draft->external_replication,
                /* Recorded explicitly so the trail cannot be read as an
                 * approval. It is a draft and it says so. */
                'status' => ProfileStatus::Draft->value,
            ],
        );

        return $draft;
    }

    /**
     * A new draft, numbered after the highest version that exists.
     *
     * @param  array<string, mixed>|null  $seed
     */
    private function newDraftFrom(
        ?DataSovereigntyProfile $source,
        User $actor,
        ?array $seed,
    ): DataSovereigntyProfile {
        $highest = (int) DataSovereigntyProfile::query()->max('version');

        $draft = new DataSovereigntyProfile;

        $draft->forceFill([
            'version' => $highest + 1,
            'status' => ProfileStatus::Draft,
            'storage_geography' => $source?->storage_geography ?? ($seed['storage_geography'] ?? null),
            'processing_geography' => $source?->processing_geography ?? ($seed['processing_geography'] ?? null),
            'ai_processing_geography' => $source?->ai_processing_geography ?? ($seed['ai_processing_geography'] ?? null),
            'backup_geography' => $source?->backup_geography ?? ($seed['backup_geography'] ?? null),
            'approved_geographies' => $source?->approved_geographies,
            'external_replication' => $source?->external_replication ?? ($seed['external_replication'] ?? null),
            /* Cross-geo defaults to OFF whether copied or seeded. CLAUDE.md. */
            'cross_geo_storage' => $source?->cross_geo_storage ?? (bool) ($seed['cross_geo_storage'] ?? false),
            'cross_geo_processing' => $source?->cross_geo_processing ?? (bool) ($seed['cross_geo_processing'] ?? false),
            'cross_geo_ai' => $source?->cross_geo_ai ?? (bool) ($seed['cross_geo_ai'] ?? false),
            'cross_geo_conversation_history' => $source?->cross_geo_conversation_history
                ?? (bool) ($seed['cross_geo_conversation_history'] ?? false),
            'source_note' => $source?->source_note ?? ($seed['source_note'] ?? null),
            'evidence_reference' => $source?->evidence_reference,
            'notes' => $source?->notes,
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ]);

        $draft->save();

        return $draft;
    }
}
