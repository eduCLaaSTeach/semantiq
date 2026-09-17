<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

use App\Modules\Security\Catalogue\Control;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Adapters\DomainAdapter;
use App\Modules\Security\Posture\Adapters\SourceAdapter;
use Throwable;

/**
 * THE ONE EVALUATOR. The only thing that produces a PostureRow or a MetricRow -
 * both have private constructors reachable only through the factories this
 * class calls.
 *
 * FAIL CLOSED, EVERYWHERE, WITH THE ROW STILL PRESENT.
 *
 *   an adapter throws            → Unverified, and the row renders
 *   an adapter returns nothing   → Unverified, and the row renders
 *   a value is unrecognised      → Unverified, and NOTHING IS RECORDED
 *   a control has no adapter     → a build failure, never a missing row
 *
 * A ROW IS NEVER OMITTED TO AVOID AN AWKWARD STATE. Omission is the failure
 * that makes a posture screen lie, because the reader counts what they see.
 *
 * THE THROWN VALUE IS DISCARDED, NOT FORMATTED. catch (Throwable) below does
 * not touch getMessage(). An exception message is where a connection string, a
 * table name, a filesystem path or a provider payload would reach a rendered
 * prop - so the sentence a person reads is assembled by Evidence::unavailable()
 * from copy this module owns, and the thrown object goes nowhere.
 *
 * P1-06 EMITS NO SECURITY EVENT, ON ANY PATH INCLUDING THIS ONE.
 * access.state.unrecognised already exists and is emitted by AccessEngine - the
 * engine that makes access decisions. A reporting screen that recorded one
 * would be writing history it has also declared it cannot read. N-SS30 asserts
 * no SecurityEventLogger::record() call from this namespace at all.
 */
final class PostureEvaluator
{
    /** @var list<SourceAdapter> */
    private readonly array $adapters;

    public function __construct(
        private readonly DomainAdapter $domains,
        SourceAdapter ...$adapters,
    ) {
        $this->adapters = array_values($adapters);
    }

    public function evaluate(): PostureReport
    {
        $evidence = $this->gather();

        $rows = [];
        $metrics = [];

        // CATALOGUE ORDER, ALWAYS. Never sorted by state, for any viewer -
        // sorting by severity turns a withheld row's position into its value.
        foreach (ControlCatalogue::all() as $control) {
            $found = $evidence[$control->id] ?? Evidence::unavailable(
                $control->id,
                'Nothing reported on this check.',
            );

            if ($control->kind === ControlKind::InformationalMetric) {
                $metrics[] = MetricRow::make(
                    $control->id,
                    $control->label,
                    $control->scope,
                    $found->count ?? 0,
                    $found->finding,
                );

                continue;
            }

            $rows[] = PostureRow::make(
                $control->id,
                $control->label,
                $control->scope,
                $control->exceptionKind,
                $found->state ?? PostureState::Unverified,
                $found->finding,
                $control->ownerRoute,
                $control->ownerLabel,
            );
        }

        return new PostureReport($rows, $metrics, $this->domainPostures());
    }

    /**
     * Which catalogued controls have an evaluator. Read by N-SS7 so a control
     * added to the catalogue without an adapter fails the build.
     *
     * @return list<string>
     */
    public function answered(): array
    {
        $answered = [];

        foreach ($this->adapters as $adapter) {
            foreach ($adapter->answers() as $control) {
                $answered[] = $control;
            }
        }

        return array_values(array_unique($answered));
    }

    /**
     * Every adapter's evidence, keyed by control. One adapter failing must not
     * take the others with it, so each is caught individually.
     *
     * TWO SOURCES THAT DISAGREE PRODUCE ATTENTION, NAMING BOTH. The first
     * version of this method keyed straight into an array, so the last adapter
     * silently overwrote the first - a silent preference, which is exactly what
     * the fail-closed contract forbids. Two parts of the product that cannot
     * agree about the same fact is a condition worth an administrator's
     * attention WHICHEVER OF THEM IS RIGHT, and picking one quietly is how a
     * screen reports a fact nobody verified.
     *
     * No two Release-1 adapters answer for the same control, and a test asserts
     * that, so this path is unreachable today. It is implemented anyway because
     * the alternative is a silent overwrite the moment somebody adds an
     * overlapping adapter - and the failure would be invisible.
     *
     * @return array<string, Evidence>
     */
    private function gather(): array
    {
        /** @var array<string, list<Evidence>> $collected */
        $collected = [];

        foreach ($this->adapters as $adapter) {
            try {
                foreach ($adapter->evidence() as $item) {
                    $collected[$item->control][] = $item;
                }
            } catch (Throwable) {
                // DELIBERATELY NOT $e->getMessage(). See the class docblock.
                foreach ($adapter->answers() as $control) {
                    if (! isset($collected[$control])) {
                        $collected[$control] = [Evidence::unavailable($control)];
                    }
                }
            }
        }

        $evidence = [];

        foreach ($collected as $control => $items) {
            $evidence[$control] = $this->resolve($control, $items);
        }

        return $evidence;
    }

