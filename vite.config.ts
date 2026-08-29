import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    /*
     * Vue compiles its hydration-mismatch warnings out of production builds, so
     * a mismatch in production is completely silent -- no console output, and
     * nothing a server log could ever see. This flag keeps those messages in,
     * and resources/js/lib/diagnostics.ts forwards them to the server log.
     *
     * Temporary: drop this (and the diagnostics module) once the post-OAuth
     * hydration issue is identified.
     */
    define: {
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'true',
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia({
            ssr: 'resources/js/ssr.ts',
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
