<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name') }}</title>

    {{-- Per-theme favicon, served from public/ rather than through Vite: a favicon
         needs a stable URL and is not a bundled module, so it is not fingerprinted.
         The browser picks by the effective colour scheme, with no JavaScript. --}}
    <link rel="icon" media="(prefers-color-scheme: light)" href="{{ asset('brand/favicon-light.ico') }}">
    <link rel="icon" media="(prefers-color-scheme: dark)" href="{{ asset('brand/favicon-dark.ico') }}">

    {{-- Approved type families, fixed by the design system. Not a per-project choice. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
