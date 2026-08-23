{{--
    Domain Entitlements. MENU_STRUCTURE 12.2.

    THE SECOND DIMENSION, seen by domain rather than by person. The question
    this screen exists for is "who can read Finance", and it cannot be read off
    the user registry by eye once there are two hundred accounts.

    READ ONLY on purpose. Granting and revoking happen on the account, where the
    elevation checks and the audit event live. A bulk grant matrix would be the
    obvious way around both, and ROLE_MODEL.md section 1 makes a domain grant a
    deliberate, individually recorded decision rather than a checkbox.
--}}
@extends('layouts.shell')

@section('title', 'Domain Entitlements · '.config('app.name'))
@section('page-title', 'Domain Entitlements')
@section('page-subtitle', 'Who can read each kind of business information.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-ticket"/></svg>
            <span>
                Business domain access is separate from a platform role. Nobody appears here
                because of the role they hold - including a System Administrator, who can
                operate the platform and read none of this. Grant and revoke on the account.
            </span>
        </div>

        @foreach ($domains as $domain)
            @php($holders = $entitlements->get($domain->value, collect()))
            <section class="card" aria-labelledby="domain-{{ $domain->value }}">
                <div class="panel-head card-head">
                    <h2 class="panel-title" id="domain-{{ $domain->value }}">
                        <svg class="icon" aria-hidden="true"><use href="#i-ticket"/></svg>
                        {{ $domain->label() }}
                        @if ($domain->isSensitive())
                            <span class="badge badge-violet">Restricted fields</span>
                        @endif
                    </h2>
                    <span class="badge {{ $holders->isEmpty() ? '' : 'badge-success' }}">
                        {{ $holders->count() }} {{ $holders->count() === 1 ? 'person' : 'people' }}
                    </span>
                </div>

                @if ($holders->isEmpty())
                    <div class="empty">
                        <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                        <span class="empty-title">Nobody has access</span>
                        <span class="empty-note">{{ $domain->description() }}</span>
                    </div>
                @else
                    <div class="table-scroll">
                        <table class="data-table">
                            <caption class="visually-hidden">People entitled to {{ $domain->label() }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="col-primary">Person</th>
                                    <th scope="col">Platform role</th>
                                    <th scope="col">Granted by</th>
                                    <th scope="col">Granted</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($holders as $entitlement)
                                    <tr>
                                        <th scope="row" class="cell-heading">
                                            {{ $entitlement->user?->name ?? 'Deleted account' }}
                                            <span class="cell-note">{{ $entitlement->user?->email ?? '-' }}</span>
                                        </th>
                                        <td>
                                            {{ $entitlement->user?->role->label() ?? '-' }}
                                            {{-- Shown next to the entitlement precisely
                                                 so the separation is visible: the role
                                                 column explains nothing about this row. --}}
                                            <span class="cell-note">Unrelated to this access</span>
                                        </td>
                                        <td>{{ $entitlement->grantedBy?->name ?? 'System' }}</td>
                                        <td>{{ $entitlement->created_at?->toFormattedDateString() ?? '-' }}</td>
                                        <td>
                                            @if ($entitlement->user)
                                                <a class="btn btn-secondary btn-small" href="{{ route('admin.users.show', $entitlement->user) }}">
                                                    <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                                                    <span class="btn-label">Open account</span>
                                                </a>
                                            @else
                                                <span class="cell-empty">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endsection
