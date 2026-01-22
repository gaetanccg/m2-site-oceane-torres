<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <!-- Logo -->
            <div class="text-center mb-8">
                <router-link to="/" class="inline-block">
                    <img src="/logo-oceane.png" alt="Oceane Torres" class="h-24 mx-auto" />
                </router-link>
                <h2 class="mt-4 text-2xl font-light text-gray-900">Inscription</h2>
                <p class="mt-2 text-sm text-gray-500">Creez votre compte client</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <!-- Error message -->
                <div v-if="authStore.error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                    {{ authStore.error }}
                </div>

                <form @submit.prevent="handleRegister" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prenom</label>
                            <input
                                v-model="form.first_name"
                                type="text"
                                required
                                autofocus
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                                placeholder="Prenom"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                            <input
                                v-model="form.last_name"
                                type="text"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                                placeholder="Nom"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                            placeholder="votre@email.com"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telephone (optionnel)</label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                            placeholder="06 XX XX XX XX"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            minlength="8"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                            placeholder="Minimum 8 caracteres"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                            placeholder="********"
                        />
                    </div>
                    <div class="flex items-start gap-2">
                        <input
                            v-model="form.gdpr_consent"
                            type="checkbox"
                            required
                            class="mt-1 w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
                        />
                        <label class="text-sm text-gray-600">
                            J'accepte que mes données soient traitées conformement a la
                            <router-link to="/mentions-legales" class="text-gold hover:underline">
                                politique de confidentialite
                            </router-link>
                        </label>
                    </div>
                    <button
                        type="submit"
                        :disabled="authStore.isLoading || !form.gdpr_consent"
                        class="w-full py-3 bg-gold text-white rounded-lg font-medium hover:bg-gold/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <svg v-if="authStore.isLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ authStore.isLoading ? 'Inscription...' : 'S\'inscrire' }}
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-500">
                        Deja un compte ?
                        <router-link to="/connexion" class="text-gold hover:underline font-medium">
                            Se connecter
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
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    gdpr_consent: false,
})

onMounted(() => {
    authStore.clearError()
})

async function handleRegister() {
    if (form.password !== form.password_confirmation) {
        authStore.error = 'Les mots de passe ne correspondent pas'
        return
    }

    const success = await authStore.register({
        first_name: form.first_name,
        last_name: form.last_name,
        email: form.email,
        phone: form.phone || undefined,
        password: form.password,
        password_confirmation: form.password_confirmation,
    })

    if (success) {
        router.push('/mon-compte')
    }
}
</script>
