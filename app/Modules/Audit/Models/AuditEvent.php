<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Modules\Security\Catalogue\AuditCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One durable piece of evidence.
 *
 * NO UPDATE AND NO DELETE PATH EXISTS. There is no route, controller action or
 * service method that changes a row once written, and AuditImmutabilityTest
 * asserts the route set as an EQUALITY so a fifth verb under /console/audit
 * fails the build.
 *
 * WHAT THAT DOES NOT CLAIM. Somebody with database or SSH access can still
 * delete rows, and no application can prevent it on shared hosting. The chain
 * makes it DETECTABLE - AuditChainVerifier - and the screen says so in those
 * words rather than implying more.
 */
final class AuditEvent extends Model
{
    protected $table = 'audit_events';

    protected $guarded = [];

    /**
     * occurred_at AND context_expires_at ARE DELIBERATELY NOT CAST.
     *
     * They are exact strings, because a value the database rewrites on the way
     * in cannot be hashed - a date cast truncates microseconds and the chain
     * then reads as broken on every untouched row. Presentation parses them;
     * nothing else needs to.
     */
    protected $casts = [
        'category' => AuditCategory::class,
        'sequence' => 'integer',
    ];

    /** @param  Builder<self>  $query */
    public function scopeInCategory(Builder $query, AuditCategory $category): void
    {
        $query->where('category', $category->value);
    }
}
