<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Enums\DisclosureTreatment;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One collected item in an assembled response. Feature PDPA-01.
 *
 * These rows ARE the response. There is no document and no export anywhere in
 * gate 4 (decision D9); the response is reviewed on screen and delivered
 * outside SemantIQ, with `privacy_requests.evidence_reference` recording how.
 *
 * `summary` IS ALREADY RENDERED, and for band C it was rendered by a function
 * that never received the other person's identity. That is deliberate: a
 * template mistake cannot leak what was never passed in.
 *
 * `detail` IS POPULATED ONLY FOR `include`. A `describe` row with a payload
 * would be a `describe` row that discloses, and the distinction would stop
 * meaning anything.
 */
class PrivacyRequestRecord extends Model
{
    use BelongsToOrganisation;

    /**
     * `treatment` is absent: changing it is a disclosure decision, and
     * `PrivacySubjectAssembler` is the only thing permitted to make one -
     * widening requires a second approver, which a mass assignment cannot
     * express.
     */
    protected $fillable = [
        'privacy_request_id',
        'band',
        'source_table',
        'collector',
        'summary',
        'detail',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'band' => DisclosureBand::class,
            'treatment' => DisclosureTreatment::class,
            'detail' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Whether this row actually discloses anything to the subject.
     *
     * An `exclude` row is retained precisely BECAUSE it discloses nothing: it
     * is the evidence that the table was considered and deliberately withheld,
     * which is what makes the coverage claim checkable after the fact.
     */
    public function discloses(): bool
    {
        return $this->treatment !== DisclosureTreatment::Exclude;
    }

    public function wasWidened(): bool
    {
        return $this->reviewer_action === 'widened';
    }

    /**
     * What a reviewer needs to see about this row's basis, in one line.
     */
    public function basis(): string
    {
        return $this->band->label().' - '.$this->treatment->label();
    }

    public function scopeDisclosed(Builder $query): Builder
    {
        return $query->where('treatment', '!=', DisclosureTreatment::Exclude->value);
    }

    public function scopeInBand(Builder $query, DisclosureBand $band): Builder
    {
        return $query->where('band', $band->value);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class, 'privacy_request_id');
    }
}
