import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    base: './',
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                main: resolve(__dirname, 'source/js/main.js'),
                admin: resolve(__dirname, 'source/js/admin.js'),
                'acf-fontawesome-js': resolve(__dirname, 'source/js/acf/acf-fontawesome-icon-field.js'),
                style: resolve(__dirname, 'source/sass/style.scss'),
                'font-awesome': resolve(__dirname, 'source/sass/font-awesome.scss'),
                'admin-style': resolve(__dirname, 'source/sass/admin.scss'),
                'acf-fontawesome-css': resolve(__dirname, 'source/sass/acf/acf-fontawesome-icon-field.scss'),
            },
            output: {
                entryFileNames: (chunkInfo) => {
                    if (chunkInfo && chunkInfo.name && chunkInfo.name.includes('acf-')) {
                        return 'js/acf/[name].[hash].js';
                    }

                    return 'js/[name].[hash].js';
                },
                chunkFileNames: (chunkInfo) => {
                    if (chunkInfo && chunkInfo.name && chunkInfo.name.includes('acf-')) {
                        return 'js/acf/[name].[hash].js';
                    }

                    return 'js/[name].[hash].js';
                },
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name.endsWith('.woff2')) {
                        return 'fonts/[name].[hash][extname]'
                    }

                    if (assetInfo && assetInfo.name && assetInfo.name.includes('acf-')) {
                        return 'css/acf/[name].[hash][extname]';
                    }

                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'css/[name].[hash][extname]';
                    }

                    return 'assets/[name].[hash][extname]';
                },
            },
        },
    },
    css: {
        devSourcemap: true,
    },
});
