{{--
    Sign in to SemantIQ.

    The auth archetype (§5.7): a standalone centered card with no shell, sharing
    only the brand mark, the tokens, and the fonts. It loads the design system
    stylesheet but not the React bundle, since nothing on this page needs it.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - {{ config('app.name', 'SemantIQ') }}</title>

    {{-- The favicon follows the effective theme, like the wordmark below. --}}
    <link rel="icon" href="{{ asset('brand/favicon-light.ico') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('brand/favicon-dark.ico') }}" media="(prefers-color-scheme: dark)">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="page">
        <div class="auth">
            <div class="card auth-card">
                {{--
                    Both wordmarks are rendered and one is revealed by the theme, so
                    the switch works for the explicit data-theme choice as well as
                    for the operating system preference. Neither file is altered.
                --}}
                <div class="auth-brand">
                    <img class="brand-mark brand-mark-light" src="{{ asset('brand/logo-full-light.png') }}" alt="CLaaS2SaaS">
                    <img class="brand-mark brand-mark-dark" src="{{ asset('brand/logo-full-dark.png') }}" alt="CLaaS2SaaS">
                </div>

                <div class="stack-tight auth-intro">
                    <h1>Sign in to SemantIQ</h1>
                    <p class="text-muted">
                        SemantIQ uses your organisation's Microsoft account. There is no separate
                        password to remember.
                    </p>
                </div>

                @if ($status)
                    {{-- The outcome of the previous attempt, so a failure explains itself. --}}
                    <div class="notice notice-{{ $status['level'] ?? 'info' }}" role="alert">
                        <p class="notice-title">{{ $status['title'] ?? '' }}</p>
                        <p class="notice-body">{{ $status['body'] ?? '' }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('auth.microsoft.redirect') }}" data-sign-in-form>
                    @csrf

                    <button
                        class="btn btn-solid btn-primary btn-lg auth-submit"
                        type="submit"
                        @disabled(! $configured)
                    >
                        {{-- Microsoft's mark, unmodified and in colour. --}}
                        <svg class="icon ms-mark" viewBox="0 0 23 23" aria-hidden="true" focusable="false">
                            <rect x="1" y="1" width="10" height="10" fill="#F25022"/>
                            <rect x="12" y="1" width="10" height="10" fill="#7FBA00"/>
                            <rect x="1" y="12" width="10" height="10" fill="#00A4EF"/>
                            <rect x="12" y="12" width="10" height="10" fill="#FFB900"/>
                        </svg>
                        <span data-label>Sign in with Microsoft</span>
                    </button>
                </form>

                @unless ($configured)
                    {{--
                        The unconfigured state. The button is disabled rather than
                        hidden, so the page reads the same and the reason is stated
                        instead of leaving a dead control with no explanation.
                    --}}
                    <p class="text-small text-muted auth-unconfigured">
                        Sign-in is unavailable until an administrator registers this environment
                        with Microsoft Entra ID.
                    </p>
                @endunless

                <p class="text-xs text-muted auth-footer">
                    Access is granted by your organisation's directory. SemantIQ never sees or
                    stores your password.
                </p>
            </div>
        </div>
    </main>

    <script>
        /*
         * The loading state for the one action on this page. The button keeps its
         * width and swaps its label for a spinner (§8 Buttons), and the form is
         * guarded against a double submit, which would mint a second sign-in
         * attempt and invalidate the first.
         */
        document.querySelector('[data-sign-in-form]')?.addEventListener('submit', function (event) {
            var button = event.currentTarget.querySelector('button[type="submit"]');

            if (!button || button.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }

            button.setAttribute('aria-busy', 'true');
        });
    </script>
</body>
</html>
