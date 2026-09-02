<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Support\ApprovedProviders;
use App\Modules\Identity\Support\ProviderInventory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Other Identity Providers.
 *
 * The list comes from the APPROVED CATALOGUE, never from the container. An
 * earlier design enumerated container bindings, which made "approved" mean
 * "present" - so anything a future developer bound would have promoted itself
 * onto this screen as an approved way into the product.
 *
 * A runtime provider with no approval is not listed here at all. It fails the
 * build, and health reports it.
 */
final class ProvidersController
{
    public function __construct(private readonly ProviderInventory $inventory) {}

    public function show(): Response
    {
        $approved = [];

        foreach (ApprovedProviders::all() as $key => $name) {
            $approved[] = [
                'key' => $key,
                'name' => $name,
                'configured' => $this->inventory->isConfigured($key),
                'inUse' => in_array($key, $this->inventory->runtimeKeys(), true),
            ];
        }

        return Inertia::render('Identity/Providers', [
            'approved' => $approved,
        ]);
    }
}
