{{--
    The application's one icon registry.

    A single inline SVG sprite, rendered once per page. Every glyph follows the
    fixed style: 24px viewBox, 2px stroke, round caps and joins, outline, no
    fill. Icons render at 1em and are sized through font-size, never by scaling
    the SVG.

    One glyph, one concept, everywhere. Register a new glyph here in the same
    style before using it; never mix in a second icon set, an emoji, or an
    ad hoc SVG.
--}}
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <defs>
        <g id="i-defaults" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round"></g>
    </defs>

    {{-- Workspace --}}
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
    <symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-map" viewBox="0 0 24 24"><path d="m9 4 6 3 5-3v13l-5 3-6-3-5 3V7Z"/><path d="M9 4v13M15 7v13"/></symbol>

    {{-- Data platform --}}
    <symbol id="i-database" viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></symbol>
    <symbol id="i-server" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/></symbol>
    <symbol id="i-layers" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5Z"/><path d="m3 13 9 5 9-5"/></symbol>
    <symbol id="i-gauge" viewBox="0 0 24 24"><path d="M4 19a9 9 0 1 1 16 0"/><path d="m12 15 4-5"/></symbol>
    <symbol id="i-plug" viewBox="0 0 24 24"><path d="M9 3v6M15 3v6"/><path d="M6 9h12v3a6 6 0 0 1-12 0Z"/><path d="M12 18v3"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24"><path d="M10 13a4 4 0 0 0 6 .5l2-2a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 11a4 4 0 0 0-6-.5l-2 2A4 4 0 0 0 11.7 18l1-1"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 4.4 3 7.9 7 9 4-1.1 7-4.6 7-9V6Z"/></symbol>
    <symbol id="i-cube" viewBox="0 0 24 24"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/></symbol>
    <symbol id="i-stack" viewBox="0 0 24 24"><path d="m12 3 9 4-9 4-9-4Z"/><path d="m3 12 9 4 9-4"/><path d="m3 17 9 4 9-4"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M3 11h18M8 7V4h8v3"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M4 20h16"/></symbol>
    <symbol id="i-flow" viewBox="0 0 24 24"><rect x="3" y="3" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M6 8v6a4 4 0 0 0 4 4h5"/></symbol>
    <symbol id="i-history" viewBox="0 0 24 24"><path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3 4v5h5"/><path d="M12 8v4.5l3 1.8"/></symbol>
    <symbol id="i-wand" viewBox="0 0 24 24"><path d="m5 19 9-9"/><path d="m13 5 1.5 1.5M19 11l1.5 1.5M17 5l-1.5 1.5M17 17l1.5-1.5"/><path d="m14 10 3-3"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m4 12 5 5L20 6"/></symbol>
    <symbol id="i-report" viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6Z"/><path d="M14 3v4h4"/><path d="M9 13h6M9 17h4"/></symbol>

    {{-- Semantics and AI --}}
    <symbol id="i-sparkles" viewBox="0 0 24 24"><path d="m12 3 1.8 4.7L18.5 9.5 13.8 11.3 12 16l-1.8-4.7L5.5 9.5l4.7-1.8Z"/><path d="M18 16.5 18.7 18.3 20.5 19 18.7 19.7 18 21.5 17.3 19.7 15.5 19 17.3 18.3Z"/></symbol>
    <symbol id="i-share" viewBox="0 0 24 24"><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8.3 10.8 7.4-3.6M8.3 13.2l7.4 3.6"/></symbol>
    <symbol id="i-sigma" viewBox="0 0 24 24"><path d="M18 5H6l6 7-6 7h12"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
    <symbol id="i-book" viewBox="0 0 24 24"><path d="M5 4h9a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3Z"/><path d="M17 7h2v13H8"/></symbol>
    <symbol id="i-note" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h4"/></symbol>
    <symbol id="i-robot" viewBox="0 0 24 24"><rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 4v4"/><circle cx="9.5" cy="13" r="1"/><circle cx="14.5" cy="13" r="1"/></symbol>
    <symbol id="i-quote" viewBox="0 0 24 24"><path d="M9 6C6.5 7 5 9 5 12v6h6v-6H8c0-2 .5-3 2-4Z"/><path d="M19 6c-2.5 1-4 3-4 6v6h6v-6h-3c0-2 .5-3 2-4Z"/></symbol>
    <symbol id="i-target" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/></symbol>
    <symbol id="i-play" viewBox="0 0 24 24"><path d="M8 5.5 18 12 8 18.5Z"/></symbol>

    {{-- Delivery --}}
    <symbol id="i-rocket" viewBox="0 0 24 24"><path d="M13 4c4 2 6 5.5 6 10l-4 4-6-6 4-4"/><path d="M9 12 5 8l4-4 4 4"/><path d="M8 16c-1.5 1.5-2 3.5-2 3.5s2-.5 3.5-2"/></symbol>
    <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="12" r="4"/><path d="M12 12h9M17 12v3M20 12v2"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-5 4Z"/></symbol>
    <symbol id="i-signal" viewBox="0 0 24 24"><path d="M5 20v-5M10 20v-9M15 20v-13M20 20V6"/></symbol>
    <symbol id="i-activity" viewBox="0 0 24 24"><path d="M3 12h4l3 7 4-14 3 7h4"/></symbol>

    {{-- Compliance --}}
    <symbol id="i-clipboard" viewBox="0 0 24 24"><rect x="5" y="5" width="14" height="16" rx="2"/><path d="M9 5V3.5h6V5"/><path d="M9 11h6M9 15h4"/></symbol>
    <symbol id="i-tag" viewBox="0 0 24 24"><path d="M4 4h7l9 9-7 7-9-9Z"/><circle cx="8.5" cy="8.5" r="1.2"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 4 21 20H3Z"/><path d="M12 10v4M12 17h.01"/></symbol>
    <symbol id="i-git-branch" viewBox="0 0 24 24"><circle cx="7" cy="6" r="2.5"/><circle cx="7" cy="18" r="2.5"/><circle cx="17" cy="8" r="2.5"/><path d="M7 8.5v7M9.5 7.4h3A2 2 0 0 1 14.5 9.5v.5"/></symbol>
    <symbol id="i-flag" viewBox="0 0 24 24"><path d="M6 21V4"/><path d="M6 5h11l-2 3.5L17 12H6Z"/></symbol>

    {{-- Administration --}}
    <symbol id="i-shield-check" viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 4.4 3 7.9 7 9 4-1.1 7-4.6 7-9V6Z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3.5 3.5 0 0 1 0 6.9M17.5 15A5.5 5.5 0 0 1 21 20"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M9 7V4.5h6V7"/><path d="M6 7v13a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7"/><path d="M10 11v6M14 11v6"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 17V11a6 6 0 1 1 12 0v6l2 2H4Z"/><path d="M10 21h4"/></symbol>
    <symbol id="i-cog" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M4.2 7.5l2.2 1.3M17.6 15.2l2.2 1.3M4.2 16.5l2.2-1.3M17.6 8.8l2.2-1.3"/></symbol>
    <symbol id="i-adjustments" viewBox="0 0 24 24"><path d="M5 6h14M5 12h14M5 18h14"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/></symbol>

    {{-- Shell chrome --}}
    <symbol id="i-panel" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M10 4v16"/></symbol>
    <symbol id="i-chevron-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/></symbol>
    <symbol id="i-monitor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M9 20h6M12 16v4"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></symbol>
    <symbol id="i-log-out" viewBox="0 0 24 24"><path d="M15 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h9"/><path d="M11 12h10"/><path d="m18 9 3 3-3 3"/></symbol>
</svg>
