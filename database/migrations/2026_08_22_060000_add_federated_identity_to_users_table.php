<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turn the scaffold's local-password users table into a federated-identity one.
 *
 * SemantIQ authenticates against Microsoft Entra ID and stores no credential of
 * its own, so the password column becomes nullable rather than being dropped:
 * dropping it would break Laravel's Authenticatable contract, while leaving it
 * NOT NULL would force every federated account to carry a fake hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * The password column is relaxed first, before anything is added.
         * On a driver that implements a column change by rebuilding the table,
         * such as SQLite, the rebuild discards standalone indexes, so an index
         * created before this point would silently not exist afterwards.
         *
         * Raw SQL on MySQL because changing a column's nullability through the
         * schema builder needs doctrine/dbal, which this project does not carry.
         */
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement(
                'ALTER TABLE `users` MODIFY `password` VARCHAR(255) NULL'
            );
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password')->nullable()->change();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            /*
             * The Entra ID object identifier, which is the only stable handle for
             * an account. An email address is re-assignable and a UPN can be
             * renamed, so neither can be the identity key; this is matched first
             * and the address is only a fallback for an account created before
             * its first federated sign-in.
             */
            $table->string('entra_object_id', 64)->nullable()->unique()->after('id');

            /*
             * The directory the account signed in from. Recorded so a future
             * multi-tenant deployment can tell two accounts apart when they hold
             * the same address in different directories.
             */
            $table->string('entra_tenant_id', 64)->nullable()->after('entra_object_id');

            /*
             * The five-tier role baseline. Stored as one column because an
             * account holds exactly one tier at a time, and the tiers are
             * cumulative rather than composable.
             */
            $table->string('role', 32)->default('self_view')->after('email_verified_at');

            $table->timestamp('last_signed_in_at')->nullable()->after('role');

            $table->index('role');
        });
    }

    public function down(): void
    {
        /*
         * Reversing this leaves any federated account with a null password, which
         * the original NOT NULL column cannot hold. Those rows are deleted rather
         * than given a fabricated hash, since a row that cannot be signed into is
         * not worth preserving and a fabricated hash is a credential-shaped lie.
         */
        Schema::getConnection()->table('users')->whereNull('password')->delete();

        /*
         * The index and columns go first, before the password column is restored.
         * On a driver that implements a column change by rebuilding the table,
         * such as SQLite, the rebuild discards the index, and dropping it
         * afterwards then fails on an index that is already gone.
         */
        Schema::table('users', function (Blueprint $table) {
            /*
             * Both indexes are named explicitly and dropped before their columns.
             * SQLite refuses to drop a column an index still references, and the
             * unique index on entra_object_id is one such reference.
             */
            $table->dropIndex(['role']);
            $table->dropUnique(['entra_object_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['entra_object_id', 'entra_tenant_id', 'role', 'last_signed_in_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement(
                'ALTER TABLE `users` MODIFY `password` VARCHAR(255) NOT NULL'
            );
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password')->nullable(false)->change();
            });
        }
    }
};
