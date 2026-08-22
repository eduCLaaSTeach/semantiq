/**
 * Shell behaviour: the rail, the accordion groups, the navigation filter, the
 * theme switcher, and the top-bar menus.
 *
 * Plain DOM, no framework and no router. The shell is server rendered, so this
 * only manages the state a page load cannot carry: what is open, what is
 * filtered, and which theme the person chose.
 *
 * Every read and write of localStorage is guarded. A browser in private mode,
 * or one set to block site data, throws on access rather than returning null,
 * and an unguarded call there would break the whole shell.
 */

const STORE_THEME = 'semantiq.theme';
const STORE_RAIL = 'semantiq.rail';
const STORE_GROUP = 'semantiq.group.';

/** Read a stored value, or null if storage is unavailable. */
function readStore(key) {
    try {
        return window.localStorage.getItem(key);
    } catch (e) {
        return null;
    }
}

/** Write a stored value, ignoring failure. The feature still works, it just forgets. */
function writeStore(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch (e) {
        /* Storage unavailable: the choice applies to this page only. */
    }
}

/* ---------------------------------------------------------------------------
 * Theme
 *
 * Three states. "system" removes the attribute entirely so the stylesheet's
 * prefers-color-scheme rules decide; the other two pin it.
 * ------------------------------------------------------------------------ */

function applyTheme(choice) {
    const root = document.documentElement;

    if (choice === 'light' || choice === 'dark') {
        root.setAttribute('data-theme', choice);
    } else {
        root.removeAttribute('data-theme');
    }

    document.querySelectorAll('[data-theme-set]').forEach((button) => {
        const isCurrent = button.dataset.themeSet === (choice || 'system');
        button.classList.toggle('is-current', isCurrent);
        button.setAttribute('aria-checked', isCurrent ? 'true' : 'false');
    });
}

function initTheme() {
    applyTheme(readStore(STORE_THEME) || 'system');

    document.querySelectorAll('[data-theme-set]').forEach((button) => {
        button.addEventListener('click', () => {
            const choice = button.dataset.themeSet;
            writeStore(STORE_THEME, choice);
            applyTheme(choice);
            closeAllMenus();
        });
    });
}

/* ---------------------------------------------------------------------------
 * Rail
 *
 * Collapsed state lives on the root element rather than the rail, because the
 * blocking script in the document head sets it before the rail exists.
 * ------------------------------------------------------------------------ */

function initRail() {
    const root = document.documentElement;
    const toggle = document.querySelector('[data-rail-toggle]');
    const open = document.querySelector('[data-rail-open]');
    const backdrop = document.querySelector('[data-shell-backdrop]');

    function setCollapsed(collapsed) {
        root.classList.toggle('rail-is-collapsed', collapsed);
        writeStore(STORE_RAIL, collapsed ? 'collapsed' : 'expanded');

        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    if (toggle) {
        toggle.addEventListener('click', () => {
            // On a small screen the rail is an overlay, so the control closes it.
            if (root.classList.contains('rail-is-open')) {
                setDrawer(false);

                return;
            }

            setCollapsed(!root.classList.contains('rail-is-collapsed'));
        });

        toggle.setAttribute(
            'aria-expanded',
            root.classList.contains('rail-is-collapsed') ? 'false' : 'true'
        );
    }

    function setDrawer(isOpen) {
        root.classList.toggle('rail-is-open', isOpen);

        if (backdrop) {
            backdrop.hidden = !isOpen;
        }
    }

    if (open) {
        open.addEventListener('click', () => setDrawer(true));
    }

    if (backdrop) {
        backdrop.addEventListener('click', () => setDrawer(false));
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setDrawer(false);
        }
    });
}

/* ---------------------------------------------------------------------------
 * Accordion groups
 *
 * Each group remembers whether it was open, so navigating does not collapse
 * the branch the person is working in. The group holding the active route is
 * opened by the server and is never closed by a stored value.
 * ------------------------------------------------------------------------ */

