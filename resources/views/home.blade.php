{{--
    A placeholder landing page so the sign-in redirect lands somewhere real.
    The application shell replaces this; it is not built yet, so this page
    deliberately does not imitate one.
--}}
@extends('layouts.auth')

@section('title', config('app.name'))

@section('content')
    <div class="card auth-card">
        <div class="auth-heading">
            <h1>Signed in</h1>
            <p class="auth-tagline">{{ auth()->user()->email }}</p>
        </div>

        <div class="alert alert-success" role="status">
            <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
            <span>Sign-in works. The application shell is not built yet.</span>
        </div>

        <form method="POST" action="{{ route('sign-out') }}">
            @csrf
            <button type="submit" class="btn btn-secondary btn-block">Sign out</button>
        </form>
    </div>
@endsection
