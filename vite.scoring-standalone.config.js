import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        tailwindcss(),
        vue(),
    ],
    root: resolve(__dirname),
    // Relative base — so `file:///android_asset/scoring-standalone.html`
    // can resolve `./assets/...` URLs without depending on a host. The
    // previous absolute `/` base broke the WebView when there was no HTTP
    // server underneath it (which is exactly the standalone APK case).
    base: './',
    publicDir: false,
    build: {
        outDir: resolve(__dirname, 'scoring-standalone-dist'),
        emptyOutDir: true,
        rollupOptions: {
            input: resolve(__dirname, 'scoring-standalone.html'),
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js/scoring-app'),
        },
    },
});
