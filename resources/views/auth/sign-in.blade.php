{{--
    SC-SIGNIN - Sign in.

    Archetype 5.7 Auth: a standalone centered card with no shell, carrying the
    brand mark, the SSO button, flash and error display, the credential form and
    a trust footer.

    The two sign-in paths are deliberately ordered. Microsoft is first and takes
    the one solid button because it is how this product is meant to be entered:
    identity is federated, so the directory decides who gets in and the
    organisation's own conditional access applies. The credential form below the
    divider is the fallback for accounts the directory does not hold.

    One solid button in the group, per section 8. The credential form's Sign in
    is therefore the neutral secondary look - not because it matters less on its
    own, but because a group may hold only one solid and Microsoft has it.
--}}
@extends('layouts.auth')

@section('title', 'Sign in · '.config('app.name'))

@section('content')
    <div class="card auth-card">
        <div class="auth-heading">
            <h1>Sign in</h1>
            <p class="auth-tagline">{{ config('app.name') }}</p>
        </div>

        {{-- Flash display. A message the previous request left behind, such as
             having been signed out, shown once above everything else. --}}
        @if (session('status'))
            <div class="alert alert-info" role="status">
                <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- The single-sign-on path. --}}
        <form method="POST" action="{{ route('sign-in.microsoft') }}">
            @csrf
            <button type="submit" class="btn btn-solid btn-primary btn-block" data-async>
                <span class="btn-label" style="display:inline-flex;align-items:center;gap:8px">
                    {{-- Microsoft's own mark, required on this button by their
                         brand guidance. It is a third-party logo rather than an
                         app icon, so it is not in the registry and its colours
                         are Microsoft's, not this palette's. --}}
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                        <path fill="#F25022" d="M0 0h7.6v7.6H0z"/>
                        <path fill="#7FBA00" d="M8.4 0H16v7.6H8.4z"/>
                        <path fill="#00A4EF" d="M0 8.4h7.6V16H0z"/>
                        <path fill="#FFB900" d="M8.4 8.4H16V16H8.4z"/>
                    </svg>
                    Sign in with Microsoft
                </span>
            </button>
        </form>

        <div class="auth-divider">or</div>

        {{-- The credential path. --}}
        <form method="POST" action="{{ route('sign-in.attempt') }}" class="auth-form" novalidate>
            @csrf

            <div class="field">
                <label class="field-label" for="email">
                    Work email<span class="field-required" aria-hidden="true">*</span>
                </label>
                <input class="input"
                       type="email"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       autocomplete="username"
                       inputmode="email"
                       required
                       @error('email') aria-invalid="true" aria-describedby="email-message" @enderror>
                {{-- The slot is reserved whether or not it holds a message, so
                     an appearing error cannot shift the fields below it. --}}
                <p class="field-message" id="email-message">@error('email'){{ $message }}@enderror</p>
            </div>

            <div class="field">
                <label class="field-label" for="password">
                    Password<span class="field-required" aria-hidden="true">*</span>
                </label>
                <div class="input-with-affix">
                    <input class="input"
                           type="password"
                           id="password"
                           name="password"
                           autocomplete="current-password"
                           required
                           @error('password') aria-invalid="true" aria-describedby="password-message" @enderror>
                    {{-- Sanctioned icon-only chrome: a control on the field
                         itself, carrying an accessible name. --}}
                    <button type="button"
                            class="input-affix"
                            data-toggle-password="password"
                            aria-label="Show password"
                            aria-pressed="false">
                        <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                    </button>
                </div>
                <p class="field-message" id="password-message">@error('password'){{ $message }}@enderror</p>
            </div>

            <div class="auth-row">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Keep me signed in
                </label>
                <a href="{{ route('password.request') }}" style="font-size:var(--text-small)">Forgot password?</a>
            </div>

            {{-- The form-foot alert: one persistent message for an error that
                 belongs to no single field, placed beside the submit where the
                 reason is needed while acting on it. Never a list of the field
                 errors, which are already inline. --}}
            @error('form')
                <div class="alert" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            {{-- Neutral secondary: the solid in this group belongs to Microsoft
                 above. Submit is never greyed out - clicking it runs validation. --}}
            <button type="submit" class="btn btn-secondary btn-block" data-async>
                <span class="btn-label" style="display:inline-flex;align-items:center;gap:8px">
                    <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                    Sign in
                </span>
            </button>
        </form>
    </div>
@endsection
