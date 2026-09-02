<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group membership, with history - and deliberately NOT keyed the way P1-01
 * keys team membership.
 *
 * P1-01 uses UNIQUE(team_id, user_id, joined_at) over date-valued timing. That
 * cannot represent join -> leave -> rejoin on the same day: the second period
 * carries the same three key values as the first, and the database refuses it
 * with an integrity error about something the administrator did not do wrong.
 *
 * So two decisions here:
 *
 *   1. joined_at and left_at are DATETIME. Two genuine membership periods on one
 *      calendar day are distinguishable by their actual boundaries.
 *   2. There is NO uniqueness on joined_at. The invariant worth enforcing is
 *      "at most one CURRENT membership", not "no two rows share a start".
 *
 * MySQL 8.4 has no partial index, so that invariant cannot be expressed
 * declaratively here. It is enforced by a locking read inside the write
 * transaction - the same mechanism the D-24 purge guard uses for its second
 * check - and MembershipRulesTest breaks it deliberately to prove the guard is
 * doing the work rather than the database.
 *
 * A membership row is never deleted. Leaving sets left_at; the row is the
 * evidence that somebody was once a member, which is the only reason to keep a
 * membership table rather than a list of current members.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_memberships', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('group_id');
            $table->foreignId('user_id');

            // Timestamps, not dates. See the note above.
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();

            $table->timestamps();

            $table->index('group_id', 'group_members_group_idx');
            $table->index('user_id', 'group_members_user_idx');

            $table->foreign('group_id', 'group_members_group_fk')
                ->references('id')
                ->on('groups');

            $table->foreign('user_id', 'group_members_user_fk')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_memberships');
    }
};
