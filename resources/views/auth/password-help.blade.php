{{--
    Password help.

    Not a reset form. Identity is federated, so a directory account's password
    lives in the directory and is reset there. A form here would imply this
    application holds a password it does not own.
--}}
@extends('layouts.auth')

@section('title', 'Password help · '.config('app.name'))

@section('content')
    <div class="card auth-card">
        <div class="auth-heading">
            <h1>Password help</h1>
        </div>

        <p style="color:var(--text-muted)">
            If you sign in with Microsoft, your password belongs to your organisation's
            directory and is reset there, not in {{ config('app.name') }}. Use the
            <strong>Sign in with Microsoft</strong> button and follow your organisation's
            reset process.
        </p>

        <p style="color:var(--text-muted)">
            If you were given an email and password for {{ config('app.name') }} itself,
            ask an administrator to reset it for you.
        </p>

        <a class="btn btn-secondary btn-block" href="{{ route('sign-in') }}">Back to sign in</a>
    </div>
@endsection
