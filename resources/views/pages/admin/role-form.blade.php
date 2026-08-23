{{--
    ADM-006, create and edit.

    The CODE and the TIER are set once and then read-only, on every role
    including a customer's own. The code is the stable identifier; the tier is
    the ceiling, and a ceiling that can be raised through an edit form is not a
    ceiling. Raising one means creating a different role and moving people to it
    deliberately.
--}}
@extends('layouts.shell')

@php($editing = $role !== null)

@section('title', ($editing ? 'Edit role' : 'New role').' · '.config('app.name'))
@section('page-title', $editing ? $role->name : 'New role')
@section('page-subtitle', $editing ? 'Change this role.' : 'Define a profile of platform authority.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <form class="card settings-form" method="POST"
              action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}"
              novalidate>
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="settings-fields">
                <div class="field">
                    <label class="field-label" for="code">
                        Code @unless($editing)<span class="field-required" aria-hidden="true">*</span>@endunless
                    </label>
                    <input class="input" type="text" id="code" name="code" maxlength="64"
                           value="{{ old('code', $role?->code) }}"
                           @if($editing) disabled @endif
                           @error('code') aria-invalid="true" aria-describedby="code-message" @enderror>
                    <p class="field-help">
                        @if ($editing)
                            The code is fixed. Other records refer to it.
                        @else
                            Lowercase with underscores, such as finance_reviewer. It cannot be changed later.
                        @endif
                    </p>
                    <p class="field-message" id="code-message">@error('code'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="name">
                        Name<span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input class="input" type="text" id="name" name="name"
                           value="{{ old('name', $role?->name) }}"
                           @error('name') aria-invalid="true" aria-describedby="name-message" @enderror>
                    <p class="field-message" id="name-message">@error('name'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="tier">
                        Ceiling @unless($editing)<span class="field-required" aria-hidden="true">*</span>@endunless
                    </label>
                    @if ($editing)
                        <input class="input" type="text" id="tier" value="{{ $role->tier->label() }}" disabled>
                    @else
                        <select class="input" id="tier" name="tier">
                            @foreach ($tiers as $tier)
                                <option value="{{ $tier->value }}" @selected(old('tier') === $tier->value)>{{ $tier->label() }}</option>
                            @endforeach
                        </select>
                    @endif
                    {{-- The sentence that explains why this is not editable. --}}
                    <p class="field-help">
                        The highest authority this role can ever carry. A permission above it is
                        inert, whoever holds the role. It cannot be changed afterwards - raising
                        a ceiling means creating a different role and moving people to it
                        deliberately.
                    </p>
                    <p class="field-message" id="tier-message">@error('tier'){{ $message }}@enderror</p>
                </div>

                <div class="field">
                    <label class="field-label" for="description">Description</label>
                    <input class="input" type="text" id="description" name="description" maxlength="500"
                           value="{{ old('description', $role?->description) }}">
                    <p class="field-help">What this role is for, in the words the people assigning it would use.</p>
                    <p class="field-message" id="description-message">@error('description'){{ $message }}@enderror</p>
                </div>

                @if ($editing)
                    <div class="field">
                        <label class="field-label" for="status">Status</label>
                        <select class="input" id="status" name="status" @if($role->is_system) disabled @endif>
                            @foreach (\App\Modules\Platform\Enums\LifecycleStatus::forStructure() as $option)
                                <option value="{{ $option->value }}" @selected(old('status', $role->status->value) === $option->value)>
                                    {{ $option->label() }}
                                </option>
                            @endforeach
                        </select>
                        <p class="field-help">
                            @if ($role->is_system)
                                A built-in role is part of the access model and cannot be disabled.
                            @else
                                A disabled role keeps its assignments and grants nothing while disabled.
                            @endif
                        </p>
                        <p class="field-message" id="status-message">@error('status'){{ $message }}@enderror</p>
                    </div>
                @endif
            </div>

            <div class="settings-foot">
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">{{ $editing ? 'Save role' : 'Create role' }}</span>
                </button>
                <a class="btn btn-secondary" href="{{ route('admin.roles') }}">
                    <span class="btn-label">Cancel</span>
                </a>
            </div>
        </form>

        @if ($editing && ! $role->is_system)
            <div class="card panel">
                <div class="panel-head">
                    <h2 class="panel-title">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                        Delete this role
                    </h2>
                </div>
                <p class="field-help">
                    A role that is still assigned to somebody cannot be deleted. Remove it from
                    each account first, so every removal is recorded against the person it
                    affected rather than appearing as one deletion.
                </p>
                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary is-danger" data-async>
                        <span class="btn-label">Delete role</span>
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection
