{{--
    The application shell. Every authenticated screen extends this.

    Three regions, and the grid is ENFORCED by section 3: the rail is the left
    column spanning BOTH rows, so it is full height and owns the top-left
    corner, and the top bar spans only the main column - never full width above
    the rail.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon"
          type="image/x-icon"
          href="{{ asset(config('brand.assets_path').'/favicon-light.ico') }}"
          data-theme-image
          data-light="{{ asset(config('brand.assets_path').'/favicon-light.ico') }}"
          data-dark="{{ asset(config('brand.assets_path').'/favicon-dark.ico') }}">

    {{-- Both the theme and the rail's collapsed state are applied before paint.
         Deferring either to the module means a visible flash of the wrong theme
         and a rail that visibly snaps shut on every page load. --}}
    <script>
        (function () {
            var root = document.documentElement;
            try {
                var theme = localStorage.getItem('semantiq.theme');
                if (theme === 'light' || theme === 'dark') root.setAttribute('data-theme', theme);
                if (localStorage.getItem('semantiq.rail') === 'collapsed') {
                    root.classList.add('rail-is-collapsed');
                }
            } catch (e) {
                /* Private window or blocked site data: defaults are correct. */
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/shell.js'])
</head>
<body>
    @include('partials.icons')

    <div class="app-shell">
        @include('shell.rail')
        @include('shell.top-bar')

        <main class="app-main" id="main">
            @include('shell.breadcrumb')

            <div class="page-header">
                <div class="page-title">
                    <h1>@yield('page-title')</h1>
                    @hasSection('page-subtitle')
                        <p class="page-subtitle">@yield('page-subtitle')</p>
                    @endif
                </div>
                @yield('page-action')
            </div>

            @yield('content')
        </main>
    </div>

    {{-- One host holding both live regions, present from page load rather than
         created when the first toast arrives: a live region inserted at the
         same moment as its content is not reliably announced. --}}
    <div class="toast-host">
        <div id="toast-polite" role="status" aria-live="polite" aria-atomic="true"></div>
        <div id="toast-assertive" role="alert" aria-live="assertive" aria-atomic="true"></div>
    </div>
</body>
</html>
