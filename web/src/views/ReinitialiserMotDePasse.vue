<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-16 bg-gray-50">
        <div class="w-full max-w-md bg-white rounded-xl border border-gray-200 p-8">
            <h1 class="text-2xl font-light text-gray-900 mb-2">Nouveau mot de passe</h1>

            <div v-if="!token || !email" class="p-4 rounded-lg bg-red-50 text-red-800 text-sm">
                Ce lien est incomplet. Demandez un nouveau lien de réinitialisation.
                <RouterLink to="/mot-de-passe-oublie" class="underline">Recommencer</RouterLink>
            </div>

            <div v-else-if="reussi" class="space-y-4">
                <div class="p-4 rounded-lg bg-green-50 text-green-800 text-sm">
                    Votre mot de passe a été modifié. Vous pouvez vous connecter.
                </div>
                <RouterLink
                    to="/"
                    class="block text-center px-4 py-3 bg-gold text-white rounded-lg hover:bg-gold/90"
                >
                    Aller à l'accueil
                </RouterLink>
            </div>

            <form v-else @submit.prevent="valider" class="space-y-4">
                <p class="text-sm text-gray-500">
                    Pour {{ email }}. Au moins 12 caractères.
                </p>

                <div>
                    <label for="password" class="block text-sm text-gray-700 mb-1">Nouveau mot de passe</label>
                    <input
                        id="password"
                        v-model="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
                    />
                </div>

                <div>
                    <label for="confirmation" class="block text-sm text-gray-700 mb-1">Confirmation</label>
                    <input
                        id="confirmation"
                        v-model="confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
                    />
                </div>

                <p v-if="erreur" class="text-sm text-red-600">{{ erreur }}</p>

                <button
                    type="submit"
                    :disabled="isLoading || password.length < 12 || password !== confirmation"
                    class="w-full px-4 py-3 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors disabled:opacity-50"
                >
                    {{ isLoading ? 'Enregistrement...' : 'Changer le mot de passe' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { authApi } from '@/services/authApi'
import { ApiError } from '@/services/baseApi'

const route = useRoute()

const token = (route.query.token as string) ?? ''
const email = (route.query.email as string) ?? ''

const password = ref('')
const confirmation = ref('')
const isLoading = ref(false)
const reussi = ref(false)
const erreur = ref('')

async function valider() {
    isLoading.value = true
    erreur.value = ''

    try {
        await authApi.resetPassword({
            email,
            token,
            password: password.value,
            password_confirmation: confirmation.value,
        })
        reussi.value = true
    } catch (e) {
        erreur.value = e instanceof ApiError ? e.apiError.message : 'Une erreur est survenue.'
    } finally {
        isLoading.value = false
    }
}
</script>
