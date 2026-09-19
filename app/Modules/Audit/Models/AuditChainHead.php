<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EXACTLY ONE ROW, id = 1. The chain's serialisation point, and the stored
 * evidence start instant.
 *
 * started_at is written once by the migration and never updated. A STORED FACT
 * rather than MIN(occurred_at), because a computed start date moves when the
 * earliest row is removed - the screen would then report a later start and look
 * entirely consistent while concealing exactly what the chain exists to reveal.
 */
final class AuditChainHead extends Model
{
    public const ID = 1;

    protected $table = 'audit_chain_head';

    protected $guarded = [];

    protected $casts = [
        'sequence' => 'integer',
        'started_at' => 'datetime',
    ];
}
