<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Http\Requests\UpdateSystemSettingsRequest;
use App\Modules\Platform\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Runtime configuration screens. Feature ADM-021.
 *
 * Two screens driven by one controller, because they differ only in which
 * catalogue category they show: General Settings and Environment Settings, both
 * named in MENU_STRUCTURE.md section 12.15. Adding a third is a config entry
 * and a route, not another class.
 *
 * The category comes from the ROUTE and is checked against a closed list.
 * Taking it from the request body would let a crafted post open a category the
 * screen never offered.
 *
 * NO SECRET IS EDITABLE HERE. `SystemSettings::set()` refuses a key that reads
 * as secret-bearing, and this controller has no path around it.
 */
class SystemConfigurationController extends Controller
{
    /**
     * The categories this controller serves, and what each screen is called.
     *
     * A closed list rather than "whatever the catalogue contains", so a new
     * category cannot become a reachable URL by accident.
     */
    private const CATEGORIES = [
        'general' => [
            'title' => 'General Settings',
            'subtitle' => 'How SemantIQ presents itself to the people who use it.',
        ],
        'environment' => [
            'title' => 'Environment Settings',
            'subtitle' => 'How this particular instance identifies itself, and what it tells people during maintenance.',
        ],
    ];

    public function edit(SystemSettings $settings, string $category): View
    {
        $screen = $this->screen($category);

        $definitions = $settings->inCategory($category);
        $values = [];

        foreach (array_keys($definitions) as $key) {
            $values[$key] = $settings->get($key);
        }

        return view('pages.admin.system-settings', [
            'category' => $category,
            'title' => $screen['title'],
            'subtitle' => $screen['subtitle'],
            'definitions' => $definitions,
            'values' => $values,
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request, SystemSettings $settings, string $category): RedirectResponse
    {
        $this->screen($category);

        /** @var User $actor */
        $actor = Auth::user();

        $changed = 0;

        try {
            foreach ($request->normalise() as $key => $value) {
                $changed += $settings->set($key, $value, $actor) ? 1 : 0;
            }
        } catch (InvalidArgumentException $exception) {
            /*
             * Thrown when a key is unknown, secret-bearing, or above the
             * actor's authority. It is a refusal rather than a fault, so it
             * comes back on the form as a message: the writer has already
             * recorded the denial in the audit trail.
             */
            return back()->withInput()->withErrors(['settings' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.system.settings', ['category' => $category])
            ->with('status', $changed === 0
                ? 'No changes to save.'
                : $changed.' setting'.($changed === 1 ? '' : 's').' saved.');
    }

    /**
     * The screen definition for a category, or a 404.
     *
     * @return array{title: string, subtitle: string}
     */
    private function screen(string $category): array
    {
        if (! array_key_exists($category, self::CATEGORIES)) {
            throw new NotFoundHttpException;
        }

        return self::CATEGORIES[$category];
    }
}
