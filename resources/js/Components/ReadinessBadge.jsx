/**
 * One readiness reading, as a person reads it.
 *
 * COLOUR IS NEVER THE ONLY SIGNAL. The word is always present and each tone
 * carries a distinct mark, so the tile is readable without colour, by somebody
 * who cannot distinguish two of them, and in both themes. WCAG AA 1.4.1 - the
 * same rule HealthStatusBadge and PostureBadge already follow.
 *
 * IT COMPUTES NOTHING. The tone and the words both arrive from the server, so
 * this component cannot decide that something is healthy, and there is no
 * fallback that renders as a tick: an unrecognised tone gets the neutral mark.
 *
 * IT BORROWS THE EXISTING PILL rather than introducing a second one. `sys-status`
 * is what System Health and Integrations wear; a readiness reading is a
 * different vocabulary in the same clothes, not a new visual system.
 */
const MARKS = {
    available: '✓',
    degraded: '!',
    not_configured: '–',
}

export default function ReadinessBadge({ badge }) {
    if (!badge) {
        return null
    }

    return (
        <span className={`sys-status sys-status-${badge.tone}`}>
            <span className="sys-status-mark" aria-hidden="true">
                {MARKS[badge.tone] ?? '–'}
            </span>
            {badge.words}
        </span>
    )
}
