{{--
    ADM-005 User Registry. MENU_STRUCTURE 12.2.

    Standard table tier: this is the one list that genuinely grows, so it
    carries the search and filter bar and numbered pagination the template
    requires, both running over the whole result set with their state in the
    URL query.

    The administrator count in the toolbar is not decoration. VAL-USER-LASTADMIN-001
    refuses to remove the last active System Administrator, and an administrator
    should see that number BEFORE it becomes a refusal rather than discovering
    the invariant by hitting it.
--}}
@extends('layouts.shell')

@section('title', 'Users · '.config('app.name'))
@section('page-title', 'Users')
@section('page-subtitle', 'Every account, what it may do, and what information it may read.')

@section('page-action')
    <a class="btn btn-solid btn-primary" href="{{ route('admin.users.create') }}">
        <svg class="icon" aria-hidden="true"><use href="#i-user-plus"/></svg>
        <span class="btn-label">New account</span>
    </a>
@endsection

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="card">
            {{-- Search and filter, state in the URL so a filtered list can be
                 shared and a refresh keeps it. --}}
            <form class="list-toolbar" method="GET" action="{{ route('admin.users') }}">
                <div class="field">
                    <label class="field-label" for="q">Search</label>
                    <input class="input" type="search" id="q" name="q" value="{{ $search }}"
                           placeholder="Name or email">
                </div>

                <div class="field">
                    <label class="field-label" for="status">Status</label>
                    <select class="input" id="status" name="status">
                        <option value="">Any status</option>
                        @foreach ($statuses as $option)
                            <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary">
                    <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                    <span class="btn-label">Apply</span>
                </button>

                @if ($search !== '' || $status !== '')
                    <a class="btn btn-secondary" href="{{ route('admin.users') }}">
                        <span class="btn-label">Clear</span>
                    </a>
                @endif

                <span class="field-help" style="margin-left:auto">
                    {{ $administratorCount }} active System Administrator{{ $administratorCount === 1 ? '' : 's' }}
                    @if ($administratorCount === 1)
                        {{-- The one number worth a warning colour. --}}
                        <span class="badge badge-warning">The last one cannot be removed</span>
                    @endif
                </span>
            </form>

            @if ($users->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-users"/></svg>
                    <span class="empty-title">{{ $search !== '' || $status !== '' ? 'Nothing matches those filters' : 'No accounts yet' }}</span>
                    <span class="empty-note">
                        @if ($search !== '' || $status !== '')
                            Try a different search, or clear the filters.
                        @else
                            Accounts appear here when somebody is invited, or the first time they
                            sign in through Microsoft.
                        @endif
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">User accounts</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Account</th>
                                <th scope="col">Role</th>
                                <th scope="col">Placement</th>
                                <th scope="col">Type</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $person)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        {{ $person->name }}
                                        <span class="cell-note">{{ $person->email }}</span>
                                    </th>
                                    <td>
                                        {{ $person->role->label() }}
                                        @if ($person->is_auditor)
                                            <span class="cell-note">Auditor</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $person->businessUnit?->name ?? '-' }}
                                        @if ($person->team)
                                            <span class="cell-note">{{ $person->team->name }}</span>
                                        @endif
                                    </td>
                                    <td><span class="{{ $person->user_type->badgeClass() }}">{{ $person->user_type->label() }}</span></td>
                                    <td>
                                        <span class="{{ $person->status->badgeClass() }}">{{ $person->status->label() }}</span>
                                        @if ($person->accessWindowHasClosed())
                                            <span class="cell-note">Access ended {{ $person->access_end->toFormattedDateString() }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a class="btn btn-secondary btn-small" href="{{ route('admin.users.show', $person) }}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                                            <span class="btn-label">Access</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="pager">
                        <span class="pager-info">
                            Showing {{ $users->firstItem() }}-{{ $users->lastItem() }} of {{ $users->total() }}
                        </span>
                        <div class="pager-controls">
                            @if ($users->onFirstPage())
                                <span class="btn btn-secondary btn-small" aria-disabled="true"><span class="btn-label">Previous</span></span>
                            @else
                                <a class="btn btn-secondary btn-small" href="{{ $users->previousPageUrl() }}"><span class="btn-label">Previous</span></a>
                            @endif

                            <span class="pager-info">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>

                            @if ($users->hasMorePages())
                                <a class="btn btn-secondary btn-small" href="{{ $users->nextPageUrl() }}"><span class="btn-label">Next</span></a>
                            @else
                                <span class="btn btn-secondary btn-small" aria-disabled="true"><span class="btn-label">Next</span></span>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection
