/**
 * The Platform Integrations tab strip.
 *
 * Shared standard, Pattern B, exactly as Organisation uses it: route-backed
 * links are the default, so each tab is its own deep-linkable URL rendered as a
 * <nav> landmark of real <a href> elements with aria-current="page" on the
 * active one. Not the ARIA tab widget - that is reserved for genuine in-page
 * panels, and the standard forbids mixing the two on one strip.
 *
 * SAME COMPONENT SHAPE, SAME CLASSES, NO NEW CSS. `org-tabs` and `org-tab`
 * already carry the browser-tab shape, the 44px touch target, the focus ring,
 * the active fill and the wrap-instead-of-scroll behaviour below 640px that
 * four earlier strips share. A fifth set of styles for this one feature is how
 * two screens come to disagree about what a selected tab looks like.
 *
 * THE LABELS COME FROM THE SERVER, and that is the one deliberate difference
 * from OrganisationTabs. Organisation hard-codes its six because its sections
 * are not an enum. These four ARE - IntegrationFamily::inWords() - and a
 * hard-coded copy here would be a second place the product's name for an
 * integration could drift, which is the drift this correction just undid.
 */

/**
 * Which tab owns a path.
 *
 * Microsoft Entra ID is the exception, for the same reason Company Profile is
 * in Organisation: every other Integrations URL starts with its path, so a
 * plain "starts with" test would light it up on every screen.
 */
export function activeTab(path, tabs) {
    const clean = path.split('?')[0].replace(/\/+$/, '') || '/'
    const root = '/console/integrations'

    const nested = tabs
        .filter((tab) => tab.href !== root)
        .find((tab) => clean === tab.href || clean.startsWith(`${tab.href}/`))

    if (nested) {
        return nested.href
    }

    return clean === root ? root : null
}

export default function IntegrationsTabs({ path, tabs }) {
    const active = activeTab(path, tabs)

    return (
        <nav className="org-tabs" aria-label="Platform integrations sections">
            <ul>
                {tabs.map((tab) => (
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
