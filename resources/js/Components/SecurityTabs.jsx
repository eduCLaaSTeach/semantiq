/**
 * The Security Status tab strip.
 *
 * Shared standard, Pattern B, exactly as Organisation and Identity & SSO use
 * it: route-backed links are the default, so each tab is its own deep-linkable
 * URL rendered as a <nav> landmark of real <a href> elements with
 * aria-current="page" on the active one. Not the ARIA tab widget - that is
 * reserved for genuine in-page panels, and the standard forbids mixing the two
 * on one strip.
 *
 * FOUR TABS. D-82 closed the question of a fifth: domain posture is a clearly
 * separated section within Privileged Access Health, because six of its seven
 * facets are access facts and the seventh is accountability for access.
 *
 * The strip is deliberately the SAME component shape and the same CSS classes
 * as the two before it. A third tab strip with its own styles would drift
 * within a unit or two, and then three features would disagree about what a
 * selected tab looks like.
 */

const TABS = [
    { label: 'Secure Baseline', href: '/console/security' },
    { label: 'Privileged Access Health', href: '/console/security/privileged-access' },
    { label: 'Exceptions', href: '/console/security/exceptions' },
    { label: 'Security Events', href: '/console/security/events' },
]

/**
 * Which tab owns a path.
 *
 * Secure Baseline is the exception, for the same reason Company Profile is in
 * Organisation and Microsoft Entra ID is in Identity: every other Security URL
 * starts with its path, so a plain "starts with" test would light it up on
 * every screen.
 */
export function activeTab(path) {
    const clean = path.replace(/\/+$/, '') || '/'

    const nested = TABS.filter((tab) => tab.href !== '/console/security')
        .find((tab) => clean === tab.href || clean.startsWith(`${tab.href}/`))

    if (nested) {
        return nested.href
    }

    return clean === '/console/security' ? '/console/security' : null
}

export default function SecurityTabs({ path, exceptionCount = 0 }) {
    const active = activeTab(path)

    return (
        <nav className="org-tabs" aria-label="Security Status sections">
            <ul>
                {TABS.map((tab) => (
                    <li key={tab.href}>
                        <a
                            href={tab.href}
                            className={`org-tab${active === tab.href ? ' org-tab-active' : ''}`}
                            aria-current={active === tab.href ? 'page' : undefined}
                        >
                            {tab.label}
                            {/*
                              * The count comes from the SAME projection the list
                              * and the badge come from - one derivation, one
                              * number. A tab that counted separately is how a
                              * count and a list come to disagree, and here it
                              * would also be a disclosure channel: a withheld
                              * platform row must never reach this number.
                              */}
                            {tab.href === '/console/security/exceptions' && exceptionCount > 0 ? (
                                <span className="sec-tab-count"> ({exceptionCount})</span>
                            ) : null}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    )
}

export { TABS }
