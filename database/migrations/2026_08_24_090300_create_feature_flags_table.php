<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an optional capability is switched on. Feature ADM-021.
 *
 * The same shape as `system_settings` and for the same reason: the catalogue of
 * flags lives in `config/platform.php`, and this table records only the
 * deviations from the declared default. An unknown flag is OFF - a flag that
 * cannot be found must never read as enabled, or deleting its declaration
 * silently turns a capability on.
 *
 * A flag is not an access control. It decides whether a capability is available
 * at all; who may use it is still decided by the tier, the permission and the
 * domain entitlement. Nothing here may be used to grant access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();

            /* Null means platform-wide, as with settings. */
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('key', 96);
            $table->boolean('enabled');

            /* Why it was turned on or off. A flag toggled without a reason is
             * the change nobody can explain three months later. */
            $table->string('reason', 512)->nullable();

            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organisation_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
