import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Inertia client entry point.
 *
 * Pages live under resources/js/pages and are resolved eagerly, so the whole
 * page set ships in one bundle. That is deliberate while the app is small: the
 * deployment target is cPanel shared hosting reached over rsync, and one
 * fingerprinted bundle is simpler to serve than many lazily fetched chunks.
 * Revisit when the page count makes the bundle size the bigger cost.
 */
const appName: string = import.meta.env.VITE_APP_NAME ?? 'SemantIQ';

/**
 * The progress bar colour is read from the design token at runtime rather than
 * written as a hex literal, so it follows the effective theme and never drifts
 * from the approved palette.
 */
function accentColor(): string | undefined {
    const value = getComputedStyle(document.documentElement)
        .getPropertyValue('--color-primary')
        .trim();

    return value.length > 0 ? value : undefined;
}

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    resolve: (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx', {
            eager: true,
        });
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Inertia page component not found: ${name}`);
        }

        return page;
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },

    progress: { color: accentColor() },
});
