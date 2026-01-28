<template>
    <!-- Admin: show loader while auth initializes -->
    <div v-if="isAdminPath && !authStore.isInitialized" class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="text-center">
            <div class="w-12 h-12 border-2 border-gray-300 border-t-gray-600 rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-gray-500 text-sm">Chargement...</p>
        </div>
    </div>

    <!-- Admin layout (handled by AdminLayout component) -->
    <router-view v-else-if="isAdminPath" />

    <!-- Public layout - renders immediately -->
    <div v-else class="min-h-screen flex flex-col">
        <Navbar />
        <main class="flex-grow">
            <router-view />
        </main>
        <Footer />
    </div>
</template>

<script setup lang="ts">
import {computed} from 'vue'
import {useRoute} from 'vue-router'
import {useAuthStore} from '@/stores/auth'
import Navbar from './components/Navbar.vue'
import Footer from './components/Footer.vue'

const route = useRoute()
const authStore = useAuthStore()

// Check path directly - more reliable than route.meta on initial load
const isAdminPath = computed(() => route.path.startsWith('/admin'))
</script>
