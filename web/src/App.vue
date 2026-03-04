<template>
    <!-- Admin routes: rendu direct (layout gere par AdminRoot) -->
    <router-view v-if="isAdminPath" />

    <!-- Public layout -->
    <div v-else class="min-h-screen flex flex-col">
        <Navbar />
        <main class="flex-grow">
            <router-view />
        </main>
        <Footer />
        <CartDrawer />
    </div>

    <!-- Toast notifications (global) -->
    <ToastContainer />

    <!-- Confirm dialog (global) -->
    <ConfirmDialog />

    <!-- Cookie consent banner (RGPD) - uniquement sur le site public -->
    <CookieBanner v-if="!isAdminPath" />
</template>

<script setup lang="ts">
import {computed, onMounted} from 'vue'
import {useRoute} from 'vue-router'
import {useCartStore} from '@/stores/cart'
import Navbar from './components/Navbar.vue'
import Footer from './components/Footer.vue'
import ToastContainer from './components/ui/ToastContainer.vue'
import ConfirmDialog from './components/ui/ConfirmDialog.vue'
import CartDrawer from './components/cart/CartDrawer.vue'
import CookieBanner from './components/CookieBanner.vue'

const route = useRoute()
const cartStore = useCartStore()
const isPrerendering = (window as typeof globalThis & { __PRERENDERING__?: boolean }).__PRERENDERING__ === true

// Detection du path admin pour le routing de layout
const isAdminPath = computed(() => route.path.startsWith('/admin'))

// Initialize cart store on app mount (uniquement côté public, pas en prerendering)
onMounted(() => {
    if (!isAdminPath.value && !isPrerendering) {
        cartStore.initialize()
    }
})
</script>
