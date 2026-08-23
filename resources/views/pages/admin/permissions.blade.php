{{--
    ADM-007 Permissions. Added to MENU_STRUCTURE 12.2 by DEC-001, closing M3.

    READ ONLY, and that is the design rather than a limitation. The catalogue is
    code, because a permission an administrator can invent is not a permission:
    nothing checks a key that no line of code names, so it would grant nothing
    while appearing to grant something.

    So this screen answers "given a permission, who can use it". The account
    page answers the other direction. Neither can be read off the other by eye
    once there are more than a handful of roles.
--}}
@extends('layouts.shell')

@section('title', 'Permissions · '.config('app.name'))
@section('page-title', 'Permissions')
@section('page-subtitle', 'Every action this application checks for, and who can perform it.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-book"/></svg>
            <span>
                This catalogue is part of the application. Permissions are added by a change to
                the code and a review, not from a screen, so a permission can never exist that
                nothing actually checks. Which roles hold them is set on each role.
            </span>
        </div>

        @foreach ($byModule as $module => $permissions)
            <section class="card" aria-labelledby="module-{{ $loop->index }}">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="module-{{ $loop->index }}">
                        <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
                        {{ $module }}
                    </h2>
                    <span class="badge">{{ count($permissions) }}</span>
                </div>

                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">{{ $module }} permissions</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Permission</th>
                                <th scope="col">Needs at least</th>
                                <th scope="col">Risk</th>
                                <th scope="col">Also granted to</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissions as $key => $permission)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        {{ $permission->label() }}
                                        <span class="cell-note">{{ $permission->description }}</span>
                                        <span class="cell-note cell-reference">{{ $key }}</span>
                                    </th>
                                    <td>
                                        {{ $tierRoles[$permission->minimumTier->value] ?? $permission->minimumTier->label() }}
                                        <span class="cell-note">and above, by default</span>
                                    </td>
                                    <td>
                                        <span class="{{ $permission->risk->badgeClass() }}">{{ $permission->risk->label() }}</span>
                                        @if ($permission->requiresAudit)
                                            <span class="cell-note">Always audited</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Never a blank cell. "No other role"
                                             is the honest answer and reads
                                             differently from an empty box. --}}
                                        @if (empty($holders[$key]))
                                            <span class="cell-empty">No other role</span>
                                        @else
                                            <span class="pill-row">
                                                @foreach ($holders[$key] as $name)
                                                    <span class="badge">{{ $name }}</span>
                                                @endforeach
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
@endsection
