import PostureBadge from './PostureBadge'

/**
 * A list of posture rows, valued and withheld together, IN THE ORDER GIVEN.
 *
 * THE ORDER IS NEVER CHANGED HERE. No sort, no "problems first", no grouping by
 * state. The server sends catalogue order and this renders catalogue order,
 * because sorting by severity would turn a withheld row's POSITION into its
 * value - the leak channel most likely to arrive as a well-meaning improvement.
 *
 * A WITHHELD ROW HAS NO STATE, so no badge is rendered for it and no
 * state-derived class is computed. Its sentence is a constant the server sends,
 * identical for every withheld row and every underlying value.
 */
export default function PostureRows({ rows, qualify = false }) {
    if (!rows.length) {
        return null
    }

    return (
        <ul className="sec-rows">
            {rows.map((row) => (
                <li key={row.control} className={`sec-row${row.withheld ? ' sec-row-withheld' : ''}`}>
                    <div className="sec-row-head">
                        {/*
                          * A per-domain row's label is its facet - "Accountable
                          * owner" - which reads correctly under a heading naming
                          * the domain and means nothing in the Exceptions list.
                          * Exceptions asks for the qualifier; the domain section
                          * leaves it out rather than printing the name twice.
                          */}
                        <span className="sec-row-label">
                            {qualify && row.qualifier ? `${row.qualifier} — ${row.label}` : row.label}
                        </span>
                        {row.withheld ? (
                            <span className="sec-withheld">Managed by the platform administrator</span>
                        ) : (
                            <PostureBadge state={row.state} label={row.stateLabel} />
                        )}
                    </div>

                    <p className="sec-row-finding">{row.finding}</p>

                    {/*
                      * Remediation is NAVIGATION, never an action. The link goes
                      * to the screen that owns the control, and that screen
                      * re-authorises on arrival. A withheld row carries no link
                      * at all - WithheldRow has no field for one.
                      */}
                    {row.ownerHref ? (
                        <a className="sec-row-owner" href={row.ownerHref}>
                            Managed in {row.ownerLabel}
                        </a>
                    ) : null}
                </li>
            ))}
        </ul>
    )
}
