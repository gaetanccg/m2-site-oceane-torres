<template>
    <div class="pt-20 min-h-screen bg-gray-50">
        <section class="py-16 px-6 lg:px-12">
            <div class="max-w-lg mx-auto">
                <div class="text-center mb-12">
                    <h1 class="text-4xl font-light mb-4">Galerie Privée</h1>
                    <p class="text-gray-600 font-light">
                        Entrez le code à 6 caractères que vous avez reçu pour accéder à votre galerie.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-8 md:p-12">
                    <form @submit.prevent="handleSubmit" class="space-y-8">
                        <ShareCodeInput
                            ref="codeInputRef"
                            v-model="code"
                            :has-error="hasError"
                            :error-message="errorMessage"
                            @complete="handleSubmit"
                        />

                        <button
                            type="submit"
                            :disabled="isLoading || code.length !== 6"
                            class="w-full py-4 px-6 bg-gold text-white rounded-lg font-medium text-lg hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <span v-if="isLoading" class="animate-spin">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                            </span>
                            <span>{{ isLoading ? 'Verification...' : 'Accéder à ma galerie' }}</span>
                        </button>
                    </form>

                    <div class="mt-8 pt-8 border-t border-gray-100 text-center">
                        <p class="text-sm text-gray-500 font-light">
                            Vous n'avez pas de code ? Contactez-moi.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import {ref} from 'vue'
import {useRouter} from 'vue-router'
import ShareCodeInput from '@/components/ShareCodeInput.vue'
import {API_CONFIG} from '@/config/constants'

const router = useRouter()

const code = ref('')
const isLoading = ref(false)
const hasError = ref(false)
const errorMessage = ref('Code invalide')
const codeInputRef = ref<InstanceType<typeof ShareCodeInput> | null>(null)

const handleSubmit = async () => {
    if (code.value.length !== 6 || isLoading.value) return

    isLoading.value = true
    hasError.value = false

    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/galleries/code/${code.value}`, {
            headers: {
                'Accept': 'application/json'
            }
        })

        if (response.ok) {
            router.push(`/gallery/${code.value}`)
        } else {
            const data = await response.json()
            hasError.value = true
            errorMessage.value = data.message || 'Code invalide'
            codeInputRef.value?.clear()
        }
    } catch (error) {
        hasError.value = true
        errorMessage.value = 'Erreur de connexion'
    } finally {
        isLoading.value = false
    }
}
</script>
