import { Link, usePage } from '@inertiajs/react'

/**
 * The shared Pattern B strip. THE SAME .org-tabs every other feature uses, so
 * the G2 correction - wrapping below 640px instead of clipping behind a hidden
 * scrollbar - applies here without anything being re-decided.
 *
 * The counts are THE VIEWER'S OWN. A deployment total would disclose how much
 * access exists beyond what this person may see.
 *
 * A non-breaking space keeps "(3)" attached to its label: JSX collapses a
 * leading space inside an element, which is how "Exceptions(9)" reached a
 * screen in P1-06.
 */
const TABS = [
    { key: 'privileged', label: 'Privileged Reviews', href: '/console/access-reviews' },
    { key: 'domains', label: 'Domain Reviews', href: '/console/access-reviews/domains' },
    { key: 'overdue', label: 'Overdue Reviews', href: '/console/access-reviews/overdue' },
]

export default function ReviewTabs({ counts }) {
    const { url } = usePage()
    const path = url.split('?')[0]

    return (
        <nav className="org-tabs" aria-label="Access Reviews sections">
            <ul>
                {TABS.map((tab) => {
                    const active = path === tab.href
                    const count = counts?.[tab.key] ?? 0

                    return (
                        <li key={tab.href}>
                            <Link
                                href={tab.href}
                                className={active ? 'org-tab org-tab-active' : 'org-tab'}
                                aria-current={active ? 'page' : undefined}
                            >
                                {tab.label}
                                {count > 0 ? ` (${count})` : ''}
                            </Link>
                        </li>
                    )
                })}
            </ul>
        </nav>
    )
}
