<?php

declare(strict_types=1);

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\DataClassification;
use App\Modules\Governance\Exceptions\GovernanceStorageNotInitialised;
use App\Modules\Governance\Models\PersonalDataCategory;
use App\Modules\Governance\Support\GovernanceStorage;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Support\Collection;

/**
 * The personal data category register. Feature ADM-014, the Personal / Sensitive
 * Data screen.
 *
 * WHAT THIS REGISTER IS FOR. PDPA-01 has to answer "what personal data do you
 * hold about me" in R1.4c, and it answers from these categories. That is why
 * `source_tables` is part of a category rather than a comment: the R1.4c
 * coverage test walks the live schema and fails when a table is claimed by no
 * category and named in no exclusion list, so the register cannot silently go
 * stale as gates 5 to 7 add tables.
 *
 * WHY THE CATALOGUE IS SEEDED FROM A SCHEMA SCAN, not from a template. DEC-002
 * named five tables holding personal data. A re-scan of the live 23-table schema
 * found it in 19. The seven categories in `config/governance.php` come from that
 * scan, which is why they name tables an off-the-shelf privacy template never
 * would - `audit_events`, `access_review_items`, `password_reset_tokens`.
 *
 * SEEDED ON FIRST READ, BY THIS SERVICE, NOT BY A MIGRATION. Same reasoning as
 * the sovereignty seed: a seeder migration can be rolled back into orphaning its
 * rows and re-run into duplicating them, and it cannot know which organisation
 * is in context.
 *
 * A CATEGORY IS RETIRED, NEVER DELETED. It is part of the record of how data was
 * treated, and deleting it removes the explanation without removing the data.
 * There is no delete path here at all - SEC-DEC-038 holds across gate 4.
 */
class PersonalDataCatalogue
{
    public function __construct(
        private readonly GovernanceStorage $storage,
        private readonly AuditLogger $audit,
        private readonly OrganisationContext $organisations,
    ) {}

    /**
     * Every category, seeding the defaults on a fresh install.
     *
     * @return Collection<int, PersonalDataCategory>
     */
    public function all(?User $actor = null): Collection
    {
        if (! $this->storage->categoriesAreReady()) {
            return collect();
        }

        $existing = PersonalDataCategory::query()->orderBy('name')->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        if ($actor === null || $this->organisations->currentId() === null) {
            /* Nothing to attribute the seed to, or nowhere to stamp it. An
             * unattributed register row would look like a decision and would
             * not be one. */
            return $existing;
        }

        $this->seed($actor);

        return PersonalDataCategory::query()->orderBy('name')->get();
    }

    /**
     * Only the categories currently in use.
     *
     * @return Collection<int, PersonalDataCategory>
     */
    public function active(): Collection
    {
        if (! $this->storage->categoriesAreReady()) {
            return collect();
        }

        return PersonalDataCategory::query()->active()->orderBy('name')->get();
    }

    public function find(int $id): ?PersonalDataCategory
    {
        if (! $this->storage->categoriesAreReady()) {
            return null;
        }

        /*
         * The organisation scope is global on this model, so an id belonging to
         * another organisation simply does not resolve. Returning null rather
         * than 403 for the same reason SEC-DEC-034 gives: a 403 would confirm
         * the row exists.
         */
        return PersonalDataCategory::query()->find($id);
    }

    /**
     * Change a category's description, classification or coverage.
     *
     * `code` is not among the changeable fields. It is the identifier the R1.4c
     * collector resolves against, so renaming one would silently break the link
     * between a category and the data it describes.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(PersonalDataCategory $category, array $values, User $actor): PersonalDataCategory
    {
        if (! $this->storage->categoriesAreReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The personal data category');
        }

        $before = $this->summarise($category);

        $category->fill($values);
        $category->updated_by_user_id = $actor->getKey();
        $category->save();

        $this->audit->record(
            action: 'governance.personal_data_category.updated',
            module: 'Governance',
            resourceType: 'personal_data_category',
            resourceId: $category->getKey(),
            before: $before,
            after: $this->summarise($category->refresh()),
        );

        return $category;
    }

    /**
     * The tables every active category between them claims.
     *
     * The input to the R1.4c coverage test, and the reason it can be written at
     * all. Returned sorted and unique so a comparison against the live schema is
     * a set operation rather than a nested loop.
     *
     * @return list<string>
     */
    public function claimedTables(): array
    {
        $claimed = $this->active()
            ->flatMap(static fn (PersonalDataCategory $c): array => $c->tables())
            ->unique()
            ->values()
            ->all();

        sort($claimed);

        return $claimed;
    }

    /**
     * Write the catalogue defaults for this organisation.
     */
    private function seed(User $actor): void
    {
        /** @var list<array<string, mixed>> $defaults */
        $defaults = (array) config('governance.personal_data_categories', []);

        foreach ($defaults as $definition) {
            $classification = $definition['classification'] ?? DataClassification::Internal;

            $category = new PersonalDataCategory;

            $category->forceFill([
                'code' => (string) $definition['code'],
                'name' => (string) $definition['name'],
                'description' => (string) $definition['description'],
                'classification' => $classification instanceof DataClassification
                    ? $classification->value
                    : (string) $classification,
                'contains_sensitive' => (bool) ($definition['contains_sensitive'] ?? false),
                'source_tables' => array_values((array) ($definition['tables'] ?? [])),
                'status' => 'active',
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            $category->save();
        }

        $this->audit->record(
            action: 'governance.personal_data_category.seeded',
            module: 'Governance',
            resourceType: 'personal_data_category',
            after: ['category_count' => count($defaults)],
        );
    }

    /**
     * A summary for the audit trail.
     *
     * Every key here was checked against `Redaction::isSensitiveKey()`.
     * `classification` and `source_tables` both survive it; a field named
     * `certification` or `subject_key` would not, which is why neither exists.
     * SEC-DEC-044.
     *
     * @return array<string, mixed>
     */
    private function summarise(PersonalDataCategory $category): array
    {
        return [
            'code' => $category->code,
            'name' => $category->name,
            'classification' => $category->classification->value,
            'contains_sensitive' => $category->contains_sensitive,
            'source_tables' => implode(', ', $category->tables()),
            'status' => $category->status,
        ];
    }
}
