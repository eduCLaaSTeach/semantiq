<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Security\Exceptions\SecurityStorageNotInitialised;
use App\Modules\Security\Http\Requests\UpdateSecurityPolicyRequest;
use App\Modules\Security\Support\SecurityPolicies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * What the three security policy screens have in common.
 *
 * They differ in which catalogue screen they render and in the CONTEXT they put
 * around it - the authentication screen explains the modes, the session screen
 * explains what the driver cannot do, the API screen shows live control
 * results. They do not differ in how a value is saved, and writing that three
 * times would be three places for it to drift.
 *
 * A subclass names its screen and adds its own context. Everything about
 * authority, validation, the reason requirement and the audit trail happens in
 * `SecurityPolicies::set()`, which is where a console command and a queued job
 * reach it too.
 */
abstract class SecurityPolicyController extends Controller
{
    public function __construct(
        protected readonly SecurityPolicies $policies,
    ) {}

    /** Which catalogue screen this controller serves. */
    abstract protected function screen(): string;

    /**
     * The values and definitions every policy screen needs.
     *
     * `blockers` is the part specific to this gate: a policy whose capability
     * is missing is reported alongside its field so the screen can say the
     * value is stored and not in force, rather than showing a control that
     * looks active. Decision D3.
     *
     * @return array<string, mixed>
     */
    protected function screenData(): array
    {
        $definitions = $this->policies->forScreen($this->screen());

        $values = [];
        $blockers = [];

        foreach (array_keys($definitions) as $key) {
            $values[$key] = $this->policies->get($key);
            $blockers[$key] = $this->policies->inForce($key) ? null : $this->policies->blocker($key);
        }

        $meta = (array) config('security.screens.'.$this->screen(), []);

        return [
            'screen' => $this->screen(),
            /*
             * The deployment window: code is live and the migration has not
             * run. The VALUES on this screen are correct - with no table there
             * can be no override, so the catalogue defaults are what is in
             * force - but nothing can be changed, and the screen says so rather
             * than letting somebody find out by pressing Save.
             */
            'storageReady' => $this->policies->storageIsReady(),
            'storageBlocker' => $this->policies->storageBlocker(),
            'title' => $meta['title'] ?? 'Security',
            'subtitle' => $meta['subtitle'] ?? '',
            'feature' => $meta['feature'] ?? '',
            'definitions' => $definitions,
            'values' => $values,
            'blockers' => $blockers,
            'updateRoute' => 'admin.security.'.$this->screen().'.update',
            'backRoute' => 'admin.security.'.$this->screen(),
        ];
    }

    /**
     * Save whatever changed, and say how much.
     */
    protected function save(UpdateSecurityPolicyRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::user();

        $changed = 0;

        try {
            foreach ($request->normalise() as $key => $value) {
                $changed += $this->policies->set($key, $value, $actor, $request->reason()) ? 1 : 0;
            }
        } catch (SecurityStorageNotInitialised $exception) {
            /*
             * Caught before the refusal handler below so the message is the
             * controlled one rather than a validation-shaped error. Nothing was
             * written and nothing was audited as though it might have been -
             * the guard runs before any other check in `set()`.
             */
            return back()->withInput()->withErrors(['policies' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            /*
             * Thrown when a key is unknown or secret-bearing, a value is
             * invalid or credential-shaped, a required reason is missing, or
             * the actor lacks authority. Every one of those is a refusal rather
             * than a fault, so it comes back on the form: the service has
             * already recorded the denial where one was warranted.
             *
             * `withInput()` keeps what they typed, including a long reason
             * nobody wants to write twice.
             */
            return back()->withInput()->withErrors(['policies' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.security.'.$this->screen())
            ->with('status', $changed === 0
                ? 'No changes to save.'
                : $changed.' security '.($changed === 1 ? 'policy' : 'policies').' saved.');
    }
}
