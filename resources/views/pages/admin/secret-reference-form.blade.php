{{--
    ADM-012 Secret References - create and edit.

    One template for both, because they differ only in where they post and
    whether a retire action is offered.

    THE FIELD THIS SCREEN IS BUILT AROUND is "Reference identifier", and the
    whole page is arranged to stop somebody pasting a credential into it. The
    help text changes with the secret type so it names what to enter for THIS
    kind of credential, the page says the rule twice in plain words, and the
    form refuses a credential-shaped value with a message rather than storing it.

    Page-hosted form, roomy sizing, one column - the template's forms section.
    Every field is a visible label, the control, its help and a RESERVED
    validation slot.
--}}
@extends('layouts.shell')

@php
    $editing = $reference !== null;
@endphp

@section('title', ($editing ? 'Edit secret reference' : 'New secret reference').' · '.config('app.name'))
@section('page-title', $editing ? 'Edit secret reference' : 'New secret reference')
@section('page-subtitle', 'Record where a credential is kept. Never the credential itself.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
            <span>
                <strong>Do not paste a credential into any field on this page.</strong>
                Record what points AT it - a Key Vault secret name, a certificate thumbprint, an
                environment variable name. A value that looks like a credential is refused and not
                stored, in every field, not only the identifier.
            </span>
        </div>

        <form class="card settings-form"
              method="POST"
              action="{{ $editing ? route('admin.security.secrets.update', $reference) : route('admin.security.secrets.store') }}"
              novalidate>
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="settings-fields">

                <div class="field">
                    <label class="field-label" for="name">
                        Name <span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input class="input" type="text" id="name" name="name"
                           value="{{ old('name', $reference?->name) }}"
                           @error('name') aria-invalid="true" aria-describedby="name-message" @enderror>
                    <p class="field-help">
                        What this credential is, in words somebody on call at 3am would recognise.
                        "Fabric automation client secret" beats "secret 2".
                    </p>
                    <p class="field-message" id="name-message">@error('name'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="reference_type">
                        Secret type <span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <select class="input" id="reference_type" name="reference_type"
                            @error('reference_type') aria-invalid="true" aria-describedby="reference_type-message" @enderror>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}"
                                @selected(old('reference_type', $reference?->reference_type->value) === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="field-help">
                        What kind of credential this points at. It decides what the identifier below
                        should look like.
                    </p>
                    <p class="field-message" id="reference_type-message">@error('reference_type'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="provider">
                        Kept in <span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <select class="input" id="provider" name="provider"
                            @error('provider') aria-invalid="true" aria-describedby="provider-message" @enderror>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->value }}"
                                @selected(old('provider', $reference?->provider->value) === $provider->value)>
                                {{ $provider->label() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="field-help">
                        Where the value actually lives. SemantIQ never contacts any of these - it records
                        where to look, so the person who has to rotate it knows where to go.
                    </p>
                    <p class="field-message" id="provider-message">@error('provider'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="reference_identifier">
                        Reference identifier <span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input class="input" type="text" id="reference_identifier" name="reference_identifier"
                           value="{{ old('reference_identifier', $reference?->reference_identifier) }}"
                           autocomplete="off"
                           @error('reference_identifier') aria-invalid="true" aria-describedby="reference_identifier-message" @enderror>
                    <p class="field-help">
                        The pointer, never the value. What to enter depends on the secret type:
                    </p>
                    <ul class="field-help">
                        @foreach ($types as $type)
                            <li><strong>{{ $type->label() }}:</strong> {{ $type->identifierHint() }}</li>
                        @endforeach
                    </ul>
                    <p class="field-message" id="reference_identifier-message">@error('reference_identifier'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="purpose">
                        What depends on it <span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <textarea class="input" id="purpose" name="purpose" rows="3"
                              @error('purpose') aria-invalid="true" aria-describedby="purpose-message" @enderror>{{ old('purpose', $reference?->purpose) }}</textarea>
                    <p class="field-help">
                        What stops working when this lapses. This is the sentence that decides how urgent
                        an expiry warning is.
                    </p>
                    <p class="field-message" id="purpose-message">@error('purpose'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="environment">
                        Environment <span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input class="input" type="text" id="environment" name="environment"
                           list="environment-options"
                           value="{{ old('environment', $reference?->environment ?? 'production') }}"
                           @error('environment') aria-invalid="true" aria-describedby="environment-message" @enderror>
                    <datalist id="environment-options">
                        @foreach ($environments as $environment)
                            <option value="{{ $environment }}"></option>
                        @endforeach
                    </datalist>
                    <p class="field-help">
                        Which deployment this credential belongs to. A free field with suggestions rather
                        than a fixed list, because which environments exist is a deployment fact and not
                        something this application should decide.
                    </p>
                    <p class="field-message" id="environment-message">@error('environment'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="owner_user_id">Owner</label>
                    <select class="input" id="owner_user_id" name="owner_user_id"
                            @error('owner_user_id') aria-invalid="true" aria-describedby="owner_user_id-message" @enderror>
                        <option value="">Nobody assigned</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}"
                                @selected((string) old('owner_user_id', $reference?->owner_user_id) === (string) $owner->id)>
                                {{ $owner->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="field-help">
                        Who is accountable for rotating it. Optional, but a credential nobody owns is a
                        credential nobody rotates.
                    </p>
                    <p class="field-message" id="owner_user_id-message">@error('owner_user_id'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="expires_on">Expiry date</label>
                    <input class="input" type="date" id="expires_on" name="expires_on"
                           value="{{ old('expires_on', $reference?->expires_on?->toDateString()) }}"
                           @error('expires_on') aria-invalid="true" aria-describedby="expires_on-message" @enderror>
                    <p class="field-help">
                        When the credential stops working. Leaving it blank means nothing will warn anybody
                        before it lapses, and the reference is reported as untracked rather than healthy.
                    </p>
                    <p class="field-message" id="expires_on-message">@error('expires_on'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="rotation_due_on">Rotation due</label>
                    <input class="input" type="date" id="rotation_due_on" name="rotation_due_on"
                           value="{{ old('rotation_due_on', $reference?->rotation_due_on?->toDateString()) }}"
                           @error('rotation_due_on') aria-invalid="true" aria-describedby="rotation_due_on-message" @enderror>
                    <p class="field-help">
                        When somebody should replace it, which should be before it expires. A reminder that
                        arrives after the credential has lapsed is too late to be useful.
                    </p>
                    <p class="field-message" id="rotation_due_on-message">@error('rotation_due_on'){{ $message }}@enderror</p>
                </div>

            </div>

            <div class="settings-foot">
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">{{ $editing ? 'Save changes' : 'Add reference' }}</span>
                </button>
                <a class="btn btn-secondary" href="{{ route('admin.security.secrets') }}">
                    <span class="btn-label">Cancel</span>
                </a>
                <span class="field-help">
                    Recorded in the audit trail with your name against it. No credential value is stored.
                </span>
            </div>
        </form>

        @if ($editing && ! $reference->isRetired())
            <section class="card" aria-labelledby="retire-heading">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="retire-heading">
                        <svg class="icon" aria-hidden="true"><use href="#i-archive"/></svg>
                        Retire this reference
                    </h2>
                </div>
                <p class="field-help">
                    Use this when the credential is no longer in use. The record is KEPT rather than
                    deleted: a credential that used to exist is part of the history an incident review
                    reads, and a deleted row answers no questions. There is deliberately no delete.
                </p>
                <form method="POST" action="{{ route('admin.security.secrets.retire', $reference) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">
                        <svg class="icon" aria-hidden="true"><use href="#i-archive"/></svg>
                        <span class="btn-label">Retire</span>
                    </button>
                </form>
            </section>
        @elseif ($editing)
            <div class="alert" role="note">
                <svg class="icon" aria-hidden="true"><use href="#i-archive"/></svg>
                <span>
                    This reference was retired on {{ $reference->retired_at?->toFormattedDateString() }}.
                    It is kept for the audit trail and no longer counts towards any expiry warning.
                </span>
            </div>
        @endif

    </div>
@endsection
