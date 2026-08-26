<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The assembled response to a privacy request. Feature PDPA-01, R1.4c-i.
 *
 * One row per collected item. This IS the response - there is no document, no
 * export and no generated file anywhere in gate 4 (decision D9). The response
 * is reviewed on screen inside SemantIQ and delivered outside it, and
 * `privacy_requests.evidence_reference` records how.
 *
 * WHY THE ASSEMBLED RESULT IS STORED RATHER THAN RECOMPUTED. A response must be
 * reproducible. If it were recomputed at read time it would change as the
 * underlying data changed, and nobody could later say what was actually
 * disclosed on the day. These rows are the evidence of what was released.
 *
 * `band` AND `treatment` ARE THE DISCLOSURE MODEL, stored per row so a reviewer
 * can see not only what is being released but on what basis:
 *
 *   A  the subject's own record                 default include
 *   B  records about the subject                 default include, rendered
 *   C  the subject's name on someone else's row  default DESCRIBE
 *   D  free text that may name a person          default describe
 *
 * BAND C IS THE DESIGN PROBLEM. "Alice approved Bob's Finance entitlement" is
 * personal data about Alice AND about Bob. Releasing it verbatim to Alice
 * discloses Bob, who asked for nothing and consented to nothing. Withholding it
 * entirely under-answers a lawful request. `describe` discloses the fact
 * without the second person: "You granted a business-domain entitlement to
 * another user on 3 March 2026." Decision D5.
 *
 * `summary` IS ALREADY RENDERED. The band C renderer is never given the other
 * person's name or id, so a template mistake cannot leak what the function
 * never held. `detail` is populated only for `include` rows.
 *
 * WIDENING NEEDS TWO PEOPLE. `reviewer_action` records whether a treatment was
 * kept, narrowed or widened. Narrowing is one reviewer's call; widening is the
 * dangerous direction and requires a second approver who is not the first, with
 * `reviewer_note` required. Enforced in the service.
 *
 * AUDIT REDACTOR CHECK. Every column name was checked against
 * `Redaction::isSensitiveKey()`. None matches. SEC-DEC-044.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_request_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /*
             * Cascade is correct here and only here: these rows have no meaning
             * apart from their request. They are not an audit trail - the audit
             * trail records that the assembly happened.
             */
            $table->foreignId('privacy_request_id')->constrained('privacy_requests')->cascadeOnDelete();

            /* A, B, C or D. */
            $table->string('band', 1);

            /* Which table this came from, for the coverage test to reconcile. */
            $table->string('source_table', 64);

            /* The collector class that produced it. */
            $table->string('collector', 190);

            /* include, describe or exclude. */
            $table->string('treatment', 16);

            /* What the subject is told. Already rendered; never re-derived. */
            $table->text('summary')->nullable();

            /* Structured payload. Populated for `include` rows only. */
            $table->json('detail')->nullable();

            $table->timestamp('occurred_at')->nullable();

            /* kept, narrowed or widened. */
            $table->string('reviewer_action', 16)->nullable();
            $table->text('reviewer_note')->nullable();

            $table->timestamps();

            $table->index(
                ['organisation_id', 'privacy_request_id', 'band'],
                'privacy_request_records_org_request_band_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_request_records');
    }
};
