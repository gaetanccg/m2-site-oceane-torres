import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { onSessionExpired } from './utils/authEvents'
import { initErrorMonitoring } from './utils/monitoring'

const app = createApp(App)
const pinia = createPinia()

// Plugins
app.use(pinia)
app.use(router)

// Skip runtime initialization during prerendering (no auth/cart session needed for static HTML)
const isPrerendering = (window as typeof globalThis & { __PRERENDERING__?: boolean }).__PRERENDERING__ === true

if (!isPrerendering) {
    void initErrorMonitoring(app, router)
}

// Mount app immediately for fast public page loading
app.mount('#app')

if (!isPrerendering) {
    // Initialize auth in background (admin pages will show loader until ready)
    const authStore = useAuthStore()
    authStore.initialize()

    // Écouter les events de session expirée pour gérer la déconnexion proprement
    onSessionExpired(() => {
        if (router.currentRoute.value.path !== '/' || !router.currentRoute.value.query.login) {
            authStore.logout().then(() => {
                router.push({ name: 'home', query: { login: 'true' } })
            })
        }
    })
}
