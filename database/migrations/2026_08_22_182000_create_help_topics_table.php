<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The in-app help centre content, structured to the SRS section 15.1 template.
 *
 * Deliberately NOT organisation-scoped, and this is the one table in the phase
 * where that is the right answer. A help topic is product documentation: the
 * steps for granting tenant admin consent are the same for every customer, and
 * scoping them would mean seeding an identical copy per organisation and then
 * keeping every copy in step with Microsoft's portal. Anything customer-specific
 * that a topic needs to show, such as the redirect URI to copy, is resolved at
 * render time from the reader's own organisation, never stored here.
 *
 * The section 15.1 template is stored as discrete columns rather than one blob
 * because the template is a contract: a topic missing "Who can do it" or
 * "Microsoft reference" is incomplete, and a schema that cannot express that is
 * a schema that will let it ship.
 *
 * `last_reviewed_at` and `microsoft_reference` carry the Microsoft freshness
 * rule from CLAUDE.md into the data: a topic whose review date has gone stale
 * is a reportable condition, not a matter of someone remembering.
 *
 * Requirement IDs: NFR-SUP-01, NFR-MNT-01. SRS section 15.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_topics', function (Blueprint $table) {
            $table->id();

            /*
             * The specification identifier, HLP-ORG-001 and so on. Unique and
             * used in every cross-reference, so a screen requests help by the
             * identifier the SRS uses rather than by a database key.
             */
            $table->string('topic_id', 32)->unique();

            $table->string('title', 191);

            /*
             * Task-oriented one-liner for the topic list, so the help centre
             * index is scannable without opening each topic.
             */
            $table->string('summary', 500)->nullable();

            /* The SRS section 15.1 sections, in the order the template lists them. */
            $table->text('why_required')->nullable();
            $table->text('who_can_do_it')->nullable();
            $table->text('prerequisites')->nullable();
            $table->text('where_to_go')->nullable();
            $table->text('steps')->nullable();

            /*
             * The values a reader copies into a Microsoft portal. JSON of label
             * and token pairs, resolved against the reader's organisation when
             * rendered. Tokens only, never a resolved secret: nothing in this
             * table is confidential, and nothing here may ever become so.
             */
            $table->json('values_to_copy')->nullable();

            $table->text('security_note')->nullable();
            $table->text('expected_result')->nullable();
            $table->text('verify_in_semantiq')->nullable();
            $table->text('troubleshooting')->nullable();

            /* Microsoft reference and its freshness evidence. */
            $table->string('microsoft_reference', 500)->nullable();
            $table->date('last_reviewed_at')->nullable();

            /*
             * Which SemantIQ release the topic describes, and which revision of
             * the topic this is. Both are strings because neither is arithmetic.
             */
            $table->string('product_version', 32)->nullable();
            $table->string('content_version', 32)->default('1');

            /*
             * Draft topics exist in the table but are not offered to readers, so
             * a topic can be authored and reviewed before it is published.
             */
            $table->string('status', 32)->default('draft')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_topics');
    }
};
