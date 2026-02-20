<template>
    <div class="pt-20">
        <!-- Hero Section -->
        <section class="relative py-10 px-6 lg:px-12 bg-gradient-to-br from-cream via-white to-cream overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-gold/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2" />
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-gold/10 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2" />

            <div class="max-w-6xl mx-auto relative">
                <div class="text-center mb-12">
                    <h1 class="text-5xl md:text-6xl font-light mb-6">Contact</h1>
                    <p class="text-xl text-gray-600 font-light max-w-2xl mx-auto">
                        Vous avez un projet en tête ? Parlons-en ensemble !
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">
                    <!-- Contact Form -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                        <h2 class="text-2xl font-light mb-6">Envoyez-moi un message</h2>

                        <!-- Success Message -->
                        <div v-if="isSuccess" class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                            <svg class="w-12 h-12 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="text-lg font-medium text-green-800 mb-2">Message envoyé !</h3>
                            <p class="text-green-600 font-light">Je vous repondrai dans les plus brefs delais.</p>
                            <button
                                @click="resetForm"
                                class="mt-4 px-6 py-2 text-sm text-green-700 hover:text-green-800 underline"
                            >
                                Envoyer un autre message
                            </button>
                        </div>

                        <!-- Form -->
                        <form v-else @submit.prevent="handleSubmit" class="space-y-6">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nom complet <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                                    :class="{'border-red-300': errors.name}"
                                    placeholder="Votre nom"
                                />
                                <p v-if="errors.name" class="mt-1 text-sm text-red-500">{{ errors.name }}</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                                    :class="{'border-red-300': errors.email}"
                                    placeholder="votre@email.com"
                                />
                                <p v-if="errors.email" class="mt-1 text-sm text-red-500">{{ errors.email }}</p>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Telephone <span class="text-gray-400 text-xs">(optionnel)</span>
                                </label>
                                <input
                                    id="phone"
                                    v-model="form.phone"
                                    type="tel"
                                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors"
                                    placeholder="06 00 00 00 00"
                                />
                            </div>

                            <!-- Subject -->
                            <div>
                                <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                                    Sujet <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="subject"
                                    v-model="form.subject"
                                    required
                                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors bg-white"
                                    :class="{'border-red-300': errors.subject}"
                                >
                                    <option value="" disabled>Selectionnez un sujet</option>
                                    <option value="Demande d'information">Demande d'information</option>
                                    <option value="Devis">Devis</option>
                                    <option value="Je souhaite reserver une seance photo">Je souhaite reserver une séance photo</option>
                                    <option value="Je me reconnais sur une photo">Je me reconnais sur une photo</option>
                                    <option value="Commander une carte cadeau">Commander une carte cadeau</option>
                                    <option value="Question sur une prestation">Question sur une prestation</option>
                                    <option value="Autre">Autre</option>
                                </select>
                                <p v-if="errors.subject" class="mt-1 text-sm text-red-500">{{ errors.subject }}</p>
                            </div>

                            <!-- Message -->
                            <div>
                                <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                                    Message <span class="text-red-500">*</span>
                                </label>
                                <textarea
                                    id="message"
                                    v-model="form.message"
                                    required
                                    rows="5"
                                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold transition-colors resize-none"
                                    :class="{'border-red-300': errors.message}"
                                    placeholder="Decrivez votre projet ou posez votre question..."
                                ></textarea>
                                <p v-if="errors.message" class="mt-1 text-sm text-red-500">{{ errors.message }}</p>
                            </div>

                            <!-- GDPR Consent -->
                            <div>
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input
                                        v-model="form.gdprConsent"
                                        type="checkbox"
                                        required
                                        class="mt-1 w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
                                    />
                                    <span class="text-sm text-gray-600">
                                        J'accepte que mes données soient utilisées pour me recontacter dans le cadre de ma demande.
                                        <span class="text-red-500">*</span>
                                    </span>
                                </label>
                                <p v-if="errors.gdprConsent" class="mt-1 text-sm text-red-500">{{ errors.gdprConsent }}</p>
                            </div>

                            <!-- Error Message -->
                            <div v-if="submitError" class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <p class="text-red-600 text-sm">{{ submitError }}</p>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                :disabled="isLoading"
                                class="w-full px-8 py-4 bg-black text-white hover:bg-gold transition-all duration-300 text-sm uppercase tracking-widest font-light rounded-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <svg v-if="isLoading" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ isLoading ? 'Envoi en cours...' : 'Envoyer le message' }}</span>
                            </button>
                        </form>
                    </div>

                    <!-- Contact Info -->
                    <div class="space-y-6">
                        <!-- Email Card -->
                        <div
                            class="group block bg-white rounded-2xl p-6 shadow-sm border border-gray-100"
                        >
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gold/10 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium mb-1">Email</h3>
                                    <p class="text-gray-600">{{ CONTACT_INFO.email }}</p>
                                    <p class="text-sm text-gray-400 mt-2">Réponse sous 24h</p>
                                </div>
                            </div>
                        </div>

                        <!-- Instagram Card -->
                        <a
                            :href="SOCIAL_LINKS[0].url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group block bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-gold/30 transition-all duration-300"
                        >
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500/10 to-pink-500/10 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:from-purple-500/20 group-hover:to-pink-500/20 transition-colors">
                                    <IconInstagram class="w-6 h-6 text-pink-500" />
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium mb-1 group-hover:text-gold transition-colors">Instagram</h3>
                                    <p class="text-gray-600">@{{ CONTACT_INFO.instagram }}</p>
                                    <p class="text-sm text-gray-400 mt-2">Pour voir mes dernières réalisations</p>
                                </div>
                                <svg class="w-5 h-5 text-gray-300 group-hover:text-gold group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>

                        <!-- Phone Card -->
                        <a
                            :href="`tel:${CONTACT_INFO.phone.replace(/\s/g, '')}`"
                            class="group block bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-gold/30 transition-all duration-300"
                        >
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-green-500/10 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-green-500/20 transition-colors">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium mb-1 group-hover:text-gold transition-colors">Telephone</h3>
                                    <p class="text-gray-600">{{ CONTACT_INFO.phone }}</p>
                                </div>
                                <svg class="w-5 h-5 text-gray-300 group-hover:text-gold group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>

                        <!-- Practical Info Box -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="bg-gradient-to-r from-gold/10 to-gold/5 px-6 py-4 border-b border-gray-100">
                                <h3 class="text-xl font-light">Informations pratiques</h3>
                            </div>

                            <div class="p-6 space-y-5">
                                <div class="flex gap-4">
                                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 mb-1">Horaires de contact</h4>
                                        <p class="text-gray-600 text-sm">
                                            {{ BUSINESS_INFO.workingHours.days }}<br>
                                            {{ BUSINESS_INFO.workingHours.hours }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex gap-4">
                                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 mb-1">Zone d'intervention</h4>
                                        <p class="text-gray-600 text-sm">
                                            Loire (42) et Rhône (69)<br>
                                            Saint-Étienne, Lyon, Rive-de-Gier, Saint-Chamond, Givors, Lorette<br>
                                            <span class="text-gray-400">Frais de déplacement au-delà de {{ BUSINESS_INFO.travelZone.free }}{{ BUSINESS_INFO.travelZone.unit }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex gap-4">
                                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 mb-1">Devis gratuit</h4>
                                        <p class="text-gray-600 text-sm">
                                            Sans engagement, contactez-moi pour discuter de votre projet.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Steps Before Contacting -->
        <section class="py-12 sm:py-20 px-6 lg:px-12 bg-white">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-6 sm:mb-8">
                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-light mb-3 sm:mb-4">Avant de me contacter</h2>
                    <p class="text-gray-600 font-light text-sm sm:text-base">Quelques étapes pour préparer notre échange</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
                    <div v-for="(step, index) in steps" :key="step.title" class="relative">
                        <div class="relative bg-cream rounded-2xl p-8 text-center">
                            <div class="w-12 h-12 mx-auto mb-6 flex items-center justify-center bg-white border-2 border-gold rounded-full">
                                <span class="text-gold font-semibold text-lg">{{ index + 1 }}</span>
                            </div>
                            <h3 class="font-medium text-lg mb-3">{{ step.title }}</h3>
                            <p class="text-gray-600 text-sm font-light leading-relaxed">{{ step.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import {ref, reactive, onMounted} from 'vue'
import {useRoute} from 'vue-router'
import {SOCIAL_LINKS, CONTACT_INFO, BUSINESS_INFO} from '@/config/constants'
import {IconInstagram} from '@/components/icons'
import {api} from '@/services/api'

const route = useRoute()

interface FormData {
    name: string
    email: string
    phone: string
    subject: string
    message: string
    gdprConsent: boolean
}

interface FormErrors {
    name?: string
    email?: string
    subject?: string
    message?: string
    gdprConsent?: string
}

// Map URL slugs to subject values
const subjectMap: Record<string, string> = {
    'carte-cadeau': 'Commander une carte cadeau',
    'photo': 'Je me reconnais sur une photo',
    'seance': 'Je souhaite reserver une seance photo',
    'devis': 'Devis',
    'info': 'Demande d\'information',
}

const form = reactive<FormData>({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    gdprConsent: false,
})

// Pre-select subject from query parameter
onMounted(() => {
    const subjectParam = route.query.sujet as string
    if (subjectParam && subjectMap[subjectParam]) {
        form.subject = subjectMap[subjectParam]
    }
})

const errors = reactive<FormErrors>({})
const isLoading = ref(false)
const isSuccess = ref(false)
const submitError = ref('')

const steps = [
    {
        title: 'Consultez le portfolio',
        description: 'Assurez-vous que mon style correspond à vos attentes'
    },
    {
        title: 'Vérifiez les tarifs',
        description: 'Consultez mes formules pour avoir une idée des prix'
    },
    {
        title: 'Préparez votre projet',
        description: 'Date souhaitée, type de séance, nombre de personnes, etc.'
    }
]

function validateForm(): boolean {
    // Reset errors
    Object.keys(errors).forEach(key => {
        delete errors[key as keyof FormErrors]
    })

    let isValid = true

    if (!form.name.trim()) {
        errors.name = 'Le nom est requis'
        isValid = false
    }

    if (!form.email.trim()) {
        errors.email = 'L\'email est requis'
        isValid = false
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        errors.email = 'L\'email n\'est pas valide'
        isValid = false
    }

    if (!form.subject) {
        errors.subject = 'Le sujet est requis'
        isValid = false
    }

    if (!form.message.trim()) {
        errors.message = 'Le message est requis'
        isValid = false
    }

    if (!form.gdprConsent) {
        errors.gdprConsent = 'Vous devez accepter les conditions'
        isValid = false
    }

    return isValid
}

async function handleSubmit() {
    submitError.value = ''

    if (!validateForm()) {
        return
    }

    isLoading.value = true

    try {
        await api.sendContactMessage({
            name: form.name.trim(),
            email: form.email.trim(),
            phone: form.phone.trim() || undefined,
            subject: form.subject,
            message: form.message.trim(),
            gdpr_consent: form.gdprConsent,
        })

        isSuccess.value = true
    } catch {
        submitError.value = 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer.'
    } finally {
        isLoading.value = false
    }
}

function resetForm() {
    form.name = ''
    form.email = ''
    form.phone = ''
    form.subject = ''
    form.message = ''
    form.gdprConsent = false
    isSuccess.value = false
    submitError.value = ''
    Object.keys(errors).forEach(key => {
        delete errors[key as keyof FormErrors]
    })
}
</script>
