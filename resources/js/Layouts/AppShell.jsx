import { router } from '@inertiajs/react'
import { useEffect, useState } from 'react'
import BrandMark from '../Components/BrandMark'
import Icon from '../Components/Icon'
import ThemeSwitcher from '../Components/ThemeSwitcher'

/**
 * The authenticated shell: rail, top bar, canvas.
 *
 * Product areas arrive already filtered by the server (HandleInertiaRequests).
 * React never receives a node the viewer may not see, and nothing here decides
 * access: every route inside the authenticated area re-authorises independently.
 *
 * node.icon is a REGISTRY KEY, never text. Icon resolves it to a glyph and
 * renders nothing for an unknown key.
 *
 * A LOCKED node has route === null. It renders as a non-link row carrying the
 * "Soon" pill, is aria-disabled, and is out of the tab order - so it cannot be
 * reached by click, keyboard or URL. There is no route behind it to reach.
 */

function isCurrent(route) {
    if (!route || typeof window === 'undefined') {
        return false
    }

    const path = window.location.pathname

    return path === route || path.startsWith(`${route}/`)
}

function LockedRow({ node, nested = false }) {
    return (
        <div
            className={`shell-link shell-link-locked${nested ? ' shell-link-nested' : ''}`}
            aria-disabled="true"
            aria-label={`${node.label} (not available yet)`}
            title={`${node.label} — not available yet`}
        >
            <Icon name={node.icon} />
            <span className="shell-link-label">{node.label}</span>
            <span className="shell-soon">Soon</span>
        </div>
    )
}

function LinkRow({ node, nested = false }) {
    return (
        <a
            className={`shell-link${nested ? ' shell-link-nested' : ''}`}
            href={node.route}
            aria-current={isCurrent(node.route) ? 'page' : undefined}
            aria-label={node.label}
            title={node.label}
        >
            <Icon name={node.icon} />
            <span className="shell-link-label">{node.label}</span>
        </a>
    )
}

function Group({ node }) {
    const [open, setOpen] = useState(false)

    return (
        <li className="shell-group">
            <button
                type="button"
                className="shell-link shell-group-header"
                aria-expanded={open}
                aria-label={node.label}
                title={node.label}
                onClick={() => setOpen((was) => !was)}
            >
                <Icon name={node.icon} />
                <span className="shell-link-label">{node.label}</span>
                {node.locked ? <span className="shell-soon">Soon</span> : null}
                <Icon name="i-chevron-down" className={`shell-chevron${open ? ' shell-chevron-open' : ''}`} />
            </button>

            {open ? (
                <ul className="shell-node-list shell-child-list">
                    {node.children.map((child) => (
                        <li key={child.label}>
                            {child.route ? <LinkRow node={child} nested /> : <LockedRow node={child} nested />}
                        </li>
                    ))}
                </ul>
            ) : null}
        </li>
    )
}

function Cluster({ area }) {
    // D-18: System Administration opens expanded because it holds the only
    // delivered capability. The other two start collapsed rather than showing
    // a wall of unavailable features on arrival.
    const [open, setOpen] = useState(area.expanded)

    return (
        <div className="shell-area">
            <button
                type="button"
                className="shell-area-label"
                aria-expanded={open}
                onClick={() => setOpen((was) => !was)}
            >
                <span>{area.label}</span>
                <Icon name="i-chevron-down" className={`shell-chevron${open ? ' shell-chevron-open' : ''}`} />
            </button>

            {open ? (
                <ul className="shell-node-list">
                    {area.nodes.map((node) =>
                        node.children && node.children.length > 0 ? (
                            <Group key={node.label} node={node} />
                        ) : (
                            <li key={node.label}>
                                {node.route ? <LinkRow node={node} /> : <LockedRow node={node} />}
                            </li>
                        )
                    )}
                </ul>
            ) : null}
        </div>
    )
}

export default function AppShell({ productAreas = [], title, children }) {
    const [collapsed, setCollapsed] = useState(false)

    // The standard collapses the rail to the 56px icon rail at every
    // breakpoint, so a narrow screen collapses it rather than stacking a
    // full-width menu above the page. Read after mount, because the server has
    // no viewport - and driven through the SAME collapsed state, so there is
    // one rail treatment rather than a second one for small screens.
    useEffect(() => {
        const narrow = window.matchMedia('(max-width: 768px)')
        const sync = (event) => setCollapsed(event.matches)

        setCollapsed(narrow.matches)
        narrow.addEventListener('change', sync)

        return () => narrow.removeEventListener('change', sync)
    }, [])

    return (
        <div className={`shell${collapsed ? ' shell-collapsed' : ''}`}>
            <nav className="shell-rail" aria-label="Primary">
                {/*
                  Collapsed, the head IS the expand control. It used to hide the
                  toggle entirely, which left no way back: a rail of 43
                  unlabelled glyphs and no control to reopen it. Navigation that
                  exists but cannot be reached is not navigation.
                */}
                <div className="shell-rail-head">
                    {collapsed ? (
                        <button
                            type="button"
                            className="shell-rail-expand"
                            aria-expanded="false"
                            onClick={() => setCollapsed(false)}
                        >
                            <BrandMark short />
                            <span className="sr-only">Expand navigation</span>
                        </button>
                    ) : (
                        <>
                            <a className="shell-brand" href="/console" aria-label="SemantIQ home">
                                <BrandMark />
                            </a>

                            <button
                                type="button"
                                className="shell-rail-toggle"
                                aria-expanded="true"
                                onClick={() => setCollapsed(true)}
                            >
                                <Icon name="i-panel" />
                                <span className="sr-only">Collapse navigation</span>
                            </button>
                        </>
                    )}
                </div>

                <div className="shell-rail-body">
                    {productAreas.map((area) => (
                        <Cluster key={area.key} area={area} />
                    ))}
                </div>
            </nav>

            <div className="shell-main">
                <header className="shell-topbar">
                    <span className="shell-app-name">{title ?? 'SemantIQ'}</span>

                    <div className="shell-topbar-actions">
                        <ThemeSwitcher />

                        <button
                            type="button"
                            className="shell-signout"
                            onClick={() => router.post('/auth/logout')}
                        >
                            <Icon name="i-logout" />
                            Sign out
                        </button>
                    </div>
                </header>

                <div className="shell-canvas">
                    {children ?? (
                        <div className="shell-empty">
                            No capability has been enabled for this account yet.
                        </div>
                    )}
                </div>
            </div>
        </div>
    )
}
