@props(['title', 'subtitle' => null, 'trail' => []])

{{--
    The application shell.

    A full-height rail owning the top-left corner, and a column holding the top
    bar and the canvas. The rail and the top bar share one chrome colour; the
    canvas is deliberately a different surface so the working area reads as
    distinct from the chrome.

    Standing navigation lives only in the rail. The top bar carries the app name
    and the utilities and never navigation, a search bar, or action buttons.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ config('app.name', 'SemantIQ') }}</title>

    <link rel="icon" href="{{ asset('brand/favicon-light.ico') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('brand/favicon-dark.ico') }}" media="(prefers-color-scheme: dark)">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    {{--
        The persisted theme and rail state are applied before the first paint,
        so a person who chose dark never sees a flash of the light chrome.
        Inline and synchronous on purpose: deferring it would reintroduce the
        flash it exists to prevent.
    --}}
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('semantiq.theme');
                if (theme === 'light' || theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
                if (localStorage.getItem('semantiq.rail') === 'collapsed') {
                    document.documentElement.classList.add('rail-is-collapsed');
                }
            } catch (e) {
                /* Private mode or blocked storage: fall back to the defaults. */
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/shell.js'])
</head>
<body class="has-shell">
    <x-icon-sprite/>

    <a class="skip-link" href="#main">Skip to content</a>

    {{-- Small screens only: dismisses the off-canvas rail. --}}
    <div class="shell-backdrop" data-shell-backdrop hidden></div>

    <div class="shell">
        <aside class="rail" data-rail aria-label="Main">
            <div class="rail-head">
                <a class="rail-home" href="{{ route('dashboard') }}" aria-label="Home">
                    {{--
                        Two wrappers, one per rail width, each holding the light and
                        dark file. The wrapper decides which mark the width calls for
                        and the theme rules decide which file inside it shows, so the
                        two choices never fight over the same display property.
                    --}}
                    <span class="rail-mark rail-mark-wide">
                        <img class="brand-mark brand-mark-light rail-wordmark"
                             src="{{ asset('brand/logo-full-light.png') }}" alt="CLaaS2SaaS">
                        <img class="brand-mark brand-mark-dark rail-wordmark"
                             src="{{ asset('brand/logo-full-dark.png') }}" alt="CLaaS2SaaS">
                    </span>
                    <span class="rail-mark rail-mark-short">
                        <img class="brand-mark brand-mark-light rail-shortmark"
                             src="{{ asset('brand/logo-short-light.png') }}" alt="CLaaS2SaaS">
                        <img class="brand-mark brand-mark-dark rail-shortmark"
                             src="{{ asset('brand/logo-short-dark.png') }}" alt="CLaaS2SaaS">
                    </span>
                </a>

                {{-- The one and only collapse control. --}}
                <button class="icon-button rail-toggle" type="button"
                        data-rail-toggle aria-expanded="true" aria-controls="rail-nav">
                    <x-icon name="i-panel" label="Collapse the sidebar"/>
                </button>
            </div>

            <div class="rail-filter">
                <label class="sr-only" for="nav-filter">Filter navigation</label>
                <input class="input" id="nav-filter" type="search" placeholder="Filter" data-nav-filter>
            </div>

            <nav class="rail-body" id="rail-nav">
                @foreach ($navigation as $cluster)
                    <div class="nav-cluster" data-nav-cluster>
                        <p class="nav-cluster-label text-micro">{{ $cluster['cluster'] }}</p>
                        @foreach ($cluster['nodes'] as $node)
                            <x-nav-node :node="$node" :depth="0"/>
                        @endforeach
                    </div>
                @endforeach

                {{-- Shown by the filter when nothing matches. --}}
                <p class="nav-empty text-small" data-nav-empty hidden>No navigation matches that.</p>
            </nav>
        </aside>

        <div class="column">
            <header class="topbar">
                <button class="icon-button topbar-rail-open" type="button" data-rail-open>
                    <x-icon name="i-panel" label="Open the sidebar"/>
                </button>

                <p class="topbar-app">{{ config('app.name', 'SemantIQ') }}</p>

                <div class="topbar-utilities">
                    <button class="icon-button" type="button" disabled aria-label="Notifications (not built yet)">
                        <x-icon name="i-bell"/>
                    </button>

                    <div class="menu" data-menu>
                        <button class="icon-button" type="button" data-menu-trigger
                                aria-haspopup="true" aria-expanded="false" aria-label="Theme">
                            <x-icon name="i-sun" class="theme-icon-light"/>
                            <x-icon name="i-moon" class="theme-icon-dark"/>
                        </button>
                        <div class="menu-panel" data-menu-panel hidden role="menu">
                            <button class="menu-item" type="button" role="menuitem" data-theme-set="system">
                                <x-icon name="i-monitor"/> System
                            </button>
                            <button class="menu-item" type="button" role="menuitem" data-theme-set="light">
                                <x-icon name="i-sun"/> Light
                            </button>
                            <button class="menu-item" type="button" role="menuitem" data-theme-set="dark">
                                <x-icon name="i-moon"/> Dark
                            </button>
                        </div>
                    </div>

                    <div class="menu" data-menu>
                        <button class="avatar-button" type="button" data-menu-trigger
                                aria-haspopup="true" aria-expanded="false">
                            <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                            <span class="sr-only">Account: {{ auth()->user()->name }}</span>
                        </button>
                        <div class="menu-panel menu-panel-wide" data-menu-panel hidden role="menu">
                            <div class="menu-heading">
                                <p class="menu-name">{{ auth()->user()->name }}</p>
                                <p class="menu-meta text-xs">{{ auth()->user()->email }}</p>
                                <p class="menu-meta text-xs">{{ auth()->user()->role->label() }}</p>
                            </div>
                            <form method="POST" action="{{ route('sign-out') }}">
                                @csrf
                                <button class="menu-item" type="submit" role="menuitem">
                                    <x-icon name="i-log-out"/> Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="canvas" id="main">
                @if (count($trail) > 1)
                    {{-- The trail is the way back, so there is never a separate back link. --}}
                    <nav class="breadcrumb" aria-label="Breadcrumb">
                        @foreach ($trail as $index => $step)
                            @if ($index > 0)
                                <span class="breadcrumb-sep" aria-hidden="true">/</span>
                            @endif
                            <span @class(['breadcrumb-step', 'is-current' => $loop->last])
                                  @if ($loop->last) aria-current="page" @endif>{{ $step }}</span>
                        @endforeach
                    </nav>
                @endif

                <div class="page-header">
                    <div class="page-header-text">
                        <h1>{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="text-muted">{{ $subtitle }}</p>
                        @endif
                    </div>
                    @isset($actions)
                        <div class="page-header-actions">{{ $actions }}</div>
                    @endisset
                </div>

                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- One host for both live regions, present from page load. --}}
    <div class="toast-host" data-toast-host>
        <div aria-live="polite" aria-atomic="true"></div>
        <div aria-live="assertive" aria-atomic="true"></div>
    </div>
</body>
</html>
