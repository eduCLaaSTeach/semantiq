<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Federated identity columns, so an account can be a mirror of a Microsoft Entra
 * directory account rather than a credential this application owns.
 *
 * The password column is relaxed to nullable FIRST, in its own statement. SQLite
 * has no ALTER COLUMN and rebuilds the whole table to emulate one, and a rebuild
 * discards standalone indexes created earlier in the same migration. Adding the
 * index before this change silently loses it, and the loss only shows up as a
 * duplicate account months later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * Null for every federated account. SemantIQ holds no password for
             * someone whose identity is Microsoft's to prove.
             */
            $table->string('password')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            /*
             * The directory's immutable identifier for the person. Unique, and
             * the key identity is matched on: an address can be reassigned or
             * changed after a marriage, this cannot.
             */
            $table->string('entra_object_id', 64)->nullable()->unique()->after('email');

            /* Which directory issued them, so a multi-tenant deployment can tell
               two people with the same address in different tenants apart. */
            $table->string('entra_tenant_id', 64)->nullable()->after('entra_object_id');

            $table->timestamp('last_signed_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Index and unique constraint first: dropping the column out from
            // under them fails on MySQL.
            $table->dropUnique(['entra_object_id']);
            $table->dropColumn(['entra_object_id', 'entra_tenant_id', 'last_signed_in_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
