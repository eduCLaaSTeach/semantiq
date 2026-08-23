{{--
    ADM-004 Teams. MENU_STRUCTURE 12.2.

    A team is a scope, never a permission - the same rule as a business unit.
    Every team shows the unit it belongs to, because VAL-TEAM-BU-001 makes that
    relationship the defining fact about a team rather than an optional detail.
--}}
@extends('layouts.shell')

@section('title', 'Teams · '.config('app.name'))
@section('page-title', 'Teams')
@section('page-subtitle', 'Working teams within your business units.')

@section('page-action')
    <a class="btn btn-solid btn-primary" href="{{ route('admin.teams.create') }}">
        <svg class="icon" aria-hidden="true"><use href="#i-plus-circle"/></svg>
        <span class="btn-label">New team</span>
    </a>
@endsection

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="card">
            @if ($teams->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-users"/></svg>
                    <span class="empty-title">No teams yet</span>
                    <span class="empty-note">
                        A team sits inside one business unit. Create a business unit first if
                        you have not already.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Teams and the business units they belong to</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Team</th>
                                <th scope="col">Business unit</th>
                                <th scope="col">Lead</th>
                                <th scope="col" class="cell-numeric">People</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teams as $team)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        {{ $team->name }}
                                        <span class="cell-note cell-reference">{{ $team->code }}</span>
                                    </th>
                                    <td>{{ $team->businessUnit?->name ?? '-' }}</td>
                                    <td>{{ $team->lead?->name ?? '-' }}</td>
                                    <td class="cell-numeric">{{ $team->members_count }}</td>
                                    <td><span class="{{ $team->status->badgeClass() }}">{{ $team->status->label() }}</span></td>
                                    <td>
                                        <a class="btn btn-secondary btn-small" href="{{ route('admin.teams.edit', $team) }}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-sliders"/></svg>
                                            <span class="btn-label">Edit</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
