import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/*
 * No CSS framework. The design system in .claude/reference-template defines
 * every token itself and forbids a second design system alongside it, so the
 * stylesheet is plain CSS custom properties authored against that file.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
