<?php

declare(strict_types=1);

namespace App\Modules\Audit\Providers;

use App\Modules\Audit\Services\AuditWriter;
use App\Modules\Platform\Security\EvidenceRecorder;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires P1-08 behind the existing emit boundary.
 *
 * A SINGLETON, because the writer holds refusal evidence raised inside a
 * transaction until that transaction ends. A per-resolution instance would hold
 * rows nobody ever flushes - evidence lost to a wiring detail.
 */
final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditWriter::class);
        $this->app->bind(EvidenceRecorder::class, AuditWriter::class);
    }

    public function boot(): void
    {
        /*
         * A ROLLBACK MAY HAVE TAKEN A REFUSAL WITH IT. Written again, once, and
         * only if it actually went.
         *
         * BOTH EVENTS ARE LISTENED TO, deliberately. A refusal is usually
         * recorded on the way to a rollback - but StepUpService records one
         * inside a transaction that then COMMITS the consumption. Listening to
         * only the rollback would leave those rows remembered forever and
         * re-checked on every later rollback; listening to only the commit
         * would lose the recovery entirely. Either mistake looks correct in
         * whichever half its author happened to test.
         */
        Event::listen(
            TransactionRolledBack::class,
            fn (): mixed => $this->app->make(AuditWriter::class)->recoverRolledBack(),
        );

        Event::listen(TransactionCommitted::class, function (): void {
            if (DB::transactionLevel() === 0) {
                $this->app->make(AuditWriter::class)->forgetFragile();
            }
        });
    }
}
