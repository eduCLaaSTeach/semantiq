<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The structured privacy contact. Feature ADM-002, gate 4 batch R1.4a.
 *
 * SEC-DEC-043 recorded that `organisations.privacy_contact` was one optional
 * free-text field while the PDPA expects a designated contact to be reachable.
 * That decision was resolved on 24 August 2026: the contact becomes REQUIRED
 * and STRUCTURED - name, email, optional phone, optional role or title.
 *
 * THE PART THAT MATTERS IS HOW IT BECOMES REQUIRED, and it is not by changing
 * validation and letting the next save fail.
 *
 *   1. The columns are added NULLABLE. A schema-level NOT NULL would have to
 *      invent a value for every existing row, and an invented privacy contact
 *      is worse than an empty one: it looks like an appointment somebody made.
 *
 *   2. The existing free-text value is BACKFILLED into `privacy_contact_name`,
 *      because whatever is in there today is far more likely to be a name than
 *      anything else. Nothing is parsed out of it - splitting a free-text field
 *      into name and email by guessing at its shape is how a wrong email
 *      address ends up on a regulatory contact record.
 *
 *   3. The ORIGINAL COLUMN IS KEPT. It is the source the backfill came from, and
 *      dropping it in the same release would leave nothing to compare against
 *      if the backfill turns out to have taken the wrong thing. A later,
 *      separately approved migration may remove it.
 *
 *   4. Validation is STAGED in the application, not here. Nothing breaks until
 *      somebody opens the organisation profile and saves it, and the screen
 *      tells them what it now needs before they do.
 *
 * WHY EMAIL AND NOT A LOGIN. These fields hold accountability information that
 * appears on screens and in evidence. They never authenticate anybody, they are
 * not linked to `users`, and a person named here need not have an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('privacy_contact_name', 190)->nullable()->after('privacy_contact');
            $table->string('privacy_contact_email', 190)->nullable()->after('privacy_contact_name');
            $table->string('privacy_contact_phone', 64)->nullable()->after('privacy_contact_email');
            $table->string('privacy_contact_role', 190)->nullable()->after('privacy_contact_phone');
        });

        /*
         * The backfill. Only where the old field actually holds something, and
         * only into the name - see point 2 above. `whereNotNull` plus the empty
         * check means running this twice changes nothing the second time.
         */
        DB::table('organisations')
            ->whereNotNull('privacy_contact')
            ->where('privacy_contact', '<>', '')
            ->whereNull('privacy_contact_name')
            ->update(['privacy_contact_name' => DB::raw('privacy_contact')]);
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'privacy_contact_name',
                'privacy_contact_email',
                'privacy_contact_phone',
                'privacy_contact_role',
            ]);
        });

        /*
         * `privacy_contact` is untouched on the way down because it was
         * untouched on the way up. Rolling back loses only what was entered
         * into the new fields after the migration ran, which is the correct
         * and unavoidable cost of reversing it.
         */
    }
};
