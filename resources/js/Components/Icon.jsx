/**
 * THE central icon registry. There is one, and this is it.
 *
 * Per the shared standard: inline SVG, 24px viewBox, 2px stroke, round caps and
 * joins, outline. Symbol ids are i-<concept>. Glyphs render at 1em and are
 * sized with font-size, never by scaling the SVG.
 *
 * Do not mix libraries, add a second icon set, use emoji, or drop an ad-hoc SVG
 * into a component. A genuinely new glyph is added HERE, in this style.
 *
 * Only glyphs actually in use are present. This is a registry, not a library:
 * an unused glyph is dead weight that still has to be maintained in style.
 */
const GLYPHS = {
    // Organisation: the company structure - business units, departments, teams
    // and the hierarchy between them. A node above two connected children reads
    // as "structure" at 18px, which is the size it is actually used at.
    'i-sitemap': (
        <>
            <rect x="9" y="3" width="6" height="5" rx="1" />
            <rect x="3" y="16" width="6" height="5" rx="1" />
            <rect x="15" y="16" width="6" height="5" rx="1" />
            <path d="M12 8v4" />
            <path d="M6 16v-2h12v2" />
        </>
    ),
}

/**
 * A decorative glyph. It carries aria-hidden because the label beside it is the
 * accessible name; an icon-only control needs its own accessible name instead.
 */
export default function Icon({ name, className = '' }) {
    const glyph = GLYPHS[name]

    // An unknown name renders NOTHING. It must never fall back to printing the
    // name, which is exactly how "building" reached the sidebar as visible text.
    if (!glyph) {
        return null
    }

    return (
        <svg
            className={`icon ${className}`.trim()}
            viewBox="0 0 24 24"
            width="1em"
            height="1em"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
        >
            {glyph}
        </svg>
    )
}

/** The registered concepts, for tests and for the registry's own guard. */
export const REGISTERED_ICONS = Object.keys(GLYPHS)
