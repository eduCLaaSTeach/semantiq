<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What SemantIQ knows about the Fabric items it manages in a customer's tenant.
 *
 * Fields follow the SRS section 17 FabricItem entity. This is a reference table,
 * not a copy: it holds Microsoft's identifiers, the item's type and the state
 * SemantIQ last observed, and nothing of what the item contains. A Lakehouse's
 * data stays in the customer's OneLake, in their approved geography; only the
 * identifier crosses into the control plane (NFR-COMP-01, and the standard's
 * "prefer resource IDs over business payload copies" rule).
 *
 * `last_seen_at` is what makes drift detectable. An item that Microsoft no
 * longer returns has not necessarily been deleted, so the row is kept and its
 * status moves to a drift state rather than the row disappearing. Deleting the
 * evidence is the one response that makes the change impossible to investigate.
 *
 * No Microsoft call is made in this phase; the table exists so Phase 02 has
 * somewhere to record what it provisions.
 *
 * Requirement IDs: NFR-COMP-01, NFR-MNT-01. SRS sections 9, 17, 18.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /*
             * Microsoft's own identifiers, stored as strings rather than as a
             * uuid column: they are opaque values from another system, and the
             * product must not depend on their format staying a GUID.
             */
            $table->string('item_id', 64);
            $table->string('workspace_id', 64);

            /*
             * The Fabric item type as Microsoft names it - Lakehouse, Notebook,
             * SemanticModel, DataPipeline. Not an enum: the set is Microsoft's
             * to extend, and a deployment that meets an unknown type should
             * record it, not reject it.
             */
            $table->string('type', 64);

            $table->string('display_name', 191);

            /*
             * DEV, TEST or PROD. Which environment an item belongs to decides
             * which approvals apply to changing it, so it is stored rather than
             * inferred from a naming convention.
             */
            $table->string('environment', 16)->nullable();

            $table->string('definition_version', 64)->nullable();

            /* The ten-state model from SRS section 18.1. */
            $table->string('status', 32);

            /*
             * When Microsoft last confirmed this item exists as recorded. Null
             * means SemantIQ has written the row but not yet verified it.
             */
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            /*
             * Scoped by organisation rather than globally unique. Two customers'
             * tenants are separate identifier spaces, and a global unique index
             * would let one customer's row block another's insert - a
             * cross-tenant coupling hiding inside a constraint.
             */
            $table->unique(['organisation_id', 'item_id']);
            $table->index(['organisation_id', 'workspace_id']);
            $table->index(['organisation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_items');
    }
};
