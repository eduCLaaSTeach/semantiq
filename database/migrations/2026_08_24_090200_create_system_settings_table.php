<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-secret application configuration that an administrator may change at
 * runtime. Feature ADM-021.
 *
 * This table holds OVERRIDES ONLY. The catalogue - key, category, type,
 * default, validation, editable tier, sensitivity - lives in `config/platform.php`
 * because it is code that must be reviewed, not data that may be edited. A key
 * with no row here reads as its declared default, so a fresh install needs no
 * seeder and an unknown key can never resolve to a value.
 *
 * NO SECRETS. CLAUDE.md and Release 1 section 3 both forbid it: a token, a
 * password, a client secret or a connection string belongs in the server
 * environment or an approved secret manager, and gate 3's `secret_references`
 * holds the pointer. `App\Modules\Platform\Support\SystemSettings` refuses to
 * write a key the catalogue marks secret-bearing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            /*
             * Null means platform-wide. A value scoped to an organisation wins
             * over the platform-wide one, which wins over the catalogue default.
             */
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('key', 96);

            /*
             * The value as a string, cast on read by the type the catalogue
             * declares. One column rather than one per type: the catalogue is
             * the type authority and a typed column would only be a second,
             * weaker copy of it that could disagree.
             */
            $table->text('value')->nullable();

            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* One override per key per scope. Setting a value twice is not two
             * settings, and a duplicate row would make "the current value"
             * ambiguous. */
            $table->unique(['organisation_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
