{{--
    ADM-006 Roles. MENU_STRUCTURE 12.2.

    A role narrows its tier and can never widen it. The tier column is the
    ceiling, and the count beside it is what the role actually grants, so a role
    carrying fewer permissions than its tier allows reads correctly as narrower
    rather than as broken.

    Built-in roles are listed first and marked. VAL-ROLE-SYSTEM-001 protects
    their code and their existence; their display name stays editable, because a
    customer may call an Administrator whatever they like.
--}}
@extends('layouts.shell')

@section('title', 'Roles · '.config('app.name'))
@section('page-title', 'Roles')
@section('page-subtitle', 'Reusable profiles of what somebody may do to the platform.')

@section('page-action')
    {{-- Absent, not disabled, when the viewer cannot manage roles: the
         permission is opt-in below System Administrator, so an Administrator
         reaches this list without reaching the editor. --}}
    @if ($mayManage)
        <a class="btn btn-solid btn-primary" href="{{ route('admin.roles.create') }}">
            <svg class="icon" aria-hidden="true"><use href="#i-plus-circle"/></svg>
            <span class="btn-label">New role</span>
        </a>
    @endif
@endsection

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
            <span>
                A role says what somebody may do to the platform. It never grants business
                information - Sales, Finance, People and the rest are granted separately, per
                account.
                @unless ($mayManage)
                    You can see roles here but not change them; editing what a role may do
                    needs to be granted to you explicitly.
                @endunless
            </span>
        </div>

        <div class="card">
            <div class="table-scroll">
                <table class="data-table">
                    <caption class="visually-hidden">Roles and what they carry</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="col-primary">Role</th>
                            <th scope="col">Ceiling</th>
                            <th scope="col" class="cell-numeric">Permissions</th>
                            <th scope="col" class="cell-numeric">Held by</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <th scope="row" class="cell-heading">
                                    {{ $role->name }}
                                    <span class="cell-note">{{ $role->description }}</span>
                                    <span class="cell-note cell-reference">{{ $role->code }} &middot; version {{ $role->version }}</span>
                                </th>
                                <td>
                                    {{ $role->tier->label() }}
                                    @if ($role->is_system)
                                        <span class="cell-note">Built in</span>
                                    @endif
                                </td>
                                <td class="cell-numeric">
                                    @if ($role->is_system)
                                        {{-- A built-in role's authority comes from its
                                             TIER through the registry, not from rows, so
                                             a bare 0 here would read as "grants nothing"
                                             when it grants the most. --}}
                                        {{ count($permissions->defaultsFor($role->tier)) }}
                                        <span class="cell-note">from its tier</span>
                                    @else
                                        {{ $role->permissions_count }}
                                    @endif
                                </td>
                                <td class="cell-numeric">{{ $role->holders_count }}</td>
                                <td><span class="{{ $role->status->badgeClass() }}">{{ $role->status->label() }}</span></td>
                                <td>
                                    @if ($mayManage)
                                        <a class="btn btn-secondary btn-small" href="{{ route('admin.roles.edit', $role) }}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-sliders"/></svg>
                                            <span class="btn-label">Edit</span>
                                        </a>
                                        @unless ($role->is_system)
                                            <a class="btn btn-secondary btn-small" href="{{ route('admin.roles.permissions', $role) }}">
                                                <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
                                                <span class="btn-label">Permissions</span>
                                            </a>
                                        @endunless
                                    @else
                                        <span class="cell-empty">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
