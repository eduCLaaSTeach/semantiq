/**
 * The authenticated shell: rail, top bar, canvas.
 *
 * Product areas arrive already filtered by the server (HandleInertiaRequests).
 * React never receives a node the viewer may not see, so there is nothing here
 * to hide on the client - and nothing here decides access. Every route inside
 * the authenticated area re-authorises independently.
 *
 * An area with no visible nodes is not rendered, which is what keeps Fabric
 * Configuration and SemantIQ Workplace out of the Phase 1 sidebar with no
 * special-casing: they have no nodes yet, so they do not appear.
 */
export default function AppShell({ productAreas = [], title, children }) {
    return (
        <div className="shell">
            <nav className="shell-rail" aria-label="Primary">
                {productAreas.map((area) => (
                    <div key={area.key}>
                        <div className="shell-area-label">{area.label}</div>
                        {area.nodes.map((node) => (
                            <a className="shell-link" key={node.route} href={node.route}>
                                <span aria-hidden="true">{node.icon}</span>
                                {node.label}
                            </a>
                        ))}
                    </div>
                ))}
            </nav>

            <div className="shell-main">
                <header className="shell-topbar">{title ?? 'SemantIQ'}</header>
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
