import {defineConfig} from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'
import {readFileSync} from 'fs'

// Release Sentry : rattache une erreur remontée à une version déployée.
const appVersion = JSON.parse(
    readFileSync(path.resolve(__dirname, './package.json'), 'utf-8')
).version

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [vue()],
    define: {
        __APP_VERSION__: JSON.stringify(appVersion),
        // Tree-shaking Sentry : retire le debug et le module de tracing.
        __SENTRY_DEBUG__: false,
        __SENTRY_TRACING__: false,
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './src')
        }
    },
    server: {
        port: 5173,
        host: true, // Écouter sur toutes les interfaces (nécessaire pour Docker)
        proxy: {
            // Proxy les requêtes /api vers le backend Laravel
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
                secure: false,
            }
        }
    },
    preview: {
        port: 4173,
        host: true,
    },
    build: {
        // Ne pas inliner les assets dans le JS
        assetsInlineLimit: 0,
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-vue': ['vue', 'vue-router', 'pinia'],
                },
            },
        },
    }
})
