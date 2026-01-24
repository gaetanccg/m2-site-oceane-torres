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

// Initialize auth before mounting to restore session from localStorage
const authStore = useAuthStore()

// Wait for auth initialization and router to be ready before mounting
Promise.all([
    authStore.initialize(),
    router.isReady()
]).then(() => {
    app.mount('#app')
})
