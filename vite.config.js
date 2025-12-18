import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                main: resolve(__dirname, 'source/js/main.js'),
                admin: resolve(__dirname, 'source/js/admin.js'),
                style: resolve(__dirname, 'source/sass/style.scss'),
                'admin-style': resolve(__dirname, 'source/sass/admin.scss'),
            },
            output: {
                entryFileNames: 'js/[name].[hash].js',
                chunkFileNames: 'js/[name].[hash].js',
                assetFileNames: (assetInfo) => {
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
