import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
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
            /**
             * Self-hosted rather than fetched from Bunny. Bunny's CSS declares a
             * `woff` @font-face after the `woff2` one with an identical
             * unicode-range, so the legacy file always won the cascade — and that
             * file has a malformed `maxp` table Firefox warns about. One format
             * means one rule per weight and nothing to override it.
             */
            fonts: [
                local('Instrument Sans', {
                    variants: [
                        { weight: 400, src: 'resources/fonts/instrument-sans-400.woff2' },
                        { weight: 500, src: 'resources/fonts/instrument-sans-500.woff2' },
                        { weight: 600, src: 'resources/fonts/instrument-sans-600.woff2' },
                    ],
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
