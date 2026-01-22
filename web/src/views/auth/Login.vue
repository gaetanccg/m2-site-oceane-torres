<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <!-- Logo -->
            <div class="text-center mb-8">
                <router-link to="/" class="inline-block">
                    <img src="/logo-oceane.png" alt="Oceane Torres" class="h-24 mx-auto" />
                </router-link>
                <h2 class="mt-4 text-2xl font-light text-gray-900">Connexion</h2>
                <p class="mt-2 text-sm text-gray-500">Accedez a votre espace client</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <!-- Error message -->
                <div v-if="authStore.error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                    {{ authStore.error }}
                </div>

                <form @submit.prevent="handleLogin" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                            placeholder="votre@email.com"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                            placeholder="********"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="authStore.isLoading"
                        class="w-full py-3 bg-gold text-white rounded-lg font-medium hover:bg-gold/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <svg v-if="authStore.isLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ authStore.isLoading ? 'Connexion...' : 'Se connecter' }}
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-500">
                        Pas encore de compte ?
                        <router-link to="/inscription" class="text-gold hover:underline font-medium">
                            S'inscrire
                        </router-link>
                    </p>
                </div>
            </div>

            <!-- Back to home -->
            <div class="mt-6 text-center">
                <router-link to="/" class="text-sm text-gray-500 hover:text-gold transition-colors">
                    &larr; Retour a l'accueil
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const form = reactive({
    email: '',
    password: '',
})

onMounted(() => {
    authStore.clearError()
})

async function handleLogin() {
    const success = await authStore.loginClient(form.email, form.password)
    if (success) {
        const redirect = route.query.redirect as string
        router.push(redirect || '/mon-compte')
    }
}
</script>
