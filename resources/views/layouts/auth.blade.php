{{--
    The auth layout.

    Sign in, register and reset do not use the application shell. They are
    standalone centered cards sharing only the brand mark, the tokens and the
    fonts, per section 3 and archetype 5.7.

    There is no theme switcher here: the switcher belongs to the shell's top bar,
    which this layout deliberately does not have. The page still honours whatever
    choice was already made, and follows the system preference otherwise.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    {{-- The favicon is a per-theme .ico pair, swapped to the effective theme by
         resources/js/app.js. The light variant is the served default. --}}
    <link rel="icon"
          type="image/x-icon"
          href="{{ asset(config('brand.assets_path').'/favicon-light.ico') }}"
          data-theme-image
          data-light="{{ asset(config('brand.assets_path').'/favicon-light.ico') }}"
          data-dark="{{ asset(config('brand.assets_path').'/favicon-dark.ico') }}">

    {{-- Applied before paint so the correct theme is the first thing rendered.
         Without this the page shows light, then corrects itself once the module
         loads, which reads as a flash of the wrong theme on every visit. --}}
    <script>
        (function () {
            try {
                var choice = localStorage.getItem('semantiq.theme');
                if (choice === 'light' || choice === 'dark') {
                    document.documentElement.setAttribute('data-theme', choice);
                }
            } catch (e) {
                /* Private window or blocked site data: fall through to system. */
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @include('partials.icons')

    <main class="auth">
        <div class="auth-brand">
            {{-- Alt text is fixed by the brand guidance and is not ours to reword. --}}
            <img src="{{ asset(config('brand.assets_path').'/logo-full-light.png') }}"
                 alt="CLaaS2SaaS"
                 data-theme-image
                 data-light="{{ asset(config('brand.assets_path').'/logo-full-light.png') }}"
                 data-dark="{{ asset(config('brand.assets_path').'/logo-full-dark.png') }}">
        </div>

        @yield('content')

        <footer class="auth-footer">
            <span>&copy; {{ date('Y') }} CLaaS2SaaS</span>
            <span>
                <svg class="icon" style="font-size:12px;vertical-align:-2px" aria-hidden="true"><use href="#i-shield"/></svg>
                Authorised access only
            </span>
        </footer>
    </main>
</body>
</html>
