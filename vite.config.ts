import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/menu/main.tsx',
                'resources/js/dashboard/main.tsx',
                'resources/js/admin/main.tsx',
            ],
            refresh: true,
            // Fontes servidas pelo Bunny (compativel com Google Fonts, sem cookies).
            fonts: [
                bunny('Karla', { weights: [400, 500, 600, 700] }),
                bunny('Playfair Display SC', { weights: [400, 700] }),
            ],
        }),
        tailwindcss(),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                // React e o router viram chunks compartilhados entre as tres entradas.
                manualChunks(id: string) {
                    if (id.includes('node_modules/react-router')) return 'router';
                    if (id.includes('node_modules/react')) return 'react';

                    return undefined;
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
