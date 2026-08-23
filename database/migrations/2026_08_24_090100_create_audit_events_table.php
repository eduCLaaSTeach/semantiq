<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The append-only record of who changed what.
 *
 * Release 1 section 3: every configuration change affecting identity, access,
 * security, data protection, sovereignty, integrations or infrastructure must
 * create an audit event. Section 32 gate 1 makes the writer a prerequisite for
 * every later gate, which is why the table lands before the screens that fill it.
 *
 * Two properties this schema is shaped around.
 *
 * APPEND ONLY. There is no `updated_at`, because a row that can be updated is
 * not evidence. `App\Modules\Audit\Models\AuditEvent` refuses updates and
 * deletes at the model layer; the missing column is the reminder of why.
 *
 * NO PAYLOADS. `before_summary` and `after_summary` hold a REDACTED summary
 * produced by `App\Modules\Audit\Support\Redaction` - key names, a coarse
 * shape, and a hash where the value itself matters. A secret value, a token or
 * a customer data extract must never reach this table, which is both
 * CLAUDE.md's rule and ADM-013's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();

            /*
             * Whose evidence this is. Nullable for a platform-level event that
             * happens before any organisation is resolved - a failed sign-in
             * against an unknown address, for instance.
             */
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();

            /*
             * UTC, recorded explicitly rather than relying on `created_at`, so
             * the evidence timestamp is a column with a stated meaning rather
             * than a framework convention a later change could reinterpret.
             */
            $table->timestamp('occurred_at');

            /*
             * The actor. `actor_user_id` is null when the actor is the system,
             * the scheduler, or an unauthenticated request. `actor_label` keeps
             * a readable identifier - never a password, never a token - so the
             * trail still reads after the account row is deleted.
             */
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 24)->default('user');
            $table->string('actor_label')->nullable();

            /* What happened, in the catalogue's dotted form: `user.disabled`. */
            $table->string('action', 96);
            $table->string('module', 32);

            /* What it happened to. Type plus id rather than a polymorphic
             * relation, because the target row may be gone by the time the
             * trail is read and the evidence must survive it. */
            $table->string('resource_type', 64)->nullable();
            $table->string('resource_id', 64)->nullable();

            /* succeeded, failed or denied. A trail containing only successes
             * cannot show an attack that failed, so denials are recorded. */
            $table->string('outcome', 16);

            /* Redacted summaries. Never the payload itself. */
            $table->json('before_summary')->nullable();
            $table->json('after_summary')->nullable();

            /* Why, where the actor was asked for a reason. */
            $table->string('reason', 512)->nullable();

            /*
             * Request context. The IP is personal data under most regimes, so
             * it is recorded only for security-relevant actions and is subject
             * to the same retention policy as the rest of the row.
             */
            $table->string('ip_address', 45)->nullable();
            $table->string('correlation_id', 64)->nullable();

            /* Which environment produced it: a production event and a staging
             * event must never be mistaken for each other. */
            $table->string('environment', 32);

            $table->timestamp('created_at')->nullable();

            /* The three questions the trail is actually asked. */
            $table->index(['organisation_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
