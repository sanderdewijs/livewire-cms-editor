import { defineConfig } from 'vite'

// Builds the pre-compiled bundle shipped in dist/ (ADR-007).
// Alpine is treated as an external peer (provided by the host app).
export default defineConfig({
    build: {
        lib: {
            entry: 'resources/js/index.js',
            name: 'CmsEditor',
            fileName: () => 'cms-editor.js',
            formats: ['es'],
        },
        outDir: 'dist',
        emptyOutDir: true,
        rollupOptions: {
            external: ['alpinejs'],
        },
    },
})
