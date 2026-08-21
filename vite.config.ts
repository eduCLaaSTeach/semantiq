import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

/**
 * Vite build for SemantIQ.
 *
 * Fonts are NOT declared here. The approved design system fixes Montserrat and
 * Source Sans 3 and specifies the Google Fonts import, which is emitted in the
 * Blade root layout so the families load before first paint. The skeleton's
 * bunny()/Instrument Sans helper is deliberately removed: the type families are
 * an ENFORCED constant and are not a per-project choice.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
