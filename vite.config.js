import { defineConfig } from 'vite';
import { resolve } from 'path';
import fs from 'fs';
import path from 'path';

export default defineConfig({
    base: './',
    build: {
        outDir: 'dist',
        emptyOutDir: false,
        manifest: true,
        rollupOptions: {
            input: {
                main: resolve(__dirname, 'source/js/main.js'),
                admin: resolve(__dirname, 'source/js/admin.js'),
                'acf-fontawesome-js': resolve(__dirname, 'source/js/acf/acf-fontawesome-icon-field.js'),
                'tinymce-fontawesome-plugin': resolve(__dirname, 'source/js/editor-plugins/tinymce-fontawesome-plugin.js'),
                'quicktags-fontawesome-plugin': resolve(__dirname, 'source/js/editor-plugins/quicktags-fontawesome-plugin.js'),
                'acf-pitea-color-picker-field': resolve(__dirname, 'source/js/editor-plugins/acf-pitea-color-picker-field.js'),
                style: resolve(__dirname, 'source/sass/style.scss'),
                'font-awesome': resolve(__dirname, 'source/sass/font-awesome.scss'),
                'admin-style': resolve(__dirname, 'source/sass/admin.scss'),
                'acf-fontawesome-css': resolve(__dirname, 'source/sass/acf/acf-fontawesome-icon-field.scss'),
                'tinymce-fontawesome-plugin-css': resolve(__dirname, 'source/css/tinymce-fontawesome-plugin.css'),
                'acf-pitea-color-picker-field-css': resolve(__dirname, 'source/css/acf-pitea-color-picker-field.css'),
            },
            output: {
                entryFileNames: (chunkInfo) => {
                    const n = chunkInfo && chunkInfo.name ? String(chunkInfo.name) : '';
                    if (n.includes('acf-') && !n.includes('acf-pitea-color-picker-field')) {
                        return 'js/acf/[name].[hash].js';
                    }
                    if (
                        n.includes('tinymce-fontawesome-plugin') ||
                        n.includes('quicktags-fontawesome-plugin') ||
                        n.includes('acf-pitea-color-picker-field')
                    ) {
                        return 'js/editor-plugins/[name].[hash].js';
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
                    const rawName = assetInfo && (assetInfo.name ?? assetInfo.names ?? '');
                    const name = Array.isArray(rawName) ? rawName.join('/') : (rawName || '');

                    if (name.endsWith('.woff') || name.endsWith('.woff2')) {
                        return 'fonts/[name].[hash][extname]'
                    }

                    if (name.endsWith('.svg')) {
                        return 'img/[name].[hash][extname]';
                    }

                    if (name.endsWith('.css')) {
                        if (name.includes('tinymce-fontawesome-plugin') || name.includes('acf-pitea-color-picker-field')) {
                            return 'css/editor-plugins/[name].[hash][extname]';
                        }
                        if (name && name.includes('acf-')) {
                            return 'css/acf/[name].[hash][extname]';
                        }
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
    plugins: [
        {
            name: 'clean-dist-except-gutenberg',
            buildStart() {
                // Configure folders to preserve in dist directory
                const preserveFolders = ['gutenberg'];
                
                const distPath = resolve(__dirname, 'dist');
                
                if (fs.existsSync(distPath)) {
                    const items = fs.readdirSync(distPath);
                    items.forEach(item => {
                        if (!preserveFolders.includes(item)) {
                            const itemPath = path.join(distPath, item);
                            fs.rmSync(itemPath, { recursive: true, force: true });
                        }
                    });
                }
            }
        }
    ],
});
