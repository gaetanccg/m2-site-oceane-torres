<template>
    <!-- Public layout with Navbar and Footer -->
    <div v-if="layout === 'public'" class="min-h-screen flex flex-col">
        <Navbar />
        <main class="flex-grow">
            <router-view />
        </main>
        <Footer />
    </div>

    <!-- Admin layout (handled by AdminLayout component) or no layout -->
    <router-view v-else />
</template>

<script setup lang="ts">
import {computed} from 'vue'
import {useRoute} from 'vue-router'
import Navbar from './components/Navbar.vue'
import Footer from './components/Footer.vue'

const route = useRoute()

const layout = computed(() => {
    return (route.meta.layout as string) || 'public'
})
</script>
