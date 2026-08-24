<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The data sovereignty profile. Feature ADM-015, gate 4 batch R1.4a.
 *
 * Where this organisation's data is stored, where it is processed, where AI
 * processes it, where the backups live, and whether anything crosses a border.
 *
 * VERSIONED for the same reason ADM-014 is, and more sharply: a sovereignty
 * position is the answer to "where was our data on this date", and an editable
 * row cannot answer it. SEC-DEC-065.
 *
 * WHY BACKUP GEOGRAPHY IS ITS OWN COLUMN. A server's country is not the same as
 * its backups' country - backups routinely leave the country the server sits
 * in, which would move data out of a geography without the server moving.
 * SEC-DEC-036 records that all three were asked separately for this deployment,
 * and all three came back Singapore. Folding backups into storage geography
 * would lose exactly the distinction that made the question worth asking.
 *
 * EVERY CROSS-GEO SWITCH DEFAULTS TO FALSE. CLAUDE.md requires cross-geo
 * processing, storage and AI or conversation-history settings to default OFF,
 * and a database default is the version of that rule which survives somebody
 * forgetting it in application code.
 *
 * THE FIRST VERSION IS SEEDED AS A DRAFT. Decision D12, SEC-DEC-068. The three
 * confirmed production facts are written in rather than retyped, but a profile
 * nobody approved is a guess with good provenance - it is created as a draft,
 * the screen says so, and nothing downstream honours it.
 *
 * AUDIT REDACTOR CHECK. `approved_geographies` is used, never
 * `authorised_geographies`, because `auth` is a matched fragment and the value
 * would be stored as "[redacted]". `evidence_reference` is used, never
 * `certification_reference`, because `cert` is matched too. SEC-DEC-044.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sovereignty_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('version')->default(1);

            /* ProfileStatus: draft, approved, superseded. */
            $table->string('status', 16)->default('draft');

            /*
             * The four geographies, each a key from the catalogue's curated
             * list. Nullable, and `not_determined` is a real answer that is not
             * the same as any country - the screen must never let the two read
             * alike.
             */
            $table->string('storage_geography', 32)->nullable();
            $table->string('processing_geography', 32)->nullable();
            $table->string('ai_processing_geography', 32)->nullable();
            $table->string('backup_geography', 32)->nullable();

            /*
             * The geographies this organisation permits at all, as a JSON list.
             * NOT `authorised_geographies`: that name contains `auth` and the
             * audit redactor would store the value as "[redacted]".
             */
            $table->json('approved_geographies')->nullable();

            /* Codified. none, same_geography, cross_geography, not_determined. */
            $table->string('external_replication', 32)->nullable();

            /* All four OFF by default, at the database. */
            $table->boolean('cross_geo_storage')->default(false);
            $table->boolean('cross_geo_processing')->default(false);
            $table->boolean('cross_geo_ai')->default(false);
            $table->boolean('cross_geo_conversation_history')->default(false);

            /*
             * Where the answers came from. Carries the seed's provenance so a
             * reader can tell a confirmed fact from a typed-in one.
             */
            $table->text('source_note')->nullable();

            /*
             * A pointer to evidence held elsewhere - a hosting contract, a
             * provider attestation. NOT `certification_reference`: `cert` is a
             * matched redactor fragment.
             */
            $table->string('evidence_reference', 190)->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()
                ->constrained('data_sovereignty_profiles')->nullOnDelete();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organisation_id', 'version']);
            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sovereignty_profiles');
    }
};
