/*
 * Theme bootstrap.
 *
 * The switcher itself lives in the shell's top bar, which an auth screen does
 * not have, so this only applies the choice already made: an explicit Light or
 * Dark stamps data-theme on the root element, and System stamps nothing and
 * lets prefers-color-scheme decide in CSS.
 *
 * The favicon and the brand mark are per-theme image pairs, so both are swapped
 * to match the effective theme here rather than being left on the light variant.
 */

const STORAGE_KEY = 'semantiq.theme';

function storedChoice() {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        // A private window or blocked site data. System is the safe answer.
        return 'system';
    }
}

function effectiveTheme(choice) {
    if (choice !== 'system') {
        return choice;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(choice) {
    const root = document.documentElement;
    const theme = effectiveTheme(choice);

    if (choice === 'system') {
        root.removeAttribute('data-theme');
    } else {
        root.setAttribute('data-theme', choice);
    }

    document.querySelectorAll('[data-theme-image]').forEach((element) => {
        const source = element.dataset[theme === 'dark' ? 'dark' : 'light'];

        if (source) {
            element.setAttribute(element.tagName === 'LINK' ? 'href' : 'src', source);
        }
    });
}

applyTheme(storedChoice());

// Follow the system while the choice is System, so a viewer switching their OS
// theme sees the page follow without a reload.
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (storedChoice() === 'system') {
        applyTheme('system');
    }
});

export { applyTheme, storedChoice, effectiveTheme };

/* ---------------------------------------------------------------------------
 * Password reveal
 *
 * The control is one of the sanctioned icon-only chrome cases: it sits on the
 * field itself. aria-pressed carries the state and the accessible name changes
 * with it, so a screen reader hears what the control will do next rather than
 * only that it exists.
 * ------------------------------------------------------------------------ */

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-password]');

    if (!toggle) {
        return;
    }

    const input = document.getElementById(toggle.dataset.togglePassword);

    if (!input) {
        return;
    }

    const revealed = input.type === 'text';

    input.type = revealed ? 'password' : 'text';
    toggle.setAttribute('aria-pressed', String(!revealed));
    toggle.setAttribute('aria-label', revealed ? 'Show password' : 'Hide password');
    toggle.querySelector('use')?.setAttribute('href', revealed ? '#i-eye' : '#i-eye-off');
});

/* ---------------------------------------------------------------------------
 * Async submit
 *
 * On submit the button immediately disables, hides its label and shows a
 * centered spinner at the same width, guarding against a double submit. The
 * look never changes - only the label is swapped - because a button's emphasis
 * must not move with its state.
 * ------------------------------------------------------------------------ */

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const button = form.querySelector('[data-async]');

    if (!button || button.dataset.busy === 'true') {
        return;
    }

    /*
     * Only once the browser is actually submitting. A form blocked by its own
     * constraint validation never reaches here, so a failed validation cannot
     * leave the button spinning forever with nothing in flight.
     */
    button.dataset.busy = 'true';
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;

    const spinner = document.createElement('span');
    spinner.className = 'btn-spinner';
    button.appendChild(spinner);
});
