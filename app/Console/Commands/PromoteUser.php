<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\DomainEntitlement;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Grant an account a platform tier, the Auditor capability, or business domains.
 *
 * This exists because the first System Administrator has to come from somewhere.
 * Every account starts as a Viewer by design, so a fresh deployment has nobody
 * who can reach the administration cluster, and the only way through was an
 * ad-hoc tinker session against the production database - typed live, unlogged,
 * one mistyped method away from writing something else entirely.
 *
 * A command is not more powerful than that. It is narrower: it can only do this
 * one thing, it validates what it is given, it shows the change before making
 * it, and it leaves a record in the shell history of what was actually run.
 *
 * Granting an administrator role is on the list of high-impact actions in
 * doc/ROLE_MODEL.md section 6, so it confirms before writing unless told not to.
 */
class PromoteUser extends Command
{
    protected $signature = 'semantiq:promote
                            {email : The account to change}
                            {--role= : A platform tier: '.self::ROLE_LIST.'}
                            {--auditor : Grant the Auditor capability}
                            {--no-auditor : Remove the Auditor capability}
                            {--domain=* : Business domains to entitle, or "all"}
                            {--force : Skip the confirmation}';

    protected $description = 'Grant a platform role, the Auditor capability, or business domains';

    private const ROLE_LIST = 'system_admin, admin, domain_owner, analyst, contributor, viewer';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            /*
             * Named rather than vague. On a fresh deployment the likely reason
             * is that nobody has signed in yet, and the account is created by
             * that first sign-in rather than by this command - which is
             * deliberate: this grants authority, it does not mint identities.
             */
            $this->error("No account found for {$this->argument('email')}.");
            $this->line('Accounts are created when somebody first signs in. This command only changes an existing one.');

            return self::FAILURE;
        }

        $role = $this->resolveRole();

        if ($role === false) {
            return self::FAILURE;
        }

        $domains = $this->resolveDomains();

        if ($domains === false) {
            return self::FAILURE;
        }

        $auditor = match (true) {
            (bool) $this->option('auditor') => true,
            (bool) $this->option('no-auditor') => false,
            default => null,
        };

        if ($role === null && $domains === [] && $auditor === null) {
            $this->warn('Nothing to change. Pass --role, --auditor, --no-auditor or --domain.');

            return self::FAILURE;
        }

        // Show the change before making it, so a mistyped address is caught
        // while it is still a question rather than after it is a grant.
        $this->newLine();
        $this->line("  <options=bold>{$user->name}</> <fg=gray>{$user->email}</>");
        $this->line('  Role      '.$user->role->label().($role !== null ? ' -> <options=bold>'.$role->label().'</>' : ''));
        $this->line('  Auditor   '.($user->is_auditor ? 'yes' : 'no').($auditor !== null ? ' -> <options=bold>'.($auditor ? 'yes' : 'no').'</>' : ''));
        $this->line('  Domains   '.(($held = $this->describe($user->entitledDomains())) ?: 'none')
            .($domains !== [] ? ' -> <options=bold>'.$this->describe($this->merged($user, $domains)).'</>' : ''));
        $this->newLine();

        if (! $this->option('force') && ! confirm('Apply this change?', default: false)) {
            $this->line('No change made.');

            return self::SUCCESS;
        }

        if ($role !== null) {
            $user->role = $role;
        }

        if ($auditor !== null) {
            $user->is_auditor = $auditor;
        }

        $user->save();

        foreach ($domains as $domain) {
            DomainEntitlement::query()->firstOrCreate([
                'user_id' => $user->id,
                'domain' => $domain->value,
            ]);
        }

        $this->info("Updated {$user->email}.");

        return self::SUCCESS;
    }

    /**
     * @return Role|null|false False on an invalid value, null when unchanged.
     */
    private function resolveRole(): Role|null|false
    {
        $value = $this->option('role');

        if ($value === null) {
            return null;
        }

        $role = Role::tryFrom((string) $value);

        if ($role === null) {
            $this->error("'{$value}' is not a platform tier. One of: ".self::ROLE_LIST);

            return false;
        }

        return $role;
    }

    /**
     * @return list<BusinessDomain>|false
     */
    private function resolveDomains(): array|false
    {
        $values = (array) $this->option('domain');

        if (in_array('all', $values, true)) {
            return BusinessDomain::cases();
        }

        $domains = [];

        foreach ($values as $value) {
            $domain = BusinessDomain::tryFrom((string) $value);

            if ($domain === null) {
                $this->error("'{$value}' is not a business domain. One of: "
                    .implode(', ', array_map(fn (BusinessDomain $d): string => $d->value, BusinessDomain::cases()))
                    .', or "all".');

                return false;
            }

            $domains[] = $domain;
        }

        return $domains;
    }

    /**
     * @param  list<BusinessDomain>  $adding
     * @return list<BusinessDomain>
     */
    private function merged(User $user, array $adding): array
    {
        $held = $user->entitledDomains();

        return array_values(array_filter(
            BusinessDomain::cases(),
            fn (BusinessDomain $d): bool => in_array($d, $held, true) || in_array($d, $adding, true),
        ));
    }

    /**
     * @param  list<BusinessDomain>  $domains
     */
    private function describe(array $domains): string
    {
        return implode(', ', array_map(fn (BusinessDomain $d): string => $d->value, $domains));
    }
}
