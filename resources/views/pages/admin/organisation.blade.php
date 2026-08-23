{{--
    ADM-002 Organisation Profile. MENU_STRUCTURE 12.2.

    One record, edited in place. No create and no delete: gate 1's bootstrap
    migration created the row because everything else is scoped to it, and
    VAL-ORG-DELETE-001 forbids removing an organisation with dependencies -
    which, by the time anyone can reach this screen, it always has.

    The three contact fields hold a NAME or a ROLE and never a credential. The
    help text says so, because a field called "Security contact" is otherwise an
    invitation to paste something that authenticates.
--}}
@extends('layouts.shell')

@section('title', 'Organisation Profile · '.config('app.name'))
@section('page-title', 'Organisation Profile')
@section('page-subtitle', 'The organisation this SemantIQ instance belongs to.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <form class="card settings-form" method="POST" action="{{ route('admin.organisation.update') }}" novalidate>
            @csrf
            @method('PUT')

            <div class="settings-fields">
                <div class="field">
                    <label class="field-label" for="name">
                        Organisation name<span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input class="input" type="text" id="name" name="name"
                           value="{{ old('name', $organisation->name) }}"
                           @error('name') aria-invalid="true" aria-describedby="name-message" @enderror>
                    <p class="field-help">What people call this organisation. Shown throughout the application.</p>
                    <p class="field-message" id="name-message">@error('name'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="legal_name">Legal name</label>
                    <input class="input" type="text" id="legal_name" name="legal_name"
                           value="{{ old('legal_name', $organisation->legal_name) }}">
                    <p class="field-help">Only if it differs from the name above.</p>
                    <p class="field-message" id="legal_name-message">@error('legal_name'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="registration_number">Registration number</label>
                    <input class="input" type="text" id="registration_number" name="registration_number"
                           value="{{ old('registration_number', $organisation->registration_number) }}">
                    <p class="field-help">Your own company or entity registration reference.</p>
                    <p class="field-message" id="registration_number-message">@error('registration_number'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="primary_country">Primary country</label>
                    <select class="input" id="primary_country" name="primary_country">
                        <option value="">Not stated</option>
                        @foreach ($countries as $code => $label)
                            <option value="{{ $code }}" @selected(old('primary_country', $organisation->primary_country) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    {{-- Honest about what this does and does not settle. --}}
                    <p class="field-help">Used to decide which privacy and data-residency questions apply. It does not by itself answer them.</p>
                    <p class="field-message" id="primary_country-message">@error('primary_country'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="primary_domain">Primary email domain</label>
                    <input class="input" type="text" id="primary_domain" name="primary_domain"
                           value="{{ old('primary_domain', $organisation->primary_domain) }}"
                           @error('primary_domain') aria-invalid="true" aria-describedby="primary_domain-message" @enderror>
                    <p class="field-help">For example example.com. Not a full web address.</p>
                    <p class="field-message" id="primary_domain-message">@error('primary_domain'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="default_time_zone">Default time zone</label>
                    <input class="input" type="text" id="default_time_zone" name="default_time_zone"
                           value="{{ old('default_time_zone', $organisation->default_time_zone) }}"
                           @error('default_time_zone') aria-invalid="true" aria-describedby="default_time_zone-message" @enderror>
                    <p class="field-help">An IANA name such as Asia/Singapore. Stored data stays in UTC.</p>
                    <p class="field-message" id="default_time_zone-message">@error('default_time_zone'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="default_currency">Default currency</label>
                    <input class="input" type="text" id="default_currency" name="default_currency" maxlength="3"
                           value="{{ old('default_currency', $organisation->default_currency) }}"
                           @error('default_currency') aria-invalid="true" aria-describedby="default_currency-message" @enderror>
                    <p class="field-help">A three-letter ISO code such as SGD.</p>
                    <p class="field-message" id="default_currency-message">@error('default_currency'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="data_owner">Data owner</label>
                    <input class="input" type="text" id="data_owner" name="data_owner"
                           value="{{ old('data_owner', $organisation->data_owner) }}">
                    {{-- Load-bearing help text: a field like this is otherwise an
                         invitation to paste something that authenticates. --}}
                    <p class="field-help">The person or role accountable for this organisation's data. A name or a job title, never a login or a key.</p>
                    <p class="field-message" id="data_owner-message">@error('data_owner'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="privacy_contact">Privacy contact</label>
                    <input class="input" type="text" id="privacy_contact" name="privacy_contact"
                           value="{{ old('privacy_contact', $organisation->privacy_contact) }}">
                    <p class="field-help">Who to reach about a privacy question. A name or a role.</p>
                    <p class="field-message" id="privacy_contact-message">@error('privacy_contact'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="security_contact">Security contact</label>
                    <input class="input" type="text" id="security_contact" name="security_contact"
                           value="{{ old('security_contact', $organisation->security_contact) }}">
                    <p class="field-help">Who to reach about a security question. A name or a role.</p>
                    <p class="field-message" id="security_contact-message">@error('security_contact'){{ $message }}@enderror</p>
                </div>
            </div>

            <div class="settings-foot">
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">Save profile</span>
                </button>
                <span class="field-help">
                    Organisation code <strong>{{ $organisation->code }}</strong>, version {{ $organisation->version }}.
                    Changes are recorded in the audit trail.
                </span>
            </div>

            @error('form')
                <div class="alert" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </form>
    </div>
@endsection
