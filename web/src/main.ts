import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'

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
