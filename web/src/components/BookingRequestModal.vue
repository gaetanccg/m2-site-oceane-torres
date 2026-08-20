<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                    @click="close"
                />

                <!-- Modal -->
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] max-h-[90dvh] overflow-y-auto mx-2 sm:mx-0">
                    <!-- Header -->
                    <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <h2 class="text-xl font-light">Demande de shooting</h2>
                        <button
                            @click="close"
                            class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Success State -->
                    <div v-if="isSuccess" class="p-8 text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-medium mb-2">Demande envoyée !</h3>
                        <p class="text-gray-600 font-light mb-6">
                            Merci pour votre demande. Je vous recontacterai tres rapidement pour discuter de votre projet.
                        </p>
                        <button
                            @click="close"
                            class="px-6 py-3 bg-gold text-white rounded-lg hover:opacity-90 transition-opacity"
                        >
                            Fermer
                        </button>
                    </div>

                    <!-- Form -->
                    <form v-else @submit.prevent="submit" class="p-6 space-y-5">
                        <p class="text-gray-600 font-light text-sm mb-4">
                            Remplissez ce formulaire pour m'envoyer une demande de shooting. Je vous recontacterai dans les plus brefs délais.
                        </p>

                        <!-- Nom -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nom complet <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                :class="[
                                    'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                    fieldErrors.name ? 'border-red-300 bg-red-50' : 'border-gray-200'
                                ]"
                                placeholder="Votre nom"
                            />
                            <p v-if="fieldErrors.name" class="mt-1 text-sm text-red-600">{{ fieldErrors.name }}</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                :class="[
                                    'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                    fieldErrors.email ? 'border-red-300 bg-red-50' : 'border-gray-200'
                                ]"
                                placeholder="votre@email.com"
                            />
                            <p v-if="fieldErrors.email" class="mt-1 text-sm text-red-600">{{ fieldErrors.email }}</p>
                        </div>

                        <!-- Telephone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Telephone
                            </label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                :class="[
                                    'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors',
                                    fieldErrors.phone ? 'border-red-300 bg-red-50' : 'border-gray-200'
                                ]"
                                placeholder="06 XX XX XX XX"
                            />
                            <p v-if="fieldErrors.phone" class="mt-1 text-sm text-red-600">{{ fieldErrors.phone }}</p>
                        </div>

                        <!-- Prestation -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Type de prestation <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.prestation_id"
                                required
                                :class="[
                                    'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors bg-white',
                                    fieldErrors.prestation_id ? 'border-red-300 bg-red-50' : 'border-gray-200'
                                ]"
                            >
                                <option value="" disabled>Selectionnez une prestation</option>
                                <option v-for="p in prestations" :key="p.id" :value="p.id">
                                    {{ p.title }}
                                </option>
                            </select>
                            <p v-if="fieldErrors.prestation_id" class="mt-1 text-sm text-red-600">{{ fieldErrors.prestation_id }}</p>
                        </div>

                        <!-- Dates souhaitées -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Dates souhaitées <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="form.date_preferences"
                                required
                                rows="3"
                                :class="[
                                    'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors resize-none',
                                    fieldErrors.date_preferences ? 'border-red-300 bg-red-50' : 'border-gray-200'
                                ]"
                                placeholder="Ex: Week-end du 15 fevrier, ou en semaine entre le 10 et le 20 mars..."
                            />
                            <p v-if="fieldErrors.date_preferences" class="mt-1 text-sm text-red-600">{{ fieldErrors.date_preferences }}</p>
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Message (optionnel)
                            </label>
                            <textarea
                                v-model="form.message"
                                rows="3"
                                class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors resize-none"
                                placeholder="Parlez-moi de votre projet, vos idées..."
                            />
                        </div>

                        <!-- RGPD Consent -->
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.gdpr_consent"
                                    required
                                    class="mt-1 rounded border-gray-300 text-gold focus:ring-gold"
                                />
                                <div class="text-sm text-gray-600">
                                    <span class="text-red-500">*</span>
                                    J'accepte que mes données personnelles soient traitées pour la gestion de ma demande de shooting.
                                    Ces données ne seront jamais cedées a des tiers.
                                    <a href="/mentions-legales" target="_blank" class="text-gold hover:underline">
                                        En savoir plus
                                    </a>
                                </div>
                            </label>
                        </div>

                        <!-- Error message -->
                        <div v-if="error" class="p-3 bg-red-50 text-red-600 rounded-lg text-sm">
                            {{ error }}
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="isLoading"
                            class="w-full px-6 py-4 bg-gold text-white rounded-lg font-medium hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <span v-if="isLoading" class="animate-spin">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span>{{ isLoading ? 'Envoi en cours...' : 'Envoyer ma demande' }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { api, ApiServiceError } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { ERROR_MESSAGES } from '@/utils/errorHandler'

