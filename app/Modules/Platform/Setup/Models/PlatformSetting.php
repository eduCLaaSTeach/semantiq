<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The one row of typed platform state.
 *
 * NOT a settings key/value store. Every column is named and typed, so a value
 * cannot arrive unvalidated by being spelled into a `key` column.
 *
 * current() is deliberately a plain query rather than a cached singleton.
 * identity_source decides which credential authority the deployment reads, and
 * identity_config_revision decides whether a stored health result is still
 * readable - caching either would reintroduce, one layer up, exactly the
 * staleness this row exists to remove.
 *
 * @property int $id
 * @property string $singleton
 * @property string $identity_source
 * @property int $identity_config_revision
 * @property Carbon|null $identity_imported_at
 * @property Carbon|null $identity_verified_at
 * @property Carbon|null $identity_committed_at
 */
final class PlatformSetting extends Model
{
    public const SINGLETON = 'platform';

    public const SOURCE_ENV = 'env';

    public const SOURCE_STORE = 'store';

    protected $table = 'platform_settings';

    protected $fillable = [
        'singleton',
        'identity_source',
        'identity_config_revision',
        'identity_imported_at',
        'identity_verified_at',
        'identity_committed_at',
    ];

    protected function casts(): array
    {
        return [
            'identity_config_revision' => 'integer',
            'identity_imported_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'identity_committed_at' => 'datetime',
        ];
    }

    /**
     * The singleton row, for READING. It never writes.
     *
     * An earlier draft of this method used firstOrCreate, which turned every
     * identity resolution - including the one behind the unauthenticated entry
     * page - into a write. A GET that mutates is wrong on its own terms, and it
     * would fail outright against a read-only replica.
     *
     * An ABSENT ROW RETURNS AN UNSAVED DEFAULT, not null, so every caller gets
     * the same shape and none has to decide what missing means. `env` is the
     * correct default rather than a convenient one: the only thing that can set
     * `store` is a successful cutover, and a deployment with no row has not had
     * one.
     */
    public static function current(): self
    {
        return self::query()->where('singleton', self::SINGLETON)->first()
            ?? self::unsavedDefault();
    }

    /**
     * The defaults, WITHOUT TOUCHING THE DATABASE.
     *
     * Needed for the one caller that has established there is no table to read:
     * asking current() there would run the very query whose table is missing.
     * An earlier draft did exactly that, and the table-absent guard above it
     * protected nothing.
     */
    public static function unsavedDefault(): self
    {
        return new self([
            'singleton' => self::SINGLETON,
            'identity_source' => self::SOURCE_ENV,
            'identity_config_revision' => 1,
        ]);
    }

    /**
     * The singleton row, for WRITING.
     *
     * firstOrCreate on the UNIQUE column, so two concurrent callers cannot
     * produce two rows: the second one's insert violates the constraint and it
     * re-reads. The database decides the winner, not the application's timing.
     */
    public static function forUpdate(): self
    {
        return self::query()->firstOrCreate(
            ['singleton' => self::SINGLETON],
            ['identity_source' => self::SOURCE_ENV, 'identity_config_revision' => 1],
        );
    }

    public function identityReadsStore(): bool
    {
        return $this->identity_source === self::SOURCE_STORE;
    }
}
