/**
 * The Organisation tab strip.
 *
 * Shared standard, Pattern B: route-backed links are the default, so each tab
 * is its own deep-linkable URL rendered as a <nav> landmark of real <a href>
 * elements with aria-current="page" on the active one. This is NOT the ARIA tab
 * widget - that is reserved for genuine in-page panels which are not separate
 * routes, and the standard forbids mixing the two on one strip.
 *
 * Being real links is the whole point: the URL changes, browser back and
 * forward behave normally, a refresh keeps the section, and opening a section
 * URL directly selects the right tab. A client-only switch hiding six screens
 * behind one URL would break every one of those.
 *
 * These routes all exist and are all delivered by P1-01. Nothing here is a
 * placeholder, so no tab carries the "Soon" treatment.
 */

const TABS = [
    { label: 'Company Profile', href: '/console/organisation' },
    { label: 'Legal Entities', href: '/console/organisation/legal-entities' },
    { label: 'Business Units', href: '/console/organisation/business-units' },
    { label: 'Departments', href: '/console/organisation/departments' },
    { label: 'Teams', href: '/console/organisation/teams' },
    { label: 'Management Hierarchy', href: '/console/organisation/hierarchy' },
]

/**
 * Which tab owns a path.
 *
 * Company Profile is the exception: every other Organisation URL starts with
 * its path, so a plain "starts with" test would light it up on every screen.
 * It matches only its own exact path; the others also own their detail pages,
 * so Business Unit detail keeps Business Units selected instead of dropping the
 * strip's selection entirely.
 */
export function activeTab(path) {
    const clean = path.replace(/\/+$/, '') || '/'

    const nested = TABS.filter((tab) => tab.href !== '/console/organisation')
        .find((tab) => clean === tab.href || clean.startsWith(`${tab.href}/`))

    if (nested) {
        return nested.href
    }

    return clean === '/console/organisation' ? '/console/organisation' : null
}

export default function OrganisationTabs({ path }) {
    const active = activeTab(path)

    return (
        <nav className="org-tabs" aria-label="Organisation sections">
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
