import { Link, usePage } from '@inertiajs/react'

/**
 * The shared Pattern B strip, unchanged. Using .org-tabs means the G2
 * correction - wrapping below 640px rather than clipping behind a hidden
 * scrollbar - applies here without anything being re-decided.
 *
 * The counts are THE VIEWER'S OWN, from their own projection. A deployment
 * total would disclose how much evidence exists beyond what this person may
 * read, which is the disclosure D-108 exists to prevent.
 */
const TABS = [
    { key: 'user_access', label: 'User Access', href: '/console/audit' },
    { key: 'admin_changes', label: 'Admin Changes', href: '/console/audit/admin-changes' },
    { key: 'security_events', label: 'Security Events', href: '/console/audit/security-events' },
    { key: 'configuration_changes', label: 'Configuration Changes', href: '/console/audit/configuration' },
]

export default function AuditTabs({ counts }) {
    const { url } = usePage()
    const path = url.split('?')[0]

    return (
        <nav className="org-tabs" aria-label="Audit sections">
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
                                {count > 0 ? ` (${count})` : ''}
                            </Link>
                        </li>
                    )
                })}
            </ul>
        </nav>
    )
}
