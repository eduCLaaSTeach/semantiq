{{--
    ADM-005, create and edit the profile.

    Deliberately NOT where access is granted. This form sets who somebody is and
    where they sit; the account page sets what they may do and what they may
    read, in three separate actions with three separate audit events.

    The email and the primary role are set once, at creation. Changing a role
    afterwards is an access decision with its own invariants - the last System
    Administrator among them - and it belongs on the account page where those
    checks and that audit event live, not buried in a profile save.
--}}
@extends('layouts.shell')

@php($editing = $user !== null)

@section('title', ($editing ? 'Edit account' : 'New account').' · '.config('app.name'))
@section('page-title', $editing ? $user->name : 'New account')
@section('page-subtitle', $editing ? 'Who this person is and where they sit.' : 'Invite somebody to SemantIQ.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <form class="card settings-form" method="POST"
              action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
              novalidate>
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="settings-fields">
                <div class="field">
                    <label class="field-label" for="name">
                        Display name<span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input class="input" type="text" id="name" name="name"
                           value="{{ old('name', $user?->name) }}"
                           @error('name') aria-invalid="true" aria-describedby="name-message" @enderror>
                    <p class="field-message" id="name-message">@error('name'){{ $message }}@enderror</p>
                </div>

                @unless ($editing)
                    <div class="field">
                        <label class="field-label" for="email">
                            Work email<span class="field-required" aria-hidden="true">*</span>
                        </label>
                        <input class="input" type="email" id="email" name="email" inputmode="email"
                               value="{{ old('email') }}"
                               @error('email') aria-invalid="true" aria-describedby="email-message" @enderror>
                        <p class="field-help">Their sign-in identity. It must be unique and cannot be changed here afterwards.</p>
                        <p class="field-message" id="email-message">@error('email'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="authentication_source">
                            Signs in with<span class="field-required" aria-hidden="true">*</span>
                        </label>
                        <select class="input" id="authentication_source" name="authentication_source">
                            <option value="entra" @selected(old('authentication_source', 'entra') === 'entra')>Microsoft Entra</option>
                            <option value="local" @selected(old('authentication_source') === 'local')>A password held here</option>
                        </select>
                        {{-- Microsoft first and defaulted: identity is federated,
                             and a password held here is a password worth
                             attacking. --}}
                        <p class="field-help">Microsoft is the intended route. A local password is a fallback for people your directory does not hold.</p>
                        <p class="field-message" id="authentication_source-message">@error('authentication_source'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="role">
                            Role<span class="field-required" aria-hidden="true">*</span>
                        </label>
                        <select class="input" id="role" name="role">
                            @foreach ($tiers as $tier)
                                <option value="{{ $tier->value }}" @selected(old('role', \App\Enums\Role::default()->value) === $tier->value)>
                                    {{ $tier->label() }}
                                </option>
                            @endforeach
                        </select>
                        {{-- The sentence the whole access model rests on. --}}
                        <p class="field-help">
                            What they may do to the platform. It grants no business information at all -
                            Sales, Finance, People and the rest are granted separately on the account page.
                        </p>
                        <p class="field-message" id="role-message">@error('role'){{ $message }}@enderror</p>
                    </div>
                @endunless

                <div class="field">
                    <label class="field-label" for="user_type">
                        Account type<span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <select class="input" id="user_type" name="user_type">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(old('user_type', $user?->user_type->value ?? 'internal') === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="field-help">For reporting and access reviews. It grants nothing.</p>
                    <p class="field-message" id="user_type-message">@error('user_type'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="business_unit_id">Business unit</label>
                    <select class="input" id="business_unit_id" name="business_unit_id">
                        <option value="">Not assigned</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" @selected((int) old('business_unit_id', $user?->business_unit_id) === $unit->id)>
                                {{ $unit->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="field-message" id="business_unit_id-message">@error('business_unit_id'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="team_id">Team</label>
                    <select class="input" id="team_id" name="team_id">
                        <option value="">Not assigned</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected((int) old('team_id', $user?->team_id) === $team->id)>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="field-message" id="team_id-message">@error('team_id'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="external_reference_id">Employee reference</label>
                    <input class="input" type="text" id="external_reference_id" name="external_reference_id"
                           value="{{ old('external_reference_id', $user?->external_reference_id) }}">
                    <p class="field-help">Your own staff or contractor number, if you use one.</p>
                    <p class="field-message" id="external_reference_id-message">@error('external_reference_id'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="access_start">Access starts</label>
                    <input class="input" type="date" id="access_start" name="access_start"
                           value="{{ old('access_start', $user?->access_start?->toDateString()) }}">
                    <p class="field-message" id="access_start-message">@error('access_start'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="access_end">Access ends</label>
                    <input class="input" type="date" id="access_end" name="access_end"
                           value="{{ old('access_end', $user?->access_end?->toDateString()) }}"
                           @error('access_end') aria-invalid="true" aria-describedby="access_end-message" @enderror>
                    {{-- A promise the system keeps without anybody remembering. --}}
                    <p class="field-help">After this date they cannot sign in, whatever else is set. Leave empty for no end date.</p>
                    <p class="field-message" id="access_end-message">@error('access_end'){{ $message }}@enderror</p>
                </div>
            </div>

            <div class="settings-foot">
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">{{ $editing ? 'Save account' : 'Create account' }}</span>
                </button>
                <a class="btn btn-secondary" href="{{ $editing ? route('admin.users.show', $user) : route('admin.users') }}">
                    <span class="btn-label">Cancel</span>
                </a>
            </div>
        </form>
    </div>
@endsection
