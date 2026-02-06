<template>
    <!-- Loader pendant l'initialisation auth -->
    <div v-if="!authStore.isInitialized" class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="text-center">
            <div class="w-12 h-12 border-2 border-gray-300 border-t-gray-600 rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-gray-500 text-sm">Chargement...</p>
        </div>
    </div>

    <!-- Admin layout avec contenu -->
    <AdminLayout v-else>
        <router-view />
    </AdminLayout>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import AdminLayout from '@/components/admin/AdminLayout.vue'

const authStore = useAuthStore()
</script>
