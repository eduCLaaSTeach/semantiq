<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-15: membership references the existing users table. There is no people
 * table, and there will not be one.
 *
 * left_at rather than deletion: "who was in this team in March" is a question
 * P1-07 access review will ask, and a deleted row cannot answer it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('team_id')->constrained('teams');
            $table->foreignId('user_id')->constrained('users');
            $table->date('joined_at');

            // NULL means current.
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id', 'joined_at'], 'team_memberships_uq');
            $table->index('user_id', 'team_memberships_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_memberships');
    }
};
