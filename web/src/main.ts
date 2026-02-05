import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { onSessionExpired } from './utils/authEvents'

const app = createApp(App)
const pinia = createPinia()

// Plugins
app.use(pinia)
app.use(router)

// Mount app immediately for fast public page loading
app.mount('#app')

// Initialize auth in background (admin pages will show loader until ready)
const authStore = useAuthStore()
authStore.initialize()

// Écouter les events de session expirée pour gérer la déconnexion proprement
onSessionExpired(() => {
    // Vérifier qu'on n'est pas déjà sur la page d'accueil avec le login ouvert
    if (router.currentRoute.value.path !== '/' || !router.currentRoute.value.query.login) {
        // Nettoyer la session et rediriger vers login
        authStore.logout().then(() => {
            router.push({ name: 'home', query: { login: 'true' } })
        })
    }
})
