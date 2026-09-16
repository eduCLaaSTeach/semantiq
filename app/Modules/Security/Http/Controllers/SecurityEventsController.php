<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Modules\Security\Catalogue\EventCatalogue;
use App\Modules\Security\Http\Controllers\Concerns\RendersPosture;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Security Events - WHAT IS RECORDED AND HOW IT IS PROTECTED. Not a history.
 *
 * P1-06 DOES NOT READ THE LOG FILES. SecurityEventLogger::record() ends at
 * Log::info(), and its own docblock says "No audit table - P1-08 owns durable
 * storage". The evidence is therefore not durable, not queryable, not
 * tamper-resistant and not complete. Parsing it would build a second audit
 * system with worse properties than the one P1-08 will build, present a partial
 * history as though it were complete, and put unbounded file I/O behind an
 * administration screen. N-SS28 asserts no file read from this namespace.
 *
 * Three panels: the catalogue of what is recorded, the redaction contract, and
 * an explicit statement of what is missing until Audit arrives.
 */
final class SecurityEventsController
{
    use RendersPosture;

    public function show(Request $request): Response
    {
        return Inertia::render('Security/Events', [
            'summary' => $this->summary($this->report($request)),
            'groups' => EventCatalogue::grouped(),
            'total' => EventCatalogue::total(),
            'permittedFields' => EventCatalogue::permittedFields(),
            'redactionPromise' => EventCatalogue::REDACTION_PROMISE,
            'limitation' => EventCatalogue::LIMITATION,
        ]);
    }
}