    /**
     * One control's answer from however many sources offered one.
     *
     * @param  list<Evidence>  $items
     */
    private function resolve(string $control, array $items): Evidence
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $states = array_values(array_unique(array_map(
            static fn (Evidence $item): ?string => $item->state?->value,
            $items,
        )));

        if (count($states) <= 1) {
            // Agreement. Any of them will do.
            return $items[0];
        }

        return Evidence::state(
            $control,
            PostureState::Attention,
            'Two parts of the product disagree about this check: '
            .implode(' ', array_map(
                static fn (Evidence $item): string => rtrim($item->finding, '.').'.',
                $items,
            ))
            .' Until they agree, this cannot be relied on either way.',
        );
    }

    /**
     * Per-domain posture. Each domain is evaluated ON ITS OWN, and a domain
     * that throws becomes one unverified row rather than removing the domain
     * from the list or taking the other domains with it.
     *
     * @return list<DomainPosture>
     */
    private function domainPostures(): array
    {
        try {
            $domains = $this->domains->domains();
        } catch (Throwable) {
            return [];
        }

        $postures = [];

        foreach ($domains as $domain) {
            try {
                $found = $this->domains->evidenceFor($domain);
            } catch (Throwable) {
                $postures[] = DomainPosture::make(
                    (int) $domain->getKey(),
                    (string) $domain->name,
                    $domain->isEnabled(),
                    [PostureRow::make(
                        DomainAdapter::OWNER_MISSING.'#'.$domain->getKey(),
                        'This domain',
                        ControlScope::Organisation,
                        ExceptionKind::VerificationIncomplete,
                        PostureState::Unverified,
                        'This domain could not be checked, so nothing is claimed about it.',
                        null,
                        null,
                        (string) $domain->name,
                    )],
                    [],
                );

                continue;
            }

            $postures[] = DomainPosture::make(
                (int) $domain->getKey(),
                (string) $domain->name,
                $domain->isEnabled(),
                array_map(
                    /*
                     * THE CONTROL ID CARRIES THE DOMAIN. Every domain produces
                     * a row for the same facets, so an unqualified id would
                     * collide the moment two domains exist - one domain's row
                     * would replace another's in any lookup keyed by control,
                     * which is cross-contamination arriving through the back
                     * door rather than through a missing where clause.
                     */
                    static fn (array $row): PostureRow => PostureRow::make(
                        $row[0].'#'.$domain->getKey(),
                        self::domainRowLabel($row[0]),
                        ControlScope::Organisation,
                        ExceptionKind::Unresolved,
                        $row[1],
                        $row[2],
                        'domains.index',
                        'Business Domains',
                        (string) $domain->name,
                    ),
                    $found['rows'],
                ),
                array_map(
                    static fn (array $row): MetricRow => MetricRow::make(
                        $row[0].'#'.$domain->getKey(),
                        self::domainRowLabel($row[0]),
                        ControlScope::Organisation,
                        $row[1],
                        $row[2],
                    ),
                    $found['metrics'],
                ),
            );
        }

        return $postures;
    }

    private static function domainRowLabel(string $key): string
    {
        return match ($key) {
            DomainAdapter::OWNER_MISSING => 'Accountable owner',
            DomainAdapter::PRIVILEGED_GRANTS => 'Administrators with access to it',
            DomainAdapter::INCOMPLETE => 'Access that grants nothing',
            DomainAdapter::ENTITLEMENTS => 'People with access',
            DomainAdapter::BROAD_SCOPES => 'Access covering the whole domain',
            DomainAdapter::RESTRICTED => 'Access to restricted information',

            // Unrecognised fails closed to a readable label rather than
            // printing an internal key at somebody. N-SS33 guards the surface.
            default => 'This domain',
        };
    }

    /** @return Control[] */
    public static function catalogue(): array
    {
        return ControlCatalogue::all();
    }
}
