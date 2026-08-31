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
 *
 * node.icon is a REGISTRY KEY, never text. Rendering it directly is what put
 * the word "building" in the sidebar; Icon resolves the key to a glyph and
 * renders nothing at all for an unknown one.
 */
import Icon from '../Components/Icon'

export default function AppShell({ productAreas = [], title, children }) {
    return (
        <div className="shell">
            <nav className="shell-rail" aria-label="Primary">
                {productAreas.map((area) => (
                    <div className="shell-area" key={area.key}>
                        <h2 className="shell-area-label">{area.label}</h2>

                        <ul className="shell-node-list">
                            {area.nodes.map((node) => (
                                <li key={node.route}>
                                    <a
                                        className="shell-link"
                                        href={node.route}
                                        aria-current={
                                            typeof window !== 'undefined' &&
                                            window.location.pathname.startsWith(node.route)
                                                ? 'page'
                                                : undefined
                                        }
                                    >
                                        <Icon name={node.icon} />
                                        <span className="shell-link-label">{node.label}</span>
                                    </a>
                                </li>
                            ))}
                        </ul>
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
