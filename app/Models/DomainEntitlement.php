<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessDomain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's access to one business domain.
 *
 * The second access dimension. A platform role says what somebody may do; this
 * says which business information they may do it to. Neither implies the other.
 *
 * @property BusinessDomain $domain
 * @property array<string, mixed>|null $scope
 */
class DomainEntitlement extends Model
{
    protected $fillable = ['user_id', 'domain', 'scope', 'granted_by_user_id'];

    protected function casts(): array
    {
        return [
            'domain' => BusinessDomain::class,
            'scope' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who granted it. Kept because a permission change is an auditable event,
     * and "who decided this" is the first question asked at an access review.
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
