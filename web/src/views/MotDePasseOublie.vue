<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-16 bg-gray-50">
        <div class="w-full max-w-md bg-white rounded-xl border border-gray-200 p-8">
            <h1 class="text-2xl font-light text-gray-900 mb-2">Mot de passe oublié</h1>
            <p class="text-sm text-gray-500 mb-6">
                Indiquez votre adresse email : si un compte existe, vous recevrez un lien de
                réinitialisation.
            </p>

            <div v-if="envoye" class="p-4 rounded-lg bg-green-50 text-green-800 text-sm">
                {{ message }}
            </div>

            <form v-else @submit.prevent="envoyer" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm text-gray-700 mb-1">Adresse email</label>
                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
                    />
                </div>

                <p v-if="erreur" class="text-sm text-red-600">{{ erreur }}</p>

                <button
                    type="submit"
                    :disabled="isLoading || !email"
                    class="w-full px-4 py-3 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors disabled:opacity-50"
                >
                    {{ isLoading ? 'Envoi...' : 'Envoyer le lien' }}
                </button>
            </form>

            <RouterLink to="/" class="block mt-6 text-sm text-gray-500 hover:text-gray-700">
                ← Retour à l'accueil
            </RouterLink>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { authApi } from '@/services/authApi'
import { ApiError } from '@/services/baseApi'

const email = ref('')
const isLoading = ref(false)
const envoye = ref(false)
const message = ref('')
const erreur = ref('')

async function envoyer() {
    isLoading.value = true
    erreur.value = ''

    try {
        const response = await authApi.forgotPassword(email.value)
        message.value = response.message
        envoye.value = true
    } catch (e) {
        erreur.value = e instanceof ApiError ? e.apiError.message : 'Une erreur est survenue.'
    } finally {
        isLoading.value = false
    }
}
</script>
