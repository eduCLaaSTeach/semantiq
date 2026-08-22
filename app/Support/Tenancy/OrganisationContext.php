<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Organisation;
use Illuminate\Support\Facades\Auth;

/**
 * The active organisation for the current request, job or command.
 *
 * One place answers "whose data is this?", so the global scope, the
 * authorisation checks and the audit writer cannot disagree about it.
 *
 * The context is deliberately not a request-only concept. A queued job runs
 * without a session, so a workflow must be able to state the organisation it
 * belongs to and have every query inside it scoped the same way.
 *
 * Requirement IDs: NFR-SEC-02.
 */
class OrganisationContext
{
    /**
     * Explicitly set for the current process, by a job or a console command.
     */
    private ?Organisation $current = null;

    /**
     * Whether a caller has deliberately entered the "no organisation" state.
     *
     * Distinct from "nothing set yet". Without this flag, running unscoped
     * would be indistinguishable from forgetting to set a context, and the
     * safest reading of a forgotten context is no access at all.
     */
    private bool $withoutScoping = false;

    /**
     * The organisation whose records may be read and written right now.
     *
     * Falls back to the signed-in person's organisation, so an ordinary web
     * request needs no explicit setup. Returns null when there is genuinely no
     * context, and callers must treat null as no access.
     */
    public function current(): ?Organisation
    {
        if ($this->current !== null) {
            return $this->current;
        }

        $user = Auth::user();

        return $user?->organisation;
    }

    /**
     * The active organisation key, or null.
     */
    public function id(): ?int
    {
        return $this->current()?->id;
    }

    /**
     * Bind an organisation for the current process.
     *
     * Used by queued jobs and console commands, which have no session to infer
     * one from.
     */
    public function set(?Organisation $organisation): void
    {
        $this->current = $organisation;
        $this->withoutScoping = false;
    }

    /**
     * Run a callback with the scope deliberately lifted.
     *
     * The only sanctioned way to read across organisations. It exists for
     * genuine system work: a scheduled task sweeping every tenant, a migration
     * backfill, an administrative report. It is deliberately awkward to reach
     * for, and it restores the previous state even when the callback throws,
     * so an exception cannot leave a process running unscoped.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutScoping(callable $callback): mixed
    {
        $previousFlag = $this->withoutScoping;
        $previousOrganisation = $this->current;

        $this->withoutScoping = true;

        try {
            return $callback();
        } finally {
            $this->withoutScoping = $previousFlag;
            $this->current = $previousOrganisation;
        }
    }

    /**
     * Whether queries should currently bypass the organisation scope.
     */
    public function isScopingDisabled(): bool
    {
        return $this->withoutScoping;
    }

    /**
     * Forget the bound organisation.
     */
    public function forget(): void
    {
        $this->current = null;
        $this->withoutScoping = false;
    }
}