interface Prestation {
    id: string
    title: string
}

defineProps<{
    isOpen: boolean
}>()

const emit = defineEmits<{
    (e: 'close'): void
}>()

const toast = useToast()
const prestations = ref<Prestation[]>([])
const isLoading = ref(false)
const isSuccess = ref(false)
const error = ref('')
const fieldErrors = ref<Record<string, string>>({})

const form = reactive({
    name: '',
    email: '',
    phone: '',
    prestation_id: '',
    date_preferences: '',
    message: '',
    gdpr_consent: false,
})

function close() {
    emit('close')
    // Reset form after close animation
    setTimeout(() => {
        isSuccess.value = false
        error.value = ''
        fieldErrors.value = {}
        form.name = ''
        form.email = ''
        form.phone = ''
        form.prestation_id = ''
        form.date_preferences = ''
        form.message = ''
        form.gdpr_consent = false
    }, 300)
}

async function loadPrestations() {
    try {
        const response = await api.getPrestations()
        // L'API publique retourne { prestations: [...] }
        if (response && 'prestations' in response) {
            const data = (response as { prestations: Array<{ id: string; title: string }> }).prestations
            prestations.value = data.map(p => ({ id: String(p.id || ''), title: p.title }))
        } else if (response && Array.isArray(response.data)) {
            prestations.value = response.data.map(p => ({ id: String(p.id || ''), title: p.title }))
        }
    } catch (err) {
        if (err instanceof ApiServiceError) {
            toast.error('Impossible de charger les prestations', err.apiError.message)
        }
    }
}

async function submit() {
    error.value = ''
    fieldErrors.value = {}

    if (!form.gdpr_consent) {
        error.value = 'Vous devez accepter le traitement de vos données personnelles pour continuer.'
        return
    }

    isLoading.value = true

    try {
        await api.sendBookingRequest({
            name: form.name,
            email: form.email,
            phone: form.phone || undefined,
            prestation_id: form.prestation_id,
            date_preferences: form.date_preferences,
            message: form.message || undefined,
            gdpr_consent: form.gdpr_consent,
        })
        isSuccess.value = true
        toast.success('Demande envoyée', 'Nous vous recontacterons très rapidement.')
    } catch (err) {
        if (err instanceof ApiServiceError) {
            const apiError = err.apiError

            // Erreurs de validation par champ
            if (apiError.isValidationError && apiError.errors) {
                fieldErrors.value = {}
                for (const [field, messages] of Object.entries(apiError.errors)) {
                    fieldErrors.value[field] = messages[0]
                }
                error.value = 'Veuillez corriger les erreurs ci-dessous.'
            } else if (apiError.isNetworkError) {
                error.value = ERROR_MESSAGES.generic.networkError
            } else if (apiError.isServerError) {
                error.value = ERROR_MESSAGES.generic.serverError
            } else {
                error.value = apiError.message
            }
        } else {
            error.value = ERROR_MESSAGES.generic.unknownError
        }
    } finally {
        isLoading.value = false
    }
}

onMounted(loadPrestations)
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .relative,
.modal-leave-active .relative {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
    opacity: 0;
}
</style>
