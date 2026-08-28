import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // `landing.js` is its own entry so the Three.js scene never enters
            // the bundle every authenticated page loads. It in turn imports
            // ./landing/scene.js dynamically, which Rollup splits again — so
            // the library itself only downloads once the landing page has
            // already painted its text and sign-in button.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/landing.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
