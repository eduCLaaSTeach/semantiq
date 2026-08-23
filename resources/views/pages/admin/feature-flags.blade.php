{{--
    ADM-021 Feature Flags - MENU_STRUCTURE.md section 12.15.

    Simple table tier: one row per declared flag, each with its own small form
    so a toggle is one action and one audit event rather than a bulk save whose
    trail cannot say which switch the reason belonged to.

    The note at the top is load-bearing, not decoration. A switch labelled
    "sign-in" invites the reading that it grants or denies access, and it does
    not: the tier, the permission and the domain entitlement do that, and none
    of them consult a flag.
--}}
@extends('layouts.shell')

@section('title', 'Feature Flags · '.config('app.name'))
@section('page-title', 'Feature Flags')
@section('page-subtitle', 'Which optional capabilities are switched on for this instance.')

@section('content')
    <div class="stack">

        @if (session('status'))
            <div class="alert alert-success" role="status">
                <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @error('flag')
            <div class="alert" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
                <span>{{ $message }}</span>
            </div>
        @enderror

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
            <span>
                A flag decides whether a capability is available. It never decides who may
                use it - that stays with the platform role, the permission and the business
                domain entitlement.
            </span>
        </div>

        <div class="card">
            <div class="table-scroll">
                <table class="data-table">
                    <caption class="visually-hidden">Feature flags and their current state</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="col-primary">Capability</th>
                            <th scope="col">State</th>
                            <th scope="col">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($flags as $key => $flag)
                            <tr>
                                <th scope="row" class="cell-heading">
                                    {{ $flag['definition']['label'] }}
                                    <span class="cell-note">{{ $flag['definition']['help'] }}</span>
                                    <span class="cell-note cell-reference">{{ $key }}</span>
                                </th>
                                <td>
                                    <span class="badge {{ $flag['enabled'] ? 'badge-success' : '' }}">
                                        {{ $flag['enabled'] ? 'On' : 'Off' }}
                                    </span>
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('admin.system.feature-flags.update', ['key' => $key]) }}"
                                          class="row-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="enabled" value="{{ $flag['enabled'] ? '0' : '1' }}">

                                        <label class="field-label visually-hidden" for="reason-{{ $loop->index }}">
                                            Reason for changing {{ $flag['definition']['label'] }}
                                        </label>
                                        <input class="input"
                                               type="text"
                                               id="reason-{{ $loop->index }}"
                                               name="reason"
                                               maxlength="200"
                                               placeholder="Reason (optional)">

                                        {{-- A table never gets a button style of
                                             its own: the neutral secondary look,
                                             icon plus its word. --}}
                                        <button type="submit" class="btn btn-secondary btn-small" data-async>
                                            <svg class="icon" aria-hidden="true"><use href="#i-toggle"/></svg>
                                            <span class="btn-label">Turn {{ $flag['enabled'] ? 'off' : 'on' }}</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
