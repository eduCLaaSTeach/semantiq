<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The sign-in screen and the end of a session.
 *
 * The screen is rendered server-side rather than inside the single-page
 * application: it carries no shell (§5.7 Auth), needs no client-side routing,
 * and the federated flow it starts is a series of full-page redirects anyway.
 */
class SignInController extends Controller
{
    /**
     * Show the sign-in screen.
     *
     * Someone already signed in has no business here, so they are sent on rather
     * than shown a form that would only confuse them.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.sign-in', [
            'status' => $request->session()->get('status'),
            'configured' => filled(config('services.microsoft.tenant'))
                && filled(config('services.microsoft.client_id'))
                && filled(config('services.microsoft.client_secret'))
                && filled(config('services.microsoft.redirect')),
        ]);
    }

    /**
     * End the session.
     *
     * The session is invalidated and its token regenerated so the cookie left in
     * the browser cannot be replayed. This signs the person out of SemantIQ
     * only; their Microsoft session is theirs to end.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sign-in')->with('status', [
            'level' => 'success',
            'title' => 'You are signed out',
            'body' => 'Your SemantIQ session has ended. You are still signed in to Microsoft.',
        ]);
    }
}
