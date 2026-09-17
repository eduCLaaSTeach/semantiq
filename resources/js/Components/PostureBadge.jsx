/**
 * One posture state, as a person reads it.
 *
 * COLOUR IS NEVER THE ONLY SIGNAL. The business label is always present as
 * text and each state carries a distinct mark, so the screen is readable
 * without colour, by somebody who cannot distinguish two of them, and in both
 * themes. WCAG AA, 1.4.1 - the same rule the status pills already follow.
 *
 * THE CLASS COMES FROM A STATE THIS COMPONENT WAS GIVEN. It computes nothing.
 * A withheld row has no state at all, so it cannot reach this component and
 * cannot be given a colour - it gets the neutral `sec-withheld` treatment
 * instead, which takes no state input.
 *
 * "Not verified" is deliberately NEUTRAL - never green and never amber. Amber
 * would make it a fault; green would make it a claim. Neutral is the honest
 * reading of "nobody has looked".
 */

const MARKS = {
    critical: '!',
    attention: '!',
    unverified: '?',
    not_applicable: '–',
    healthy: '✓',
}

export default function PostureBadge({ state, label, size = 'normal' }) {
    if (!state) {
        return null
    }

    return (
        <span className={`sec-state sec-state-${state}${size === 'large' ? ' sec-state-large' : ''}`}>
            <span className="sec-state-mark" aria-hidden="true">
                {MARKS[state] ?? '?'}
            </span>
            {label}
        </span>
    )
}
