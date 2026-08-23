{{--
    ADM-010 - confirm your identity before a critical action.

    Archetype 5.7 Auth: a standalone centered card with no shell. It looks like
    the sign-in screen on purpose - the person is being asked the same question
    they were asked at the door, and a prompt for a password that looks like an
    ordinary application screen is exactly the shape of a phishing page.

    TWO PATHS, because a federated account has no password here. A local account
    is asked for its password; a Microsoft account is sent back to Entra with
    prompt=login, and the round trip is the proof. Nothing extra is stored to
    make the second one work.

    The action being confirmed is NAMED. "Confirm your identity" with no reason
    trains people to type their password whenever they are asked.
--}}
@extends('layouts.auth')

@section('title', 'Confirm your identity · '.config('app.name'))

@section('content')
    <div class="card auth-card">
        <div class="auth-heading">
            <h1>Confirm it is you</h1>
            <p class="auth-tagline">
                @if ($action !== null)
                    {{ $action->label() }}
                @else
                    This action needs a second check
                @endif
            </p>
        </div>

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
            <span>
                @if ($action !== null)
                    {{ $action->help() }}
                @endif
                Being signed in is not enough for this one - an unlocked machine is enough to be signed
                in. One confirmation covers further critical actions for {{ $validMinutes }}
                {{ $validMinutes === 1 ? 'minute' : 'minutes' }}.
            </span>
        </div>

        @error('form')
            <div class="alert" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
                <span>{{ $message }}</span>
            </div>
        @enderror

        @if ($usesPassword)
            <form method="POST" action="{{ route('reauthenticate.confirm') }}" class="auth-form" novalidate>
                @csrf

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
                        <button type="button"
                                class="input-affix"
                                data-toggle-password="password"
                                aria-label="Show password"
                                aria-pressed="false">
                            <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                        </button>
                    </div>
                    {{-- Reserved whether or not it holds a message. --}}
                    <p class="field-message" id="password-message">@error('password'){{ $message }}@enderror</p>
                </div>

                <button type="submit" class="btn btn-solid btn-primary btn-block" data-async>
                    <span class="btn-label" style="display:inline-flex;align-items:center;gap:8px">
                        <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                        Confirm
                    </span>
                </button>
            </form>
        @else
            <p class="auth-tagline">
                Your account signs in through Microsoft Entra, so SemantIQ holds no password to check.
                Confirming means signing in with Microsoft once more.
            </p>

            <form method="POST" action="{{ route('sign-in.microsoft') }}">
                @csrf
                <input type="hidden" name="reauthenticate" value="1">
                <button type="submit" class="btn btn-solid btn-primary btn-block" data-async>
                    <span class="btn-label" style="display:inline-flex;align-items:center;gap:8px">
                        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                            <path fill="#F25022" d="M0 0h7.6v7.6H0z"/>
                            <path fill="#7FBA00" d="M8.4 0H16v7.6H8.4z"/>
                            <path fill="#00A4EF" d="M0 8.4h7.6V16H0z"/>
                            <path fill="#FFB900" d="M8.4 8.4H16V16H8.4z"/>
                        </svg>
                        Confirm with Microsoft
                    </span>
                </button>
            </form>
        @endif

        <p class="auth-tagline">
            <a href="{{ route('home') }}">Cancel and go back</a>
        </p>
    </div>
@endsection
