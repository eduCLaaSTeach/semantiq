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
                    {{-- The same grouped select as the General Settings screen,
                         from the same source, so the two cannot disagree about
                         what a valid zone is. --}}
                    <select class="input" id="default_time_zone" name="default_time_zone"
                            @error('default_time_zone') aria-invalid="true" aria-describedby="default_time_zone-message" @enderror>
                        <option value="">Not stated</option>
                        @foreach (\App\Modules\Platform\Support\TimeZones::grouped() as $region => $zones)
                            <optgroup label="{{ $region }}">
                                @foreach ($zones as $identifier => $zoneLabel)
                                    <option value="{{ $identifier }}"
                                        @selected(old('default_time_zone', $organisation->default_time_zone) === $identifier)>{{ $zoneLabel }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="field-help">Where this organisation works. Stored data stays in UTC.</p>
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

                {{-- The privacy contact. SEC-DEC-043, resolved 24 August 2026.

                     Four fields where there was one, and required where it was
                     optional, because the Singapore PDPA expects a designated
                     contact to be reachable and one free-text box could hold a
                     name with no way to reach the person.

                     The banner below appears only where the required parts are
                     still missing, so an organisation saved before this change
                     is told what it now needs BEFORE the save fails rather than
                     by the save failing. --}}
                @if ($privacyContactIncomplete)
                    <div class="alert alert-warning" role="alert">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                        <span>
                            <strong>The privacy contact is incomplete.</strong>
                            A designated contact with a name and an email address is now required.
                            @if ($organisation->privacy_contact)
                                This organisation records
                                <strong>{{ $organisation->privacy_contact }}</strong> from before the
                                field was split up. Check the name below is right and add an email address.
                            @else
                                Nothing is recorded yet.
                            @endif
                            Saving this profile will ask for both.
                        </span>
                    </div>
                @endif

                <div class="field">
                    <label class="field-label" for="privacy_contact_name">Privacy contact name <span class="field-required" aria-hidden="true">*</span></label>
                    <input class="input" type="text" id="privacy_contact_name" name="privacy_contact_name" required
                           value="{{ old('privacy_contact_name', $organisation->privacy_contact_name) }}"
                           @error('privacy_contact_name') aria-invalid="true" aria-describedby="privacy_contact_name-message" @enderror>
                    <p class="field-help">The person or role designated to answer privacy questions. A name or a job title, never a login or a key.</p>
                    <p class="field-message" id="privacy_contact_name-message">@error('privacy_contact_name'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="privacy_contact_email">Privacy contact email <span class="field-required" aria-hidden="true">*</span></label>
                    <input class="input" type="email" id="privacy_contact_email" name="privacy_contact_email" required
                           value="{{ old('privacy_contact_email', $organisation->privacy_contact_email) }}"
                           @error('privacy_contact_email') aria-invalid="true" aria-describedby="privacy_contact_email-message" @enderror>
                    <p class="field-help">Where a data subject or a regulator can reach that person. This address appears in privacy correspondence, so a shared mailbox is usually better than one person's inbox.</p>
                    <p class="field-message" id="privacy_contact_email-message">@error('privacy_contact_email'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="privacy_contact_phone">Privacy contact phone</label>
                    <input class="input" type="text" id="privacy_contact_phone" name="privacy_contact_phone"
                           value="{{ old('privacy_contact_phone', $organisation->privacy_contact_phone) }}"
                           @error('privacy_contact_phone') aria-invalid="true" aria-describedby="privacy_contact_phone-message" @enderror>
                    <p class="field-help">Optional.</p>
                    <p class="field-message" id="privacy_contact_phone-message">@error('privacy_contact_phone'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="privacy_contact_role">Privacy or DPO role or title</label>
                    <input class="input" type="text" id="privacy_contact_role" name="privacy_contact_role"
                           value="{{ old('privacy_contact_role', $organisation->privacy_contact_role) }}"
                           @error('privacy_contact_role') aria-invalid="true" aria-describedby="privacy_contact_role-message" @enderror>
                    <p class="field-help">Optional. For example Data Protection Officer.</p>
                    <p class="field-message" id="privacy_contact_role-message">@error('privacy_contact_role'){{ $message }}@enderror</p>
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
