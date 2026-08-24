<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Modules\Governance\Enums\ProfileStatus;
use App\Modules\Governance\Models\Concerns\IsVersionedProfile;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One version of the data sovereignty profile. Feature ADM-015.
 *
 * Where this organisation's data is stored, processed, processed by AI and
 * backed up, and whether anything crosses a border.
 *
 * BACKUP GEOGRAPHY IS A SEPARATE ANSWER from storage geography, because backups
 * routinely leave the country the server sits in. SEC-DEC-036 records that all
 * three questions were asked separately for this deployment and all three came
 * back Singapore. `crossesABorder()` below therefore checks backups too - a
 * profile that says Singapore storage and United States backups is a cross-geo
 * position however its switches are set.
 *
 * THE SEEDED FIRST VERSION IS A DRAFT and nothing here treats it otherwise.
 * SEC-DEC-068: a profile nobody approved is a guess with good provenance, and a
 * screen showing it as settled would be a false healthy applied to sovereignty.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $version
 * @property ProfileStatus $status
 * @property string|null $storage_geography
 * @property string|null $processing_geography
 * @property string|null $ai_processing_geography
 * @property string|null $backup_geography
 * @property list<string>|null $approved_geographies
 * @property string|null $external_replication
 * @property bool $cross_geo_storage
 * @property bool $cross_geo_processing
 * @property bool $cross_geo_ai
 * @property bool $cross_geo_conversation_history
 * @property string|null $source_note
 * @property string|null $evidence_reference
 * @property string|null $notes
 * @property Carbon|null $approved_at
 * @property Carbon|null $superseded_at
 */
class DataSovereigntyProfile extends Model
{
    use BelongsToOrganisation;
    use IsVersionedProfile;

    protected $fillable = [
        'storage_geography',
        'processing_geography',
        'ai_processing_geography',
        'backup_geography',
        'approved_geographies',
        'external_replication',
        'cross_geo_storage',
        'cross_geo_processing',
        'cross_geo_ai',
        'cross_geo_conversation_history',
        'source_note',
        'evidence_reference',
        'notes',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'version' => 'integer',
            'approved_geographies' => 'array',
            'cross_geo_storage' => 'boolean',
            'cross_geo_processing' => 'boolean',
            'cross_geo_ai' => 'boolean',
            'cross_geo_conversation_history' => 'boolean',
            'approved_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * The four geographies, keyed by the question each answers.
     *
     * One place, so a screen, the overview and a future export cannot each
     * enumerate three of the four and quietly omit backups.
     *
     * @return array<string, string|null>
     */
    public function geographies(): array
    {
        return [
            'Storage' => $this->storage_geography,
            'Processing' => $this->processing_geography,
            'AI processing' => $this->ai_processing_geography,
            'Backups' => $this->backup_geography,
        ];
    }

    /**
     * Whether any cross-geo switch is on, or the geographies disagree.
     *
     * Two ways data crosses a border and only one of them is a switch. A
     * profile naming Singapore storage and United States backups has crossed a
     * border whatever the switches say, so both are checked here rather than
     * the screens checking whichever occurred to whoever wrote them.
     */
    public function crossesABorder(): bool
    {
        if ($this->cross_geo_storage || $this->cross_geo_processing
            || $this->cross_geo_ai || $this->cross_geo_conversation_history) {
            return true;
        }

        if ($this->external_replication === 'cross_geography') {
            return true;
        }

        $named = array_values(array_filter(
            $this->geographies(),
            static fn (?string $value): bool => $value !== null
                && $value !== ''
                && $value !== 'not_determined',
        ));

        return count(array_unique($named)) > 1;
    }

    /**
     * What is still unanswered.
     *
     * Returns gaps rather than a boolean for the same reason ADM-014 does: a
     * screen that names what is missing is useful, and one that shows an
     * unexplained warning is not. "Not determined" counts as a gap - it is an
     * honest answer to record and it is not a decision.
     *
     * @return list<string>
     */
    public function gaps(): array
    {
        $gaps = [];

        foreach ($this->geographies() as $question => $value) {
            if ($value === null || $value === '' || $value === 'not_determined') {
                $gaps[] = $question.' geography has not been determined.';
            }
        }

        if (($this->external_replication ?? '') === '' || $this->external_replication === 'not_determined') {
            $gaps[] = 'Whether data is replicated outside its geography has not been determined.';
        }

        if (($this->evidence_reference ?? '') === '') {
            $gaps[] = 'No evidence reference has been recorded for these geographies.';
        }

        return $gaps;
    }
}
