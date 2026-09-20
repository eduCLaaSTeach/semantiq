<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel framework baseline only.
 *
 * The database session driver is the one place P1-BASE looks ahead, and this
 * table was PREPARED for a capability rather than built to serve one that
 * exists.
 *
 * NOTHING IN THE APPLICATION REVOKES ANOTHER PERSON'S SESSION TODAY. The only
 * two invalidations are the viewer's own - sign-out, and the expiry middleware -
 * and both work on any driver. The comment here used to say "P1-00 has to
 * revoke sessions on privilege change", which reads as a delivered control and
 * is not one.
 *
 * What is true: the table exists, and carries user_id, so that Phase 1 CAN
 * support server-side per-user session revocation when the approved
 * privilege-change control is implemented. A file-driver session cannot be
 * invalidated server-side by user id, which is why `database` remains the
 * approved target store even though production currently runs `file` - a
 * carried Phase 1 alignment finding, not a design change.
 *
 * user_id is a plain nullable index, NOT a foreign key. There is no users table
 * in P1-BASE and creating one would be P1-03's business schema arriving early.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
