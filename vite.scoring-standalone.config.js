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
    base: '/',
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
