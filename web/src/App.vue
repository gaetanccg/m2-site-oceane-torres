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
import CartDrawer from './components/cart/CartDrawer.vue'
import CookieBanner from './components/CookieBanner.vue'

const route = useRoute()
const cartStore = useCartStore()

// Detection du path admin pour le routing de layout
const isAdminPath = computed(() => route.path.startsWith('/admin'))

// Initialize cart store on app mount (uniquement cote public)
onMounted(() => {
    if (!isAdminPath.value) {
        cartStore.initialize()
    }
})
</script>
