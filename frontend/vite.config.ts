import path from 'node:path';
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [react()],

        resolve: {
            alias: {
                '@': path.resolve(__dirname, './src'),
            },
        },

        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            proxy: {
                // Forward /api → Laravel during dev so Sanctum cookie auth works
                '/api': {
                    target: env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000',
                    changeOrigin: true,
                    secure: false,
                },
                '/sanctum': {
                    target: env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000',
                    changeOrigin: true,
                    secure: false,
                },
            },
        },

        build: {
            outDir: 'dist',
            sourcemap: true,
            rollupOptions: {
                output: {
                    // Function-style manualChunks avoids the circular-chunk
                    // warning from rollup that fires when @tanstack/* (which
                    // depends on react) is grouped separately from react itself.
                    manualChunks(id) {
                        if (!id.includes('node_modules')) return undefined;
                        if (id.includes('node_modules/recharts/')) return 'charts';
                        if (id.match(/node_modules\/(@tanstack|react|react-dom|scheduler)\//)) {
                            return 'vendor-react';
                        }
                        return undefined;
                    },
                },
            },
        },

        test: {
            globals: true,
            environment: 'jsdom',
            setupFiles: ['./src/test/setup.ts'],
            // vitest runs unit + component tests; Playwright owns e2e/.
            // Without this exclude, vitest would try to parse Playwright's
            // page-fixture syntax and fail.
            exclude: ['e2e/**', 'node_modules/**', 'dist/**'],
        },
    };
});
