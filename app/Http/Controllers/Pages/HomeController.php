<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The business landing pages.
 *
 * These render structure and empty states only. No metric, trend or
 * recommendation is produced, because no data source exists yet and
 * PHASE-00-UI-SHELL.md section 3 forbids faking one.
 */
class HomeController extends Controller
{
    /**
     * The icon each domain card carries, matching its rail entry so the same
     * domain is recognisable in both places.
     */
    private const DOMAIN_ICONS = [
        'executive' => 'i-target',
        'sales' => 'i-trending-up',
        'finance' => 'i-banknote',
        'people' => 'i-users',
        'operations' => 'i-server',
        'customer' => 'i-heart',
        'learning' => 'i-graduation',
    ];

    public function home(Request $request): View
    {
        $user = $request->user();

        return view('pages.home', [
            'user' => $user,
            'greeting' => $this->greetingFor($user->name),
            'domains' => $user->entitledDomains(),
        ]);
    }

    public function intelligence(Request $request): View
    {
        return view('pages.intelligence', [
            'domains' => $request->user()->entitledDomains(),
            'icons' => self::DOMAIN_ICONS,
        ]);
    }

    /**
     * Time-of-day greeting.
     *
     * Uses the server's timezone, which is right while one deployment serves one
     * organisation and wrong the moment it does not. Noted rather than solved:
     * the fix is the viewer's own timezone, and nothing stores one yet.
     */
    private function greetingFor(string $name): string
    {
        $first = explode(' ', trim($name))[0] ?: 'there';
        $hour = (int) now()->format('G');

        $part = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return "{$part}, {$first}";
    }
}
