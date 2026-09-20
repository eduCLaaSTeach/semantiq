<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The single local setup administrator row.
 *
 * A MODEL, NOT A PRINCIPAL. BootstrapPrincipal is the value that reaches a
 * request; this is only how the row is read and written. Keeping them apart is
 * what stops an Eloquent model carrying a password hash being handed to a view,
 * a serialiser or the access model.
 *
 * `password_hash` IS HIDDEN from array and JSON forms. A model that reaches a
 * response by accident should carry nothing worth having.
 *
 * isClosed() IS THE STATE THE FIRST DRAFT NEVER WROTE. The transition to a
 * permanent System Administrator sets disabled_at AND replaces the hash with an
 * unusable value, in one transaction, so a deployment cannot be talked back
 * into accepting the original password by deactivating every administrator.
 *
 * @property int $id
 * @property string $email
 * @property string $password_hash
 * @property Carbon|null $disabled_at
 * @property Carbon|null $last_signed_in_at
 */
final class BootstrapAdministrator extends Model
{
    public const SINGLETON = 'bootstrap';

    /**
     * What the password hash is replaced with when bootstrap closes.
     *
     * NOT an empty string and not a null. A column left empty invites a future
     * "if the hash is blank, let them set one" recovery path - the backdoor
     * rebuilt by kindness. This is a value no Hash::check can return true for,
     * and it says what it is.
     */
    public const UNUSABLE_HASH = 'closed:no-password-can-match-this-value';

    protected $table = 'bootstrap_administrators';

    protected $fillable = [
        'singleton',
        'email',
        'password_hash',
        'disabled_at',
        'last_signed_in_at',
    ];

    /** @var list<string> */
    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'disabled_at' => 'datetime',
            'last_signed_in_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->where('singleton', self::SINGLETON)->first();
    }

    public function isClosed(): bool
    {
        return $this->disabled_at !== null;
    }
}
