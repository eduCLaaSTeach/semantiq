<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\IdentityProvider;
use Symfony\Component\HttpFoundation\Response;

final class RedirectController
{
    public function __construct(private readonly IdentityProvider $provider) {}

    public function __invoke(): Response
    {
        try {
            return $this->provider->beginAuthorization();
        } catch (AuthenticationFailed) {
            return redirect()->route('auth.sign-in-unavailable');
        }
    }
}
