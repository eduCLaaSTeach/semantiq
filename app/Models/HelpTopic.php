<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HelpTopicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A help centre topic, structured to the SRS section 15.1 template.
 *
 * Deliberately NOT organisation-scoped. Help content is product documentation
 * and is identical for every customer; see the migration for why scoping it
 * would be actively harmful rather than merely unnecessary. Nothing customer
 * specific is stored here, so there is no boundary to enforce.
 *
 * `isStale()` carries the CLAUDE.md Microsoft freshness rule into the data. A
 * topic describing a Microsoft portal path that has not been reviewed for a year
 * is a liability: following stale steps in someone else's tenant wastes an
 * administrator's afternoon and costs the product their trust.
 *
 * Requirement IDs: NFR-SUP-01, NFR-MNT-01. SRS section 15.1.
 *
 * @property string $topic_id
 * @property string $title
 * @property string|null $summary
 * @property array<int, array{label: string, token: string}>|null $values_to_copy
 * @property Carbon|null $last_reviewed_at
 * @property string $status
 */
class HelpTopic extends Model
{
    /** @use HasFactory<HelpTopicFactory> */
    use HasFactory;

    /**
     * How long a Microsoft-referencing topic stays trustworthy without a review.
     *
     * A year, because Microsoft portal navigation and Fabric tenant settings
     * change often enough that anything older should be re-read before it is
     * handed to a customer administrator.
     */
    public const REVIEW_INTERVAL_DAYS = 365;

    protected $fillable = [
        'topic_id',
        'title',
        'summary',
        'why_required',
        'who_can_do_it',
        'prerequisites',
        'where_to_go',
        'steps',
        'values_to_copy',
        'security_note',
        'expected_result',
        'verify_in_semantiq',
        'troubleshooting',
        'microsoft_reference',
        'last_reviewed_at',
        'product_version',
        'content_version',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'values_to_copy' => 'array',
            'last_reviewed_at' => 'date',
        ];
    }

    /**
     * Topics a reader may see.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Whether the Microsoft reference is old enough to need re-reading.
     *
     * A topic that cites Microsoft and has never been reviewed counts as stale.
     * The absence of a review date is not evidence of freshness.
     */
    public function isStale(): bool
    {
        if ($this->microsoft_reference === null) {
            return false;
        }

        return $this->last_reviewed_at === null
            || $this->last_reviewed_at->diffInDays(now()) > self::REVIEW_INTERVAL_DAYS;
    }
}
