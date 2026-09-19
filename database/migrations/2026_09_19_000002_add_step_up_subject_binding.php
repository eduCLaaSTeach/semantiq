<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ONE STEP-UP BINDS TO ONE EXACT SUBJECT AND ONE EXACT INTENT.
 *
 * GATE C BLOCKER 1. The first implementation held the chosen decision in a
 * mutable column on the review item and found the item again, after the return
 * from Microsoft, by the id of the ACCESS OBJECT it was about. Two things could
 * go wrong and neither was theoretical: a second tab could change the decision
 * while the first confirmation was away, so the returning step-up executed an
 * intent nobody confirmed; and if the reviewed item became terminal while a
 * LATER review existed for the same access object, the callback attached to the
 * later one - authorising a decision on a review the person never saw.
 *
 * These three columns are DELIBERATELY GENERIC. P1-05 stores an opaque kind, an
 * id and an intent string; it never interprets any of them, and the unit that
 * began the confirmation is the only thing that knows what they mean. That is
 * what lets P1-07 bind a step-up to its own object without P1-05 depending on
 * P1-07, and what will let a later unit do the same without another migration.
 *
 * This is the one schema decision raised beyond P1-07's own two tables, and it
 * is unavoidable: binding a confirmation to an exact intent means storing the
 * intent somewhere the confirmation owns, and anything the review screen owns is
 * mutable while the reviewer is away at Microsoft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_step_ups', function (Blueprint $table): void {
            // What kind of thing this confirmation is about, as the unit that
            // began it names it. P1-05 never reads the value.
            $table->string('subject_type', 64)->nullable()->after('organisation_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            // The EXACT action confirmed, not merely the category of action.
            $table->string('subject_intent', 32)->nullable()->after('subject_id');

            $table->index(['subject_type', 'subject_id'], 'step_ups_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pending_step_ups', function (Blueprint $table): void {
            $table->dropIndex('step_ups_subject_idx');
            $table->dropColumn(['subject_type', 'subject_id', 'subject_intent']);
        });
    }
};
