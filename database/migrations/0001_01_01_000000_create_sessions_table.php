<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel framework baseline only.
 *
 * The database session driver is the one place P1-BASE looks ahead: P1-00 has to
 * revoke sessions on privilege change, and a file-driver session cannot be
 * invalidated server-side by user id.
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
