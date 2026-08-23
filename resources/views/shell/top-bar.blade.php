{{--
    The top bar: a three-column grid, 52px tall.

    Carries no navigation tabs, no global search bar and no action buttons. A
    page's primary action belongs in its page header, not up here.
--}}
@php($user = auth()->user())

<header class="top-nav">
    {{-- The application name as text. The logo lives in the rail, never here. --}}
    <span class="app-name">{{ config('app.name') }}</span>

    <span></span>

    <div class="top-utils">
        <div class="overlay-anchor">
            <button type="button"
                    class="icon-button"
                    data-overlay-toggle="notifications"
                    aria-label="Notifications"
                    aria-expanded="false">
                <svg class="icon" aria-hidden="true"><use href="#i-bell"/></svg>
            </button>
            <div class="popover" id="notifications" hidden>
                <div class="menu-section-label">Notifications</div>
                <p style="padding: var(--space-2); color: var(--text-muted)">
                    Nothing to show yet.
                </p>
            </div>
        </div>

        <div class="overlay-anchor">
            <button type="button"
                    class="icon-button"
                    data-overlay-toggle="profile-menu"
                    aria-label="Account"
                    aria-expanded="false">
                <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span>
            </button>

            {{-- Three parts, in this order: identity block, Appearance, sign
                 out. The identity block is one clickable row opening the
                 profile page, not a plain text header. --}}
            <div class="popover" id="profile-menu" hidden>
                <a href="{{ route('profile') }}" class="menu-identity">
                    <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span>
                    <span class="menu-identity-text">
                        <span class="menu-identity-name">{{ $user->name }}</span>
                        <span class="menu-identity-email">{{ $user->email }}</span>
                    </span>
                    <svg class="icon" aria-hidden="true" style="font-size:16px;color:var(--text-muted)">
                        <use href="#i-chevron-right"/>
                    </svg>
                </a>

                <div class="menu-divider"></div>

                <div class="menu-section-label">
                    <svg class="icon" aria-hidden="true"><use href="#i-palette"/></svg>
                    Appearance
                </div>

                {{-- System / Dark / Light, in that order, System the default.
                     One connected segmented control filling the width in equal
                     segments - not detached buttons, not a list, and never a
                     blind click-to-cycle. --}}
                <div class="theme-switch" role="group" aria-label="Theme">
                    <button type="button" class="theme-option" data-theme-choice="system" aria-pressed="false">
                        <svg class="icon" aria-hidden="true"><use href="#i-monitor"/></svg>System
                    </button>
                    <button type="button" class="theme-option" data-theme-choice="dark" aria-pressed="false">
                        <svg class="icon" aria-hidden="true"><use href="#i-moon"/></svg>Dark
                    </button>
                    <button type="button" class="theme-option" data-theme-choice="light" aria-pressed="false">
                        <svg class="icon" aria-hidden="true"><use href="#i-sun"/></svg>Light
                    </button>
                </div>

                <div class="menu-divider"></div>

                <form method="POST" action="{{ route('sign-out') }}">
                    @csrf
                    <button type="submit" class="menu-action">
                        <svg class="icon" aria-hidden="true"><use href="#i-log-out"/></svg>
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
