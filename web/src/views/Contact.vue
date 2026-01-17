<template>
    <div class="pt-20">
        <!-- Hero Section -->
        <section class="relative py-10 px-6 lg:px-12 bg-gradient-to-br from-cream via-white to-cream overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-gold/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2" />
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-gold/10 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2" />

            <div class="max-w-6xl mx-auto relative">
                <div class="text-center mb-16">
                    <h1 class="text-5xl md:text-6xl font-light mb-6">Contact</h1>
                    <p class="text-xl text-gray-600 font-light max-w-2xl mx-auto">
                        Vous avez un projet en tête ? Parlons-en ensemble !
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">
                    <!-- Contact Cards -->
                    <div class="space-y-6">
                        <!-- Email Card -->
                        <a
                            :href="`mailto:${CONTACT_INFO.email}`"
                            class="group block bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-gold/30 transition-all duration-300"
                        >
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gold/10 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-gold/20 transition-colors">
                                    <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium mb-1 group-hover:text-gold transition-colors">Email</h3>
                                    <p class="text-gray-600">{{ CONTACT_INFO.email }}</p>
                                    <p class="text-sm text-gray-400 mt-2">Réponse sous 24h</p>
                                </div>
                                <svg class="w-5 h-5 text-gray-300 group-hover:text-gold group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>

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
                                    <h3 class="text-lg font-medium mb-1 group-hover:text-gold transition-colors">Téléphone</h3>
                                    <p class="text-gray-600">{{ CONTACT_INFO.phone }}</p>
                                    <p class="text-sm text-gray-400 mt-2">Pour les urgences</p>
                                </div>
                                <svg class="w-5 h-5 text-gray-300 group-hover:text-gold group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>
                    </div>

                    <!-- Practical Info Box -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-gold/10 to-gold/5 px-8 py-6 border-b border-gray-100">
                            <h3 class="text-2xl font-light">Informations pratiques</h3>
                        </div>

                        <div class="p-8 space-y-6">
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
                                        Auvergne-Rhône-Alpes<br>
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

                        <div class="px-8 pb-8">
                            <a
                                :href="`mailto:${CONTACT_INFO.email}`"
                                class="block w-full px-8 py-4 bg-black text-white hover:bg-gold transition-all duration-300 text-sm uppercase tracking-widest font-light text-center rounded-lg"
                            >
                                Demander un devis gratuit
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Steps Before Contacting -->
        <section class="py-20 px-6 lg:px-12 bg-white">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-4">
                    <h2 class="text-3xl md:text-4xl font-light mb-4">Avant de me contacter</h2>
                    <p class="text-gray-600 font-light">Quelques étapes pour préparer notre échange</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
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

        <!-- CTA Section -->
        <section class="py-16 px-6 lg:px-12 bg-black text-white">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-3xl md:text-4xl font-light text-white mb-6">
                    Prêt(e) à immortaliser vos moments ?
                </h2>
                <p class="text-gray-400 font-light mb-8 max-w-2xl mx-auto">
                    Que ce soit pour un mariage, un portrait, ou tout autre événement, je serai ravie de discuter de votre projet.
                </p>
                <a
                    :href="`mailto:${CONTACT_INFO.email}`"
                    class="inline-block px-10 py-4 bg-gold text-white hover:bg-white hover:text-black transition-all duration-300 text-sm uppercase tracking-widest font-light rounded-lg"
                >
                    Contactez-moi
                </a>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import {SOCIAL_LINKS, CONTACT_INFO, BUSINESS_INFO} from '@/config/constants'
import {IconInstagram} from '@/components/icons'

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
</script>