function initGroups() {
    document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const toggle = group.querySelector('[data-nav-toggle]');
        const children = group.querySelector('[data-nav-children]');
        const key = STORE_GROUP + group.dataset.groupKey;

        if (!toggle || !children) {
            return;
        }

        // A group on the active trail stays as the server rendered it.
        if (!toggle.classList.contains('is-trail') && readStore(key) === 'open') {
            children.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
        }

        toggle.addEventListener('click', () => {
            const willOpen = children.hidden;
            children.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            writeStore(key, willOpen ? 'open' : 'closed');
        });
    });
}

/* ---------------------------------------------------------------------------
 * Navigation filter
 *
 * Matches leaf and group labels alike. A group survives if its own label
 * matches or if any descendant does, and it is force-opened while filtering so
 * a match is never hidden inside a closed accordion.
 * ------------------------------------------------------------------------ */

function initFilter() {
    const input = document.querySelector('[data-nav-filter]');
    const empty = document.querySelector('[data-nav-empty]');

    if (!input) {
        return;
    }

    function matches(node, term) {
        const label = (node.dataset.navLabel || '').toLowerCase();

        return label.indexOf(term) !== -1;
    }

    function filterGroup(group, term) {
        const toggle = group.querySelector(':scope > [data-nav-toggle]');
        const children = group.querySelector(':scope > [data-nav-children]');
        let anyVisible = toggle ? matches(toggle, term) : false;

        if (children) {
            children.querySelectorAll(':scope > [data-nav-group]').forEach((child) => {
                if (filterGroup(child, term)) {
                    anyVisible = true;
                }
            });

            children.querySelectorAll(':scope > [data-nav-leaf]').forEach((leaf) => {
                const hit = matches(leaf, term);
                leaf.hidden = !hit;

                if (hit) {
                    anyVisible = true;
                }
            });
        }

        group.hidden = !anyVisible;

        // Open every surviving group while a filter is active, so hits are visible.
        if (children && anyVisible) {
            children.hidden = false;
        }

        return anyVisible;
    }

    function reset() {
        document.querySelectorAll('[data-nav-group]').forEach((group) => {
            group.hidden = false;

            const toggle = group.querySelector(':scope > [data-nav-toggle]');
            const children = group.querySelector(':scope > [data-nav-children]');

            if (children && toggle) {
                children.hidden = toggle.getAttribute('aria-expanded') !== 'true';
            }
        });

        document.querySelectorAll('[data-nav-leaf]').forEach((leaf) => {
            leaf.hidden = false;
        });

        document.querySelectorAll('[data-nav-cluster]').forEach((cluster) => {
            cluster.hidden = false;
        });

        if (empty) {
            empty.hidden = true;
        }
    }

    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();

        if (term === '') {
            reset();

            return;
        }

        let anyHit = false;

        document.querySelectorAll('[data-nav-cluster]').forEach((cluster) => {
            let clusterHit = false;

            cluster.querySelectorAll(':scope > [data-nav-group]').forEach((group) => {
                if (filterGroup(group, term)) {
                    clusterHit = true;
                }
            });

            cluster.querySelectorAll(':scope > [data-nav-leaf]').forEach((leaf) => {
                const hit = matches(leaf, term);
                leaf.hidden = !hit;

                if (hit) {
                    clusterHit = true;
                }
            });

            // A cluster with no surviving node goes too, label and all.
            cluster.hidden = !clusterHit;

            if (clusterHit) {
                anyHit = true;
            }
        });

        if (empty) {
            empty.hidden = anyHit;
        }
    });
}

/* ---------------------------------------------------------------------------
 * Top-bar menus
 * ------------------------------------------------------------------------ */

function closeAllMenus() {
    document.querySelectorAll('[data-menu]').forEach((menu) => {
        const trigger = menu.querySelector('[data-menu-trigger]');
        const panel = menu.querySelector('[data-menu-panel]');

        if (trigger && panel) {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
}

function initMenus() {
    document.querySelectorAll('[data-menu]').forEach((menu) => {
        const trigger = menu.querySelector('[data-menu-trigger]');
        const panel = menu.querySelector('[data-menu-panel]');

        if (!trigger || !panel) {
            return;
        }

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = panel.hidden;
            closeAllMenus();
            panel.hidden = !willOpen;
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        panel.addEventListener('click', (event) => event.stopPropagation());
    });

    document.addEventListener('click', closeAllMenus);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllMenus();
        }
    });
}

initTheme();
initRail();
initGroups();
initFilter();
initMenus();
