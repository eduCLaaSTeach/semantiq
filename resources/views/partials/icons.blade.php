{{--
    The application's one inline-SVG icon registry.

    Fixed style throughout: 24px viewBox, 2px stroke, round caps and joins,
    outline. Symbol ids are i-<concept>. One glyph means one concept everywhere,
    and a genuinely new glyph is added here in the same style rather than pulled
    from a second library.

    Only the glyphs the built screens use are registered; the sprite grows with
    the application.
--}}
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <symbol id="i-eye" viewBox="0 0 24 24">
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
        <circle cx="12" cy="12" r="3" />
    </symbol>
    <symbol id="i-eye-off" viewBox="0 0 24 24">
        <path d="M9.9 5.2A9.5 9.5 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.2 6.2A17 17 0 0 0 2 12s3.5 7 10 7a9.5 9.5 0 0 0 4.2-.9" />
        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
        <path d="m3 3 18 18" />
    </symbol>
    <symbol id="i-alert-circle" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 8v4.5" />
        <path d="M12 16h.01" />
    </symbol>
    <symbol id="i-check-circle" viewBox="0 0 24 24">
        <path d="M21 11.1V12a9 9 0 1 1-5.3-8.2" />
        <path d="m9 11 3 3 8.5-8.5" />
    </symbol>
    <symbol id="i-shield" viewBox="0 0 24 24">
        <path d="M12 3l7 3v5.5c0 4.4-3 8.3-7 9.5-4-1.2-7-5.1-7-9.5V6l7-3Z" />
    </symbol>
    <symbol id="i-lock" viewBox="0 0 24 24">
        <rect x="4" y="10.5" width="16" height="10" rx="2" />
        <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
    </symbol>
    <symbol id="i-panel" viewBox="0 0 24 24">
        <rect x="3" y="4" width="18" height="16" rx="2" />
        <path d="M9 4v16" />
    </symbol>
    <symbol id="i-grid" viewBox="0 0 24 24">
        <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
        <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
        <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
        <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
    </symbol>
    <symbol id="i-list-check" viewBox="0 0 24 24">
        <path d="M10 6h11" /><path d="M10 12h11" /><path d="M10 18h11" />
        <path d="m3 6 1.5 1.5L7.5 4.5" />
        <path d="m3 12 1.5 1.5L7.5 10.5" />
        <path d="m3 18 1.5 1.5L7.5 16.5" />
    </symbol>
    <symbol id="i-users" viewBox="0 0 24 24">
        <circle cx="9" cy="8" r="3.5" />
        <path d="M2.5 20a6.5 6.5 0 0 1 13 0" />
        <path d="M16.5 5.2a3.5 3.5 0 0 1 0 6.6" />
        <path d="M18 14.4a6.5 6.5 0 0 1 3.5 5.6" />
    </symbol>
    <symbol id="i-key" viewBox="0 0 24 24">
        <circle cx="8" cy="16" r="4" />
        <path d="m10.8 13.2 8.2-8.2" />
        <path d="m16.5 7.5 2.5 2.5" />
    </symbol>
    <symbol id="i-bell" viewBox="0 0 24 24">
        <path d="M18 9a6 6 0 1 0-12 0c0 5-2 6-2 6h16s-2-1-2-6Z" />
        <path d="M10.3 19a2 2 0 0 0 3.4 0" />
    </symbol>
    <symbol id="i-chevron-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></symbol>
    <symbol id="i-chevron-right" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" /></symbol>
    <symbol id="i-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="6.5" /><path d="m16 16 4.5 4.5" />
    </symbol>
    <symbol id="i-palette" viewBox="0 0 24 24">
        <path d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.6-1.4-.3-.4-.4-.8-.4-1.1 0-.8.7-1.5 1.5-1.5H16a5 5 0 0 0 5-5c0-3.9-4-7-9-7Z" />
        <circle cx="7.5" cy="11" r="1" /><circle cx="10.5" cy="7" r="1" /><circle cx="15" cy="8" r="1" />
    </symbol>
    <symbol id="i-monitor" viewBox="0 0 24 24">
        <rect x="3" y="4" width="18" height="12" rx="2" /><path d="M9 20h6" /><path d="M12 16v4" />
    </symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z" /></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4" />
    </symbol>
    <symbol id="i-log-out" viewBox="0 0 24 24">
        <path d="M9 4.5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h3" />
        <path d="M15.5 15.5 20 12l-4.5-3.5" /><path d="M20 12H9" />
    </symbol>
</svg>
