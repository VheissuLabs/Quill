import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            /**
             * Lowercase deliberately. The value becomes both `server.host` and
             * `server.hmr.host`, and Vite's allowed-host check is case-sensitive —
             * browsers always send a lowercase Host header, so 'Quill.test' (as
             * Herd names the certificate files) makes every HMR upgrade 400 while
             * ordinary asset requests still succeed. The cert lookup is
             * case-insensitive on macOS, so this still finds Quill.test.crt.
             */
            detectTls: 'quill.test',
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
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
