/*
 * Application shell behaviour.
 *
 * Everything here is progressive: the shell renders and navigates without any
 * of it. What this adds is the rail's collapse, the nav filter, accordion
 * groups, collapsed flyouts and the overlay menus.
 */

const RAIL_KEY = 'semantiq.rail';
const THEME_KEY = 'semantiq.theme';

const root = document.documentElement;

function read(key) {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch {
        /* Private window or blocked site data. The choice just will not persist. */
    }
}

/* ---------------------------------------------------------------------------
 * Rail collapse
 *
 * The state lives on the root element so the pre-paint script in the layout can
 * apply it before anything renders, which is what stops the rail visibly
 * snapping shut on every page load.
 * ------------------------------------------------------------------------ */

function syncRailControl() {
    const control = document.querySelector('[data-rail-toggle]');

    if (!control) {
        return;
    }

    const collapsed = root.classList.contains('rail-is-collapsed');

    control.setAttribute('aria-expanded', String(!collapsed));
    control.setAttribute(
        'aria-label',
        collapsed ? control.dataset.collapsedLabel : control.dataset.expandedLabel,
    );
}

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-rail-toggle]')) {
        return;
    }

    const collapsed = root.classList.toggle('rail-is-collapsed');

    write(RAIL_KEY, collapsed ? 'collapsed' : 'expanded');
    syncRailControl();
    closeFlyout();
});

/* ---------------------------------------------------------------------------
 * Accordion groups
 *
 * Each group keeps its own open state across navigation, so a person working
 * inside one section does not have to reopen it on every page.
 * ------------------------------------------------------------------------ */

function groupKey(button) {
    return `semantiq.group.${button.dataset.label}`;
}

document.querySelectorAll('[data-nav-group-toggle]').forEach((button) => {
    const stored = read(groupKey(button));

    // A group holding the active route is open regardless of what was stored:
    // hiding the page you are on behind a closed accordion is disorienting.
    if (button.getAttribute('aria-expanded') === 'true') {
        return;
    }

    if (stored === 'open') {
        button.setAttribute('aria-expanded', 'true');
        button.nextElementSibling?.removeAttribute('hidden');
    }
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-nav-group-toggle]');

    if (!button) {
        return;
    }

    const open = button.getAttribute('aria-expanded') !== 'true';

    button.setAttribute('aria-expanded', String(open));
    button.nextElementSibling?.toggleAttribute('hidden', !open);
    write(groupKey(button), open ? 'open' : 'closed');
});

/* ---------------------------------------------------------------------------
 * Nav filter
 *
 * Matches leaf labels AND group labels. A group-name match reveals the group
 * with all its children, because people often remember only the section name.
 * ------------------------------------------------------------------------ */

const filter = document.querySelector('[data-nav-filter]');

if (filter) {
    filter.addEventListener('input', () => {
        const term = filter.value.trim().toLowerCase();

        document.querySelectorAll('[data-nav-group]').forEach((group) => {
            const header = group.querySelector('[data-nav-group-toggle]');
            const groupMatches = header.dataset.label.toLowerCase().includes(term);
            let anyChild = false;

            group.querySelectorAll('.nav-group-body .nav-item').forEach((item) => {
                const hit = groupMatches || item.dataset.label.toLowerCase().includes(term);
                item.toggleAttribute('hidden', term !== '' && !hit);
                anyChild = anyChild || hit;
            });

            group.toggleAttribute('hidden', term !== '' && !groupMatches && !anyChild);

            if (term !== '' && (groupMatches || anyChild)) {
                group.querySelector('.nav-group-body')?.removeAttribute('hidden');
                header.setAttribute('aria-expanded', 'true');
            }
        });

        // Leaves sitting directly under a cluster, outside any group.
        document.querySelectorAll('.nav-cluster > .nav-item').forEach((item) => {
            item.toggleAttribute('hidden', term !== '' && !item.dataset.label.toLowerCase().includes(term));
        });

        // A cluster with nothing left to show goes too, rather than leaving a
        // heading floating above empty space.
        document.querySelectorAll('[data-cluster]').forEach((cluster) => {
            const visible = [...cluster.querySelectorAll('.nav-item, [data-nav-group]')]
                .some((node) => !node.hasAttribute('hidden'));
            cluster.toggleAttribute('hidden', !visible);
        });
    });
}

