<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkflowStatus;
use App\Support\Tenancy\BelongsToOrganisation;
use Database\Factories\FabricItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Fabric item SemantIQ manages in the customer's tenant, held by reference.
 *
 * Identifiers, type and last observed state. Never contents: the data stays in
 * the customer's OneLake in their approved geography, and the control plane
 * keeps only enough to find it again and to notice when it changes.
 *
 * No Microsoft call is made in this phase. Phase 02 populates this table.
 *
 * Requirement IDs: NFR-COMP-01, NFR-MNT-01. SRS sections 9, 17.
 *
 * @property string $item_id
 * @property string $workspace_id
 * @property string $type
 * @property string $display_name
 * @property string|null $environment
 * @property WorkflowStatus $status
 * @property Carbon|null $last_seen_at
 */
class FabricItem extends Model
{
    /** @use HasFactory<FabricItemFactory> */
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'item_id',
        'workspace_id',
        'type',
        'display_name',
        'environment',
        'definition_version',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->status ??= WorkflowStatus::default();
        });
    }

    /**
     * Whether SemantIQ has confirmed this item exists as recorded.
     *
     * An unconfirmed item is one SemantIQ believes it created but has not seen
     * since. That is a meaningfully different claim from a verified one, and the
     * readiness screens must not present the two as the same.
     */
    public function isConfirmed(): bool
    {
        return $this->last_seen_at !== null;
    }
}
