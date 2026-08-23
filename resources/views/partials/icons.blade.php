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
</svg>
