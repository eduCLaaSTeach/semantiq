{{--
    ADM-004, create and edit.

    The business unit field is required and has no empty option, because
    VAL-TEAM-BU-001 says a team belongs to exactly one. An orphan team is not
    representable here or in the database.
--}}
@extends('layouts.shell')

@php($editing = $team !== null)

@section('title', ($editing ? 'Edit team' : 'New team').' · '.config('app.name'))
@section('page-title', $editing ? $team->name : 'New team')
@section('page-subtitle', $editing ? 'Change this team.' : 'Add a team inside a business unit.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @if ($units->isEmpty())
            <div class="card panel">
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-building"/></svg>
                    <span class="empty-title">No business unit to put a team in</span>
                    <span class="empty-note">
                        Every team belongs to exactly one business unit. Create an active
                        business unit first.
                    </span>
                    <a class="btn btn-secondary btn-small" href="{{ route('admin.business-units.create') }}">
                        <span class="btn-label">New business unit</span>
                    </a>
                </div>
            </div>
        @else
            <form class="card settings-form" method="POST"
                  action="{{ $editing ? route('admin.teams.update', $team) : route('admin.teams.store') }}"
                  novalidate>
                @csrf
                @if ($editing) @method('PUT') @endif

                <div class="settings-fields">
                    <div class="field">
                        <label class="field-label" for="code">
                            Code @unless($editing)<span class="field-required" aria-hidden="true">*</span>@endunless
                        </label>
                        <input class="input" type="text" id="code" name="code" maxlength="32"
                               value="{{ old('code', $team?->code) }}"
                               @if($editing) disabled @endif
                               @error('code') aria-invalid="true" aria-describedby="code-message" @enderror>
                        <p class="field-help">
                            @if ($editing)
                                The code is fixed once a team exists.
                            @else
                                A short stable identifier. It cannot be changed later.
                            @endif
                        </p>
                        <p class="field-message" id="code-message">@error('code'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="name">
                            Name<span class="field-required" aria-hidden="true">*</span>
                        </label>
                        <input class="input" type="text" id="name" name="name"
                               value="{{ old('name', $team?->name) }}"
                               @error('name') aria-invalid="true" aria-describedby="name-message" @enderror>
                        <p class="field-message" id="name-message">@error('name'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="business_unit_id">
                            Business unit<span class="field-required" aria-hidden="true">*</span>
                        </label>
                        {{-- No empty option: a team belongs to exactly one unit. --}}
                        <select class="input" id="business_unit_id" name="business_unit_id"
                                @error('business_unit_id') aria-invalid="true" aria-describedby="business_unit_id-message" @enderror>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" @selected((int) old('business_unit_id', $team?->business_unit_id) === $unit->id)>
                                    {{ $unit->path() }}@if(! $unit->acceptsAssignment()) (disabled)@endif
                                </option>
                            @endforeach
                        </select>
                        <p class="field-help">Moving a team to another unit is recorded separately in the audit trail.</p>
                        <p class="field-message" id="business_unit_id-message">@error('business_unit_id'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="lead_user_id">Team lead</label>
                        <select class="input" id="lead_user_id" name="lead_user_id">
                            <option value="">Not assigned</option>
                            @foreach ($leads as $lead)
                                <option value="{{ $lead->id }}" @selected((int) old('lead_user_id', $team?->lead_user_id) === $lead->id)>
                                    {{ $lead->name }} ({{ $lead->email }})
                                </option>
                            @endforeach
                        </select>
                        <p class="field-help">This grants them no additional access.</p>
                        <p class="field-message" id="lead_user_id-message">@error('lead_user_id'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="description">Description</label>
                        <input class="input" type="text" id="description" name="description" maxlength="500"
                               value="{{ old('description', $team?->description) }}">
                        <p class="field-message" id="description-message">@error('description'){{ $message }}@enderror</p>
                    </div>

                    @if ($editing)
                        <div class="field">
                            <label class="field-label" for="status">Status</label>
                            <select class="input" id="status" name="status">
                                @foreach (\App\Modules\Platform\Enums\LifecycleStatus::forStructure() as $option)
                                    <option value="{{ $option->value }}" @selected(old('status', $team->status->value) === $option->value)>
                                        {{ $option->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="field-help">A disabled team keeps its members and takes nobody new.</p>
                            <p class="field-message" id="status-message">@error('status'){{ $message }}@enderror</p>
                        </div>
                    @endif
                </div>

                <div class="settings-foot">
                    <button type="submit" class="btn btn-solid btn-primary" data-async>
                        <span class="btn-label">{{ $editing ? 'Save team' : 'Create team' }}</span>
                    </button>
                    <a class="btn btn-secondary" href="{{ route('admin.teams') }}">
                        <span class="btn-label">Cancel</span>
                    </a>
                </div>
            </form>
        @endif
    </div>
@endsection
