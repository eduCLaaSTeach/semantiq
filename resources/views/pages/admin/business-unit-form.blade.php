{{--
    ADM-003, create and edit. Page-hosted form, roomy sizing, one column.

    The CODE is set once and then shown read-only. VAL-BU-CODE-001 makes it the
    stable identifier, and a stable identifier that can be edited is not one.

    The parent select excludes this unit and everything beneath it, which is the
    commonest way to create a loop. StructureRegistry checks again on save - a
    filtered select stops a mistake and does nothing about a crafted post.
--}}
@extends('layouts.shell')

@php($editing = $unit !== null)

@section('title', ($editing ? 'Edit business unit' : 'New business unit').' · '.config('app.name'))
@section('page-title', $editing ? $unit->name : 'New business unit')
@section('page-subtitle', $editing ? 'Change this division of the organisation.' : 'Add a division of the organisation.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <form class="card settings-form" method="POST"
              action="{{ $editing ? route('admin.business-units.update', $unit) : route('admin.business-units.store') }}"
              novalidate>
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="settings-fields">
                <div class="field">
                    <label class="field-label" for="code">
                        Code @unless($editing)<span class="field-required" aria-hidden="true">*</span>@endunless
                    </label>
                    <input class="input" type="text" id="code" name="code" maxlength="32"
                           value="{{ old('code', $unit?->code) }}"
                           @if($editing) disabled @endif
                           @error('code') aria-invalid="true" aria-describedby="code-message" @enderror>
                    <p class="field-help">
                        @if ($editing)
                            The code is fixed once a unit exists, because other records refer to it.
                        @else
                            A short stable identifier such as FIN or SALES-APAC. It cannot be changed later.
                        @endif
                    </p>
                    <p class="field-message" id="code-message">@error('code'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="name">
                        Name<span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input class="input" type="text" id="name" name="name"
                           value="{{ old('name', $unit?->name) }}"
                           @error('name') aria-invalid="true" aria-describedby="name-message" @enderror>
                    <p class="field-message" id="name-message">@error('name'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="parent_id">Sits under</label>
                    <select class="input" id="parent_id" name="parent_id">
                        <option value="">Top level</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected((int) old('parent_id', $unit?->parent_id) === $parent->id)>
                                {{ $parent->path() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="field-help">A unit cannot sit under itself or under one of its own sub-units.</p>
                    <p class="field-message" id="parent_id-message">@error('parent_id'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="manager_user_id">Manager</label>
                    <select class="input" id="manager_user_id" name="manager_user_id">
                        <option value="">Not assigned</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((int) old('manager_user_id', $unit?->manager_user_id) === $manager->id)>
                                {{ $manager->name }} ({{ $manager->email }})
                            </option>
                        @endforeach
                    </select>
                    {{-- Said explicitly, because "manager" reads like authority
                         and is not. --}}
                    <p class="field-help">Who is accountable for this unit. This grants them no additional access.</p>
                    <p class="field-message" id="manager_user_id-message">@error('manager_user_id'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="cost_centre">Cost centre</label>
                    <input class="input" type="text" id="cost_centre" name="cost_centre"
                           value="{{ old('cost_centre', $unit?->cost_centre) }}">
                    <p class="field-message" id="cost_centre-message">@error('cost_centre'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="effective_from">Effective from</label>
                    <input class="input" type="date" id="effective_from" name="effective_from"
                           value="{{ old('effective_from', $unit?->effective_from?->toDateString()) }}">
                    <p class="field-message" id="effective_from-message">@error('effective_from'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="effective_to">Effective to</label>
                    <input class="input" type="date" id="effective_to" name="effective_to"
                           value="{{ old('effective_to', $unit?->effective_to?->toDateString()) }}"
                           @error('effective_to') aria-invalid="true" aria-describedby="effective_to-message" @enderror>
                    <p class="field-help">Leave empty while the unit is current.</p>
                    <p class="field-message" id="effective_to-message">@error('effective_to'){{ $message }}@enderror</p>
                </div>

                @if ($editing)
                    <div class="field">
                        <label class="field-label" for="status">Status</label>
                        <select class="input" id="status" name="status">
                            @foreach (\App\Modules\Platform\Enums\LifecycleStatus::forStructure() as $option)
                                <option value="{{ $option->value }}" @selected(old('status', $unit->status->value) === $option->value)>
                                    {{ $option->label() }}
                                </option>
                            @endforeach
                        </select>
                        <p class="field-help">A disabled unit keeps everyone already in it, and takes nobody new.</p>
                        <p class="field-message" id="status-message">@error('status'){{ $message }}@enderror</p>
                    </div>
                @endif
            </div>

            <div class="settings-foot">
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">{{ $editing ? 'Save business unit' : 'Create business unit' }}</span>
                </button>
                <a class="btn btn-secondary" href="{{ route('admin.business-units') }}">
                    <span class="btn-label">Cancel</span>
                </a>
            </div>
        </form>
    </div>
@endsection
