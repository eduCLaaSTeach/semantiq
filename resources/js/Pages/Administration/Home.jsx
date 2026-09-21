import AppShell from '../../Layouts/AppShell'
import HealthStatusBadge from '../../Components/HealthStatusBadge'
import PostureBadge from '../../Components/PostureBadge'
import ReadinessBadge from '../../Components/ReadinessBadge'

/**
 * Administration Home. THE ONLY THING ON THIS SCREEN THAT IS THIS UNIT'S IS
 * THE ARRANGEMENT.
 *
 * Every number, word and status here was decided by the unit that owns the
 * fact. This component renders what it was given; it computes no state, derives
 * no verdict, and has no fallback that reads as good news.
 *
 * IT IS A DASHBOARD, AND IT IS NOT GIVEN THE TAB PATTERN. Organisation and
 * Integrations use Pattern B because they have sections you switch between.
 * This has areas you read at once, so forcing a strip onto it would hide
 * three-quarters of the page behind a control nobody needed.
 *
 * THREE STATES THAT NEVER COLLAPSE - D-139.
 *
 *   valued       the numbers are real, and 0 is one of them
 *   withheld     this viewer may not be told. NO NUMBER AT ALL
 *   unavailable  the source could not answer. NO NUMBER AT ALL
 *
 * A tile in either of the last two carries no `metrics` array from the server,
 * so there is nothing here to render a number from even by accident - the same
 * reasoning that keeps a secret out of IntegrationView.
 *
 * NO PERSON, NO DOMAIN RECORD, NO CREDENTIAL. The tile payload has no field any
 * of them could occupy.
 */

function Badge({ badge }) {
    if (!badge) {
        return null
    }

    // Two vocabularies, two existing treatments, and the SERVER says which.
    // A readiness word wears the sys-status pill; a posture state wears the
    // sec-state pill it wears on Security Status. Neither is chosen here.
    return badge.kind === 'posture' ? (
        <PostureBadge state={badge.tone} label={badge.words} />
    ) : (
        <ReadinessBadge badge={badge} />
    )
}

function Tile({ tile }) {
    const quiet = tile.state !== 'valued'

    return (
        <section className="adm-tile" aria-labelledby={`adm-tile-${tile.key}`}>
            <div className="adm-tile-head">
                <h4 id={`adm-tile-${tile.key}`}>{tile.name}</h4>
                <Badge badge={tile.badge} />
            </div>

            {/*
             * A WITHHELD OR UNAVAILABLE TILE SAYS SO IN WORDS, and says so
             * where the numbers would have been. An empty box reads as a bug,
             * and a reader cannot tell one apart from a tile that failed to
             * load.
             */}
            {quiet ? (
                <p className="adm-tile-quiet">
                    {tile.state === 'withheld' ? 'Withheld' : 'Not available'}
                </p>
            ) : null}

            {tile.metrics.length > 0 ? (
                <ul className="sec-metrics adm-tile-metrics">
                    {tile.metrics.map((metric) => (
                        <li key={metric.label} className="sec-metric">
                            <span className="sec-metric-count">{metric.count}</span>
                            <span className="sec-metric-label">{metric.label}</span>
                        </li>
                    ))}
                </ul>
            ) : null}

            {/*
             * Platform Integrations lists its four families rather than
             * collapsing them. D-178: this tile derives nothing, and "one Not
             * applicable and one Unavailable" has no summary that is not a lie.
             */}
            {tile.rows.length > 0 ? (
                <ul className="sys-rows adm-tile-rows">
                    {tile.rows.map((row) => (
                        <li className="sys-row" key={row.name}>
                            <div className="sys-row-head">
                                <span className="sys-row-name">{row.name}</span>
                                <HealthStatusBadge status={row.status} />
                            </div>
                        </li>
                    ))}
                </ul>
            ) : null}

            <p className="adm-tile-note">{tile.note}</p>

            {/*
             * D-145. A link is rendered only where the server sent one, and it
             * sends none the viewer cannot open. Arriving re-authorises anyway:
             * a link is a suggestion, never a grant.
             */}
            {tile.href ? (
                <a className="adm-tile-link" href={tile.href}>
                    {tile.linkLabel}
                </a>
            ) : null}
        </section>
    )
}

export default function Home({ productAreas, areas, actions }) {
    return (
        <AppShell productAreas={productAreas} title="Administration Home">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Administration Home</h1>
                    <p>
                        One place to see whether SemantIQ is set up, secure and working. Everything
                        here is read-only, and every figure comes from the screen that owns it.
                    </p>
                </header>

                {areas.map((area) => (
                    <section
                        className="sys-area"
                        key={area.key}
                        aria-labelledby={`adm-area-${area.key}`}
                    >
                        <div className="sys-area-head">
                            <h3 id={`adm-area-${area.key}`}>{area.name}</h3>
                            <p className="sys-area-description">{area.description}</p>
                        </div>

                        <div className="adm-tiles">
                            {area.tiles.map((tile) => (
                                <Tile key={tile.key} tile={tile} />
                            ))}
                        </div>
                    </section>
                ))}

                <section className="sys-area" aria-labelledby="adm-area-actions">
                    <div className="sys-area-head">
                        <h3 id="adm-area-actions">Action Queue</h3>
                        <p className="sys-area-description">
                            Derived from the areas above, every time this page is opened. Nothing
                            here is assigned to anybody and nothing is stored.
                        </p>
                    </div>

                    {/*
                     * EMPTY IS A REAL STATE AND IS RENDERED AS ONE. It is a
                     * good outcome, and a screen that showed an empty box
                     * instead would leave a reader wondering whether the list
                     * had failed to load.
                     */}
                    {actions.length === 0 ? (
                        <p className="org-empty">Nothing needs your attention.</p>
                    ) : (
                        <ul className="adm-queue">
                            {actions.map((action) => (
                                <li className="adm-queue-row" key={action.key}>
                                    <span className="adm-queue-sentence">{action.sentence}</span>
                                    <a className="adm-tile-link" href={action.href}>
                                        Open {action.destination}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <p className="sys-note">
                    Nothing on this page changes a setting, starts a check or contacts an outside
                    service. Each screen checks your authority again when you arrive.
                </p>
            </div>
        </AppShell>
    )
}