/* ---------------------------------------------------------------------------
 * Collapsed flyouts
 *
 * Positioned fixed, from the icon's own coordinates, because the nav list
 * scrolls and clips its overflow - a flyout inside it would be cut off.
 *
 * Hover-intent, not instant-hide: the pointer has to cross a gap to reach the
 * popover, and closing the moment it leaves the icon makes that impossible.
 * ------------------------------------------------------------------------ */

let flyout = null;
let closeTimer = null;

function closeFlyout() {
    clearTimeout(closeTimer);
    flyout?.remove();
    flyout = null;
}

function scheduleClose() {
    clearTimeout(closeTimer);
    closeTimer = setTimeout(closeFlyout, 220);
}

function openFlyout(anchor) {
    closeFlyout();

    if (!root.classList.contains('rail-is-collapsed')) {
        return;
    }

    const group = anchor.closest('[data-nav-group]');
    const box = anchor.getBoundingClientRect();

    flyout = document.createElement('div');
    flyout.className = 'flyout';
    flyout.style.top = `${box.top}px`;
    flyout.style.left = `${box.right + 6}px`;

    const title = document.createElement('div');
    title.className = 'flyout-title';
    title.textContent = anchor.dataset.label;
    flyout.appendChild(title);

    if (group) {
        // Children are cloned rather than re-derived, so a flyout entry is the
        // same element with the same handlers and can never drift out of step
        // with the rail it mirrors.
        group.querySelectorAll('.nav-group-body > *').forEach((child) => {
            const copy = child.cloneNode(true);
            copy.removeAttribute('hidden');
            flyout.appendChild(copy);
        });
    }

    flyout.addEventListener('mouseenter', () => clearTimeout(closeTimer));
    flyout.addEventListener('mouseleave', scheduleClose);

    document.body.appendChild(flyout);
}

document.querySelectorAll('.rail-nav .nav-item').forEach((item) => {
    item.addEventListener('mouseenter', () => openFlyout(item));
    item.addEventListener('focus', () => openFlyout(item));
    item.addEventListener('mouseleave', scheduleClose);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeFlyout();
        closeOverlays();
    }
});

/* ---------------------------------------------------------------------------
 * Overlays
 *
 * Mutually exclusive, and toggled with the hidden attribute so the element
 * leaves layout entirely rather than lingering invisible but focusable.
 * ------------------------------------------------------------------------ */

function closeOverlays(except = null) {
    document.querySelectorAll('[data-overlay-toggle]').forEach((button) => {
        const panel = document.getElementById(button.dataset.overlayToggle);

        if (panel === except) {
            return;
        }

        panel?.setAttribute('hidden', '');
        button.setAttribute('aria-expanded', 'false');
    });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-overlay-toggle]');

    if (button) {
        const panel = document.getElementById(button.dataset.overlayToggle);
        const open = panel.hasAttribute('hidden');

        closeOverlays(panel);
        panel.toggleAttribute('hidden', !open);
        button.setAttribute('aria-expanded', String(open));

        return;
    }

    // Outside click. A click inside an open panel must not dismiss it.
    if (!event.target.closest('.popover')) {
        closeOverlays();
    }
});

/* ---------------------------------------------------------------------------
 * Theme switcher
 * ------------------------------------------------------------------------ */

function currentChoice() {
    const value = read(THEME_KEY);

    return value === 'light' || value === 'dark' ? value : 'system';
}

function syncThemeControl() {
    const choice = currentChoice();

    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.themeChoice === choice));
    });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-theme-choice]');

    if (!button) {
        return;
    }

    const choice = button.dataset.themeChoice;

    write(THEME_KEY, choice);
    window.dispatchEvent(new CustomEvent('semantiq:theme', { detail: choice }));
    syncThemeControl();
});

syncRailControl();
syncThemeControl();

export { closeOverlays, closeFlyout };
