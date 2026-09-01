<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title inertia>SemantIQ</title>

    {{-- The approved CLaaS2SaaS favicons, light and dark. The media query picks
         the mark that stays legible against the browser's own tab chrome. --}}
    <link rel="icon" href="/favicon-light.ico" media="(prefers-color-scheme: light)">
    <link rel="icon" href="/favicon-dark.ico" media="(prefers-color-scheme: dark)">

    {{-- Montserrat and Source Sans 3, the families the shared standard fixes. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
{{-- Applied before first paint so a dark-mode reload never flashes light.
     Reads the same key the theme switcher writes; anything else means System. --}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('semantiq.theme')
            if (stored === 'light' || stored === 'dark') {
                document.documentElement.setAttribute('data-theme', stored)
            }
        } catch (e) {
            // Storage can be unavailable (private mode, blocked cookies).
            // System preference is the correct fallback and needs no attribute.
        }
    })()
</script>
<body>
@inertia
</body>
</html>
