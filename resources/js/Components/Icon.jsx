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
 * ONE GLYPH, ONE CONCEPT. Two menu entries that mean different things get
 * different glyphs, which is why Workplace Home, Fabric Overview and
 * Administration Home are i-home, i-gauge and i-grid rather than three
 * variations on a house.
 */
/*
 * THIRD-PARTY BRAND MARKS.
 *
 * Not UI icons, and deliberately not in the outline style: Microsoft's brand
 * guidance requires its own mark, in its own colours, on a "sign in with
 * Microsoft" control, and a logo is used as supplied or not at all. They live
 * in THIS registry so there is still exactly one SVG source in the app - the
 * standard's rule is one registry, and an ad-hoc <svg> dropped into a component
 * is what it forbids.
 *
 * A key may only appear here if it is a real third-party brand mark. Everything
 * else belongs in GLYPHS, in the approved style.
 */
export const BRAND_MARKS = ['i-microsoft']

const GLYPHS = {
    // -- Third-party brand mark (see BRAND_MARKS above) -------------------
    'i-microsoft': (
        <>
            <path fill="#F25022" stroke="none" d="M3 3h8.4v8.4H3z" />
            <path fill="#7FBA00" stroke="none" d="M12.6 3H21v8.4h-8.4z" />
            <path fill="#00A4EF" stroke="none" d="M3 12.6h8.4V21H3z" />
            <path fill="#FFB900" stroke="none" d="M12.6 12.6H21V21h-8.4z" />
        </>
    ),

    // -- Chrome ----------------------------------------------------------
    'i-panel': (<><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M9 4v16" /></>),
    'i-chevron-down': <path d="M6 9l6 6 6-6" />,
    'i-sun': (<><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></>),
    'i-moon': <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z" />,
    'i-monitor': (<><rect x="2" y="4" width="20" height="13" rx="2" /><path d="M8 21h8M12 17v4" /></>),
    'i-logout': (<><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5M21 12H9" /></>),

    // -- SemantIQ Workplace ----------------------------------------------
    'i-home': (<><path d="M3 10l9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><path d="M9 22V12h6v10" /></>),
    'i-brain': (<><path d="M12 4a3 3 0 0 0-3 3 3 3 0 0 0-1 5.8V16a3 3 0 0 0 4 2.8" /><path d="M12 4a3 3 0 0 1 3 3 3 3 0 0 1 1 5.8V16a3 3 0 0 1-4 2.8" /><path d="M12 4v16" /></>),
    'i-crown': (<><path d="M3 7l4 4 5-7 5 7 4-4v10H3z" /><path d="M3 20h18" /></>),
    'i-trending-up': (<><path d="M3 17l6-6 4 4 8-8" /><path d="M14 7h7v7" /></>),
    'i-coins': (<><ellipse cx="12" cy="6" rx="8" ry="3" /><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6" /><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" /></>),
    'i-id-badge': (<><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M9 3h6v3H9z" /><circle cx="12" cy="12" r="2" /><path d="M8.5 18a3.5 3.5 0 0 1 7 0" /></>),
    'i-cog': (<><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1" /></>),
    'i-smile': (<><circle cx="12" cy="12" r="9" /><path d="M8 14a4.5 4.5 0 0 0 8 0" /><path d="M9 9h.01M15 9h.01" /></>),
    'i-book': (<><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22z" /><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20" /></>),
    'i-puzzle': <path d="M10 3h4v2.5a1.5 1.5 0 0 0 3 0V3h4v4h-2.5a1.5 1.5 0 0 0 0 3H21v4h-2.5a1.5 1.5 0 0 0 0 3H21v4h-4v-2.5a1.5 1.5 0 0 0-3 0V21h-4v-4H5.5a1.5 1.5 0 0 0 0-3H3v-4h2.5a1.5 1.5 0 0 0 0-3H3V3h4z" />,
    'i-compass': (<><circle cx="12" cy="12" r="9" /><path d="M15.5 8.5l-2 5-5 2 2-5z" /></>),
    'i-message': <path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-5.5A8 8 0 0 1 13 4a8 8 0 0 1 8 8z" />,
    'i-lightbulb': (<><path d="M9 18h6" /><path d="M10 21h4" /><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2h5c0-.8.4-1.5 1-2A6 6 0 0 0 12 3z" /></>),
    'i-alert-triangle': (<><path d="M12 3l9.5 16.5H2.5z" /><path d="M12 10v4M12 17h.01" /></>),
    'i-check-circle': (<><circle cx="12" cy="12" r="9" /><path d="M8.5 12.5l2.5 2.5 4.5-5" /></>),
    'i-bell': (<><path d="M18 9a6 6 0 1 0-12 0c0 5-2 6-2 6h16s-2-1-2-6z" /><path d="M10.5 20a2 2 0 0 0 3 0" /></>),
    'i-chart-pie': (<><path d="M12 3a9 9 0 1 0 9 9h-9z" /><path d="M15 3.5A9 9 0 0 1 20.5 9H15z" /></>),
    'i-briefcase': (<><rect x="2" y="7" width="20" height="14" rx="2" /><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" /><path d="M2 13h20" /></>),
    'i-help': (<><circle cx="12" cy="12" r="9" /><path d="M9.5 9.5a2.5 2.5 0 1 1 3.2 2.4c-.7.2-1.2.9-1.2 1.6v.5" /><path d="M12 17h.01" /></>),

    // -- Fabric Configuration --------------------------------------------
    'i-gauge': (<><path d="M12 14l4-4" /><path d="M3.5 18a9 9 0 1 1 17 0" /><circle cx="12" cy="14" r="1" /></>),
    'i-database': (<><ellipse cx="12" cy="5" rx="8" ry="3" /><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5" /><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" /></>),
    'i-plug': (<><path d="M9 2v6M15 2v6" /><path d="M6 8h12v3a6 6 0 0 1-12 0z" /><path d="M12 17v5" /></>),
    'i-search': (<><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></>),
    'i-tag': (<><path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z" /><path d="M7.5 7.5h.01" /></>),
    'i-download': (<><path d="M12 3v12" /><path d="M7.5 10.5L12 15l4.5-4.5" /><path d="M4 19h16" /></>),
    'i-clipboard-check': (<><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4V3h6v1" /><path d="M9 13l2 2 4-4" /></>),
    'i-cube': (<><path d="M12 2.5l8.5 4.8v9.4L12 21.5 3.5 16.7V7.3z" /><path d="M3.5 7.3L12 12l8.5-4.7M12 12v9.5" /></>),
    'i-shield-check': (<><path d="M12 3l8 3v6c0 5-3.5 8.3-8 9.5-4.5-1.2-8-4.5-8-9.5V6z" /><path d="M9 12l2 2 4-4" /></>),
    'i-share-nodes': (<><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="6" r="2.5" /><circle cx="18" cy="18" r="2.5" /><path d="M8.2 10.8l7.6-3.6M8.2 13.2l7.6 3.6" /></>),
    'i-sparkles': (<><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z" /><path d="M18 16l.8 2.2 2.2.8-2.2.8L18 22l-.8-2.2-2.2-.8 2.2-.8z" /></>),
    'i-refresh': (<><path d="M20 11a8 8 0 0 0-13.6-4.6L3 9" /><path d="M4 13a8 8 0 0 0 13.6 4.6L21 15" /><path d="M3 5v4h4M21 19v-4h-4" /></>),
    'i-upload': (<><path d="M12 15V3" /><path d="M7.5 7.5L12 3l4.5 4.5" /><path d="M4 19h16" /></>),
    'i-activity': <path d="M3 12h4l3 8 4-16 3 8h4" />,

    // -- System Administration -------------------------------------------
    'i-grid': (<><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></>),
    // Organisation: a node above two connected children reads as "structure".
    'i-sitemap': (<><rect x="9" y="3" width="6" height="5" rx="1" /><rect x="3" y="16" width="6" height="5" rx="1" /><rect x="15" y="16" width="6" height="5" rx="1" /><path d="M12 8v4" /><path d="M6 16v-2h12v2" /></>),
    'i-users': (<><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0" /><path d="M17 5.5a3.5 3.5 0 0 1 0 7M18 14.5a6 6 0 0 1 3.5 5.5" /></>),
    'i-key': (<><circle cx="8" cy="12" r="4" /><path d="M12 12h9" /><path d="M17 12v3M20 12v2" /></>),
    'i-layers': (<><path d="M12 3l9 5-9 5-9-5z" /><path d="M3 13l9 5 9-5" /><path d="M3 17.5l9 5 9-5" /></>),
    'i-fingerprint': (<><path d="M6 11a6 6 0 0 1 12 0v2" /><path d="M9 11a3 3 0 0 1 6 0v3a7 7 0 0 1-1 3.5" /><path d="M12 11v4a10 10 0 0 1-1 4" /><path d="M18 15a12 12 0 0 1-1 5" /></>),
    'i-shield': <path d="M12 3l8 3v6c0 5-3.5 8.3-8 9.5-4.5-1.2-8-4.5-8-9.5V6z" />,
    'i-clipboard-list': (<><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4V3h6v1" /><path d="M9 10h6M9 14h6M9 18h3" /></>),
    'i-scroll': (<><path d="M5 4h11a2 2 0 0 1 2 2v12a2 2 0 0 0 2 2H7a2 2 0 0 1-2-2z" /><path d="M9 8h5M9 12h5" /></>),
    'i-heart-pulse': (<><path d="M12 20.5C6.5 17 3 13.8 3 9.8A4.8 4.8 0 0 1 12 7a4.8 4.8 0 0 1 9 2.8c0 4-3.5 7.2-9 10.7z" /><path d="M4 12h3l1.5-2.5L11 15l2-4h4" /></>),
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
