{{--
    ADM-007, choosing what a role may do.

    Two reasons a permission can be unavailable, and BOTH are shown rather than
    hidden, each with its reason:

      - the actor does not hold it themselves, so they cannot delegate it;
      - it is above this role's ceiling, so it would be inert whoever held it.

    Hiding them would leave an administrator wondering whether the screen was
    broken. Showing them disabled, with the reason, answers the question they
    were about to ask. RoleRegistry refuses both again on save - a disabled
    checkbox stops a mistake and does nothing about a crafted post.

    Risk is shown on every high and elevated permission, because an
    administrator ticking forty identical-looking checkboxes reads none of them.
--}}
@extends('layouts.shell')

@section('title', 'Permissions for '.$role->name.' · '.config('app.name'))
@section('page-title', $role->name)
@section('page-subtitle', 'What this role may do. Nothing here grants business information.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @if ($withinCeiling === [])
            {{-- Every declared permission is above this role's ceiling, so the
                 editor would be a page of disabled checkboxes with no
                 explanation. Say what is actually going on instead. --}}
            <div class="card panel">
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
                    <span class="empty-title">Nothing this role can carry yet</span>
                    <span class="empty-note">
                        Its ceiling is {{ $roleTier->label() }}, and every permission this
                        application currently declares needs Administrator or above. A role at
                        this level is useful for grouping people, not for granting
                        administrative access.
                    </span>
                    <a class="btn btn-secondary btn-small" href="{{ route('admin.roles') }}">
                        <span class="btn-label">Back to roles</span>
                    </a>
                </div>
            </div>
        @else

        <div class="alert alert-info" role="note">
            <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
            <span>
                This role's ceiling is <strong>{{ $roleTier->label() }}</strong>. A permission
                above that ceiling does nothing even if it is ticked, so those are shown but
                cannot be chosen.
            </span>
        </div>

        <form class="card" method="POST" action="{{ route('admin.roles.permissions.update', $role) }}">
            @csrf
            @method('PUT')

            @foreach ($byModule as $module => $permissions)
                <fieldset class="permission-module">
                    <legend class="permission-module-name">{{ $module }}</legend>

                    @foreach ($permissions as $key => $permission)
                        @php($aboveCeiling = ! $roleTier->atLeast($permission->minimumTier))
                        @php($notHeld = ! in_array($key, $grantable, true))
                        @php($locked = $aboveCeiling || $notHeld)

                        <div class="permission-choice @if($locked) is-locked @endif">
                            <input type="checkbox"
                                   id="perm-{{ $loop->parent->index }}-{{ $loop->index }}"
                                   name="permissions[]"
                                   value="{{ $key }}"
                                   @checked(in_array($key, $held, true))
                                   @disabled($locked)>
                            <span class="permission-choice-body">
                                <label class="permission-choice-name" for="perm-{{ $loop->parent->index }}-{{ $loop->index }}">
                                    {{ $permission->label() }}
                                    @if ($permission->risk !== \App\Modules\Identity\Support\PermissionRisk::Normal)
                                        <span class="{{ $permission->risk->badgeClass() }}">{{ $permission->risk->label() }} risk</span>
                                    @endif
                                    @if ($permission->isRead())
                                        <span class="badge">Read only</span>
                                    @endif
                                </label>
                                <span class="cell-note">{{ $permission->description }}</span>
                                <span class="cell-note cell-reference">{{ $key }}</span>

                                {{-- The reason it is unavailable, said plainly. --}}
                                @if ($aboveCeiling)
                                    <span class="cell-note">
                                        Needs {{ $permission->minimumTier->label() }}, which is above this role's ceiling.
                                    </span>
                                @elseif ($notHeld)
                                    <span class="cell-note">
                                        You do not hold this permission yourself, so you cannot give it to a role.
                                    </span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </fieldset>
            @endforeach

            <div class="settings-foot" style="margin:0 var(--space-3) var(--space-3)">
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">Save permissions</span>
                </button>
                <a class="btn btn-secondary" href="{{ route('admin.roles') }}">
                    <span class="btn-label">Cancel</span>
                </a>
                <span class="field-help">Saving bumps this role to version {{ $role->version + 1 }} and is recorded in the audit trail.</span>
            </div>
        </form>
        @endif
    </div>
@endsection
