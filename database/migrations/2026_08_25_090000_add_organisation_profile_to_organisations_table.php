<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of the organisation profile. Feature ADM-002.
 *
 * Gate 1 created the row because everything else is scoped to it and needed an
 * anchor. This adds the fields an administrator actually edits.
 *
 * Every column is nullable except the ones gate 1 already filled. An
 * organisation that exists but has not been described yet is a real state - it
 * is the state every new instance starts in - and forcing a value at the
 * database level would mean inventing one at install time and then being unable
 * to tell an invented value from a chosen one.
 *
 * The three contact fields hold a NAME or a ROLE, not a credential. They appear
 * on screens and in exported evidence, so they must never be used for anything
 * that authenticates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('registration_number', 64)->nullable()->after('legal_name');

            /*
             * Policy context, ADM-002. The country drives which privacy regime
             * questions are asked at go-live; it does not by itself answer them.
             */
            $table->string('primary_country', 2)->nullable()->after('registration_number');
            $table->string('primary_domain', 190)->nullable()->after('primary_country');

            /* IANA identifier and ISO 4217 code, validated in the request. */
            $table->string('default_time_zone', 64)->nullable()->after('primary_domain');
            $table->string('default_currency', 3)->nullable()->after('default_time_zone');
            $table->string('default_language', 16)->nullable()->after('default_currency');

            /* Accountability, not authentication. Names or roles only. */
            $table->string('data_owner', 190)->nullable()->after('default_language');
            $table->string('privacy_contact', 190)->nullable()->after('data_owner');
            $table->string('security_contact', 190)->nullable()->after('privacy_contact');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'registration_number',
                'primary_country',
                'primary_domain',
                'default_time_zone',
                'default_currency',
                'default_language',
                'data_owner',
                'privacy_contact',
                'security_contact',
            ]);
        });
    }
};
