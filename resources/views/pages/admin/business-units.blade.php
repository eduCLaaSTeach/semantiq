{{--
    ADM-003 Business Units. MENU_STRUCTURE 12.2.

    Simple table tier: an organisation chart is dozens of rows, not thousands,
    so it needs a header, rows and hover and none of the Standard tier's
    sorting or pagination.

    Ordered as a hierarchy rather than alphabetically, because "where does this
    unit sit" is the question the screen exists to answer. Depth is drawn with a
    rule per level so it survives a narrow column.

    A business unit is a SCOPE, never a permission. Nothing on this screen
    grants access to anything.
--}}
@extends('layouts.shell')

@section('title', 'Business Units · '.config('app.name'))
@section('page-title', 'Business Units')
@section('page-subtitle', 'The divisions of your organisation, and who is accountable for each.')

@section('page-action')
    <a class="btn btn-solid btn-primary" href="{{ route('admin.business-units.create') }}">
        <svg class="icon" aria-hidden="true"><use href="#i-plus-circle"/></svg>
        <span class="btn-label">New business unit</span>
    </a>
@endsection

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="card">
            @if ($units->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-building"/></svg>
                    <span class="empty-title">No business units yet</span>
                    <span class="empty-note">
                        Business units divide the organisation for reporting and for scoping
                        access. They grant nothing on their own.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Business units, shown as a hierarchy</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Business unit</th>
                                <th scope="col">Manager</th>
                                <th scope="col" class="cell-numeric">Teams</th>
                                <th scope="col" class="cell-numeric">People</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Rendered depth-first from the loaded set, so the
                                 hierarchy reads without a query per level. --}}
                            @foreach ($units->sortBy(fn ($u) => $u->path()) as $unit)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        @for ($level = 0; $level < count($unit->ancestors()); $level++)
                                            <span class="tree-indent" aria-hidden="true"></span>
                                        @endfor
                                        {{ $unit->name }}
                                        <span class="cell-note cell-reference">{{ $unit->code }}</span>
                                    </th>
                                    {{-- Never a blank cell: the template requires
                                         a muted placeholder instead. --}}
                                    <td>{{ $unit->manager?->name ?? '-' }}</td>
                                    <td class="cell-numeric">{{ $unit->teams_count }}</td>
                                    <td class="cell-numeric">{{ $unit->members_count }}</td>
                                    <td><span class="{{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span></td>
                                    <td>
                                        <a class="btn btn-secondary btn-small" href="{{ route('admin.business-units.edit', $unit) }}">
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
