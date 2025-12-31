import {defineConfig} from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [vue()],
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
        assetsInlineLimit: 0
    }
})
