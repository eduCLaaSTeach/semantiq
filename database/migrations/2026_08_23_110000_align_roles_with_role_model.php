<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the stored tier codes from the design template's five-tier baseline to
 * the six roles doc/ROLE_MODEL.md defines, and add the Auditor capability.
 *
 * The remap is data, not just a default. Rows already carry the old codes, and
 * leaving them would mean every comparison silently failing closed: an old
 * `self_view` matches no case in the new enum, so that account would be locked
 * out rather than downgraded.
 */
return new class extends Migration
{
    /**
     * Old tier code to new. `team` becomes Domain Owner and `self` becomes
     * Contributor, which preserves each account's relative authority; the new
     * Analyst rung sits between them and nobody is placed on it automatically,
     * because no existing account was ever described that way.
     */
    private const FORWARD = [
        'system_admin' => 'system_admin',
        'admin' => 'admin',
        'team' => 'domain_owner',
        'self' => 'contributor',
        'self_view' => 'viewer',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * Reads the audit trail and governance evidence while holding no
             * operational rights. A flag rather than a tier: as a rung it would
             * either carry power an auditor must not have, or miss the
             * Compliance cluster it exists to reach.
             */
            $table->boolean('is_auditor')->default(false)->after('role');
        });

        foreach (self::FORWARD as $old => $new) {
            DB::table('users')->where('role', $old)->update(['role' => $new]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default(Role::default()->value)->change();
        });
    }

    public function down(): void
    {
        foreach (array_reverse(self::FORWARD, true) as $old => $new) {
            /*
             * Reversing the map loses the Analyst rung, which has no equivalent
             * in the old five. Those accounts land on `self`, the nearest tier
             * below, rather than being silently promoted.
             */
            DB::table('users')->where('role', $new)->update(['role' => $old]);
        }

        DB::table('users')->where('role', 'analyst')->update(['role' => 'self']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('self_view')->change();
            $table->dropColumn('is_auditor');
        });
    }
};
