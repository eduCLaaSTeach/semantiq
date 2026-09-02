/**
 * The Identity & SSO tab strip.
 *
 * Shared standard, Pattern B, exactly as Organisation uses it: route-backed
 * links are the default, so each tab is its own deep-linkable URL rendered as a
 * <nav> landmark of real <a href> elements with aria-current="page" on the
 * active one. Not the ARIA tab widget - that is reserved for genuine in-page
 * panels, and the standard forbids mixing the two on one strip.
 *
 * The strip is deliberately the SAME component shape and the same CSS classes as
 * Organisation's. A second tab strip with its own styles would drift from the
 * first within a unit or two, and then two features would disagree about what a
 * selected tab looks like.
 *
 * The second tab reads "Other Identity Providers". It was "Other Approved IdPs"
 * in the design until the Product Owner replaced the abbreviation: IdP is
 * identity-protocol jargon, and this is a user-facing surface. The route is
 * unchanged.
 */

const TABS = [
    { label: 'Microsoft Entra ID', href: '/console/identity' },
    { label: 'Other Identity Providers', href: '/console/identity/providers' },
    { label: 'Login Experience', href: '/console/identity/login-experience' },
    { label: 'SSO Health', href: '/console/identity/health' },
    { label: 'Session Policy', href: '/console/identity/session-policy' },
]

/**
 * Which tab owns a path.
 *
 * Microsoft Entra ID is the exception, for the same reason Company Profile is in
 * Organisation: every other Identity URL starts with its path, so a plain
 * "starts with" test would light it up on every screen.
 */
export function activeTab(path) {
    const clean = path.replace(/\/+$/, '') || '/'

    const nested = TABS.filter((tab) => tab.href !== '/console/identity')
        .find((tab) => clean === tab.href || clean.startsWith(`${tab.href}/`))

    if (nested) {
        return nested.href
    }

    return clean === '/console/identity' ? '/console/identity' : null
}

export default function IdentityTabs({ path }) {
    const active = activeTab(path)

    return (
        <nav className="org-tabs" aria-label="Identity and SSO sections">
            <ul>
                {TABS.map((tab) => (
                    <li key={tab.href}>
                        <a
                            href={tab.href}
                            className={`org-tab${active === tab.href ? ' org-tab-active' : ''}`}
                            aria-current={active === tab.href ? 'page' : undefined}
                        >
                            {tab.label}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    )
}

export { TABS }
