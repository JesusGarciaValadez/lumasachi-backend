import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./modules/Lumasachi/resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            usePolling: true,
        },
    },
    plugins: [
        laravel({
            input: [
                '@/app.ts',
            ],
            ssr: '@/ssr.ts',
            refresh: [
                '@/app/Livewire/**',
                '@/app/View/Components/**',
                '@/lang/**',
                'modules/Lumasachi/resources/lang/**',
                'modules/Lumasachi/resources/views/**',
                '@/routes/**',
            ],
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
    ],
});
