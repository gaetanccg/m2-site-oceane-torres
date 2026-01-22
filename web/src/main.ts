import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'

const app = createApp(App)

// Plugins
app.use(createPinia())
app.use(router)

// Wait for router to be ready before mounting to prevent flash
router.isReady().then(() => {
    app.mount('#app')
})
