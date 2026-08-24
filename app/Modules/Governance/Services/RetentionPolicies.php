<?php

declare(strict_types=1);

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\RetentionStatus;
use App\Modules\Governance\Exceptions\GovernanceStorageNotInitialised;
use App\Modules\Governance\Models\PersonalDataCategory;
use App\Modules\Governance\Models\RetentionPolicy;
use App\Modules\Governance\Support\GovernanceStorage;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Retention policy per personal data category. Feature PDPA-03.
 *
 * **THIS SERVICE HAS NO DELETE METHOD, AND THAT IS THE DESIGN.** SEC-DEC-038.
 * Gate 4 writes retention policy down and executes none of it. A retention
 * sweep is the single most destructive feature this application could have, and
 * it is not being built by the batch that first records the periods. When it is
 * built it will have to drop the `audit_events` triggers, delete, and recreate
 * them, as a deliberate and separately approved operation.
 *
 * A ROW PER CATEGORY, CREATED ON DEMAND. There is no seeder. The retention
 * screen lists every ACTIVE personal data category and pairs it with its policy
 * if one exists, so a category added later appears immediately with nothing
 * configured, rather than being silently absent because no seeder ran for it.
 *
 * WHAT "APPROVED" MEANS HERE, and what it does not. Approving records that a
 * human agreed the period. It does not switch anything on, and the screens say
 * so. That distinction is the difference between a compliance record and a
 * compliance theatre.
 */
class RetentionPolicies
{
    public function __construct(
        private readonly GovernanceStorage $storage,
        private readonly AuditLogger $audit,
        private readonly OrganisationContext $organisations,
        private readonly PersonalDataCatalogue $catalogue,
    ) {}

    /**
     * Every active category paired with its policy, whether one exists or not.
     *
     * Returns categories, not policies, on purpose. A category with no policy is
     * the state that matters most - it is personal data nobody has decided a
     * retention period for - and listing only policies would hide exactly that.
     *
     * @return Collection<int, array{category: PersonalDataCategory, policy: RetentionPolicy|null}>
     */
    public function forEveryCategory(): Collection
    {
        if (! $this->storage->retentionIsReady() || ! $this->storage->categoriesAreReady()) {
            return collect();
        }

        $policies = RetentionPolicy::query()->get()->keyBy('personal_data_category_id');

        return $this->catalogue->active()->map(
            static fn (PersonalDataCategory $category): array => [
                'category' => $category,
                'policy' => $policies->get($category->getKey()),
            ]
        )->values();
    }

    /**
     * How many active categories have no period set.
     *
     * The one number that says whether retention has been thought about. Shown
     * on the screen rather than a percentage, because "4 of 7 categories have no
     * retention period" is actionable and "57 percent complete" is not.
     */
    public function categoriesWithoutAPeriod(): int
    {
        return $this->forEveryCategory()->filter(
            static fn (array $row): bool => $row['policy'] === null || ! $row['policy']->hasPeriod()
        )->count();
    }

    /**
     * Policies whose review date has passed.
     *
     * Derived on read from today's date. No job marks a review overdue.
     *
     * @return Collection<int, RetentionPolicy>
     */
    public function overdueReviews(): Collection
    {
        if (! $this->storage->retentionIsReady()) {
            return collect();
        }

        return RetentionPolicy::query()
            ->with('category')
            ->get()
            ->filter(static fn (RetentionPolicy $p): bool => $p->reviewIsOverdue())
            ->values();
    }

    public function findForCategory(int $categoryId): ?RetentionPolicy
    {
        if (! $this->storage->retentionIsReady()) {
            return null;
        }

        return RetentionPolicy::query()
            ->where('personal_data_category_id', $categoryId)
            ->first();
    }

    /**
     * Write the policy for one category, creating the row if it is the first time.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(PersonalDataCategory $category, array $values, User $actor): RetentionPolicy
    {
        if (! $this->storage->retentionIsReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The retention policy');
        }

        if ($this->organisations->currentId() === null) {
            /* An unattributed retention policy would look like a decision and
             * would belong to nobody. */
            throw new RuntimeException('No organisation is in context, so a retention policy cannot be written.');
        }

        $policy = $this->findForCategory((int) $category->getKey());
        $before = $policy === null ? null : $this->summarise($policy);

        if ($policy === null) {
            $policy = new RetentionPolicy;
            $policy->forceFill([
                'personal_data_category_id' => $category->getKey(),
                'status' => RetentionStatus::Draft,
                'created_by_user_id' => $actor->getKey(),
            ]);
        }

        /*
         * Editing an approved policy returns it to DRAFT. A period that changed
         * after somebody approved it is not the thing they approved, and leaving
         * the approved badge in place would attribute a decision to a person who
         * did not make it.
         */
        if ($policy->exists && $policy->status === RetentionStatus::Approved) {
            $policy->forceFill([
                'status' => RetentionStatus::Draft,
                'approved_at' => null,
                'approved_by_user_id' => null,
            ]);
        }

        $policy->fill($values);
        $policy->updated_by_user_id = $actor->getKey();
        $policy->save();

        $this->audit->record(
            action: 'governance.retention_policy.updated',
            module: 'Governance',
            resourceType: 'retention_policy',
            resourceId: $policy->getKey(),
            before: $before,
            after: $this->summarise($policy->refresh()),
        );

        return $policy;
    }

    /**
     * Record that a human agreed this period.
     *
     * Switches nothing on. See the class note.
     */
    public function approve(RetentionPolicy $policy, User $actor, string $reason): RetentionPolicy
    {
        if (! $this->storage->retentionIsReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The retention policy');
        }

        if (! $policy->hasPeriod()) {
            /*
             * Refused rather than allowed with a warning. Approving a policy
             * with no period would produce an approved row that says nothing,
             * and an approved-looking row that says nothing is worse than a
             * draft that plainly does not.
             */
            throw new RuntimeException(
                'This policy has no retention period, so there is nothing to approve. '
                .'Set a period and a basis first.'
            );
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Approving a retention policy requires a stated reason.');
        }

        $policy->forceFill([
            'status' => RetentionStatus::Approved,
            'approved_at' => now()->utc(),
            'approved_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'governance.retention_policy.approved',
            module: 'Governance',
            resourceType: 'retention_policy',
            resourceId: $policy->getKey(),
            after: $this->summarise($policy->refresh()),
            reason: $reason,
        );

        return $policy;
    }

    /**
     * A summary for the audit trail.
     *
     * Every key checked against `Redaction::isSensitiveKey()`. None matches -
     * `lawful_basis`, `disposal_action` and `start_event` are all clean. A field
     * named `retention_key` would not have been. SEC-DEC-044.
     *
     * @return array<string, mixed>
     */
    private function summarise(RetentionPolicy $policy): array
    {
        return [
            'category' => $policy->category?->code,
            'retention_months' => $policy->retention_months,
            'lawful_basis' => $policy->lawful_basis,
            'start_event' => $policy->start_event,
            'disposal_action' => $policy->disposal_action,
            'owner' => $policy->owner,
            'next_review_on' => $policy->next_review_on?->toDateString(),
            'state' => $policy->status->value,
        ];
    }
}
