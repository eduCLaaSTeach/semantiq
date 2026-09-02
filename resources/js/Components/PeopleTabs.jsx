/**
 * The Users & Groups tab strip.
 *
 * Pattern B, as Organisation and Identity already use: route-backed links in a
 * <nav> landmark, real <a href> elements, aria-current on the active one.
 *
 * Two tabs, and Add is an action on each screen rather than a third tab - D-38.
 * A permanent tab for a form turns a button into navigation.
 */

const TABS = [
    { label: 'Users', href: '/console/people/users' },
    { label: 'Groups', href: '/console/people/groups' },
]

export function activeTab(path) {
    const clean = path.replace(/\/+$/, '') || '/'

    return TABS.find((tab) => clean === tab.href || clean.startsWith(`${tab.href}/`))?.href ?? null
}

export default function PeopleTabs({ path }) {
    const active = activeTab(path)

    return (
        <nav className="org-tabs" aria-label="Users and groups sections">
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
