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
        host: true, // Escuchar en todas las interfaces de red
        port: 5173,
        strictPort: true, // Fallar si el puerto no está disponible
        cors: true, // Habilitar CORS
        hmr: {
            host: 'localhost',
            port: 5173,
        },
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
                '@/app/View/Components/**',
                'modules/Lumasachi/resources/lang/**',
                'modules/Lumasachi/resources/views/**',
                'modules/Lumasachi/routes/**',
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
