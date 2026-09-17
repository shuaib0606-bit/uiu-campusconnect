import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // `landing.js` is its own entry so the Three.js scene never enters
            // the bundle every authenticated page loads. It in turn imports
            // ./landing/scene.js dynamically, which Rollup splits again — so
            // the library itself only downloads once the landing page has
            // already painted its text and sign-in button.
            //
            // `inertia.jsx` is the React entry. Blade pages keep loading
            // app.js, so the two run side by side while pages are converted
            // one at a time and neither ships the other's code.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/landing.js',
                'resources/js/inertia.jsx',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
});
