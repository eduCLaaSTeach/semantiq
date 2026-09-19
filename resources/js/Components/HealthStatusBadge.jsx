/**
 * One health status, as a person reads it.
 *
 * COLOUR IS NEVER THE ONLY SIGNAL. The words are always present and each status
 * carries a distinct mark, so the screen is readable without colour, by
 * somebody who cannot distinguish two of them, and in both themes. WCAG AA
 * 1.4.1 - the same rule PostureBadge already follows.
 *
 * THREE OF THE SIX ARE NEUTRAL, AND THAT IS THE DESIGN.
 *
 *   Not checked      nobody has looked. Green would be a claim; amber would be
 *                    a fault. Neutral is the only honest reading.
 *   Not applicable   cannot apply to this deployment at all.
 *   Not configured   deliberately absent, and correct.
 *
 * Making any of them amber would invite a reader to treat "the scheduler is not
 * configured" as an incident, which is how a health screen teaches people to
 * ignore it.
 *
 * THE CLASS COMES FROM A STATUS THIS COMPONENT WAS GIVEN. It computes nothing
 * and it has no fallback that renders as healthy: an unknown status gets the
 * neutral treatment and a question mark, never a tick.
 */
const WORDS = {
    available: 'Available',
    degraded: 'Needs attention',
    unavailable: 'Unavailable',
    not_configured: 'Not configured',
    not_applicable: 'Not applicable',
    not_checked: 'Not checked',
}

const MARKS = {
    available: '✓',
    degraded: '!',
    unavailable: '!',
    not_configured: '–',
    not_applicable: '–',
    not_checked: '?',
}

export default function HealthStatusBadge({ status }) {
    if (!status) {
        return null
    }

    return (
        <span className={`sys-status sys-status-${status}`}>
            <span className="sys-status-mark" aria-hidden="true">
                {MARKS[status] ?? '?'}
            </span>
            {WORDS[status] ?? 'Not checked'}
        </span>
    )
}
