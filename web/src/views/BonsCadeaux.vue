<template>
    <div class="pt-20">
        <!-- Bons Cadeaux Section -->
        <section class="py-4 px-6 lg:px-12 bg-white relative overflow-hidden">
            <div class="max-w-4xl mx-auto relative">
                <PageHeader
                    title="Bons Cadeaux"
                    subtitle="Offrez un moment unique à vos proches"
                />

                <!-- Single Gift Card Option -->
                <div class="bg-black text-white p-10 lg:p-16 text-center border-2 border-gold mb-16">
                    <div class="max-w-2xl mx-auto">
                        <h3 class="text-3xl lg:text-4xl font-light mb-4">Carte Cadeau Personnalisée</h3>
                        <p class="text-gray-400 text-lg mb-8">
                            Choisissez le montant que vous souhaitez offrir
                        </p>

                        <div class="text-gold text-6xl lg:text-7xl font-light mb-8">
                            À partir de 35€
                        </div>

                        <p class="text-gray-300 font-light mb-10 text-lg leading-relaxed">
                            Offrez une carte cadeau du montant de votre choix, utilisable sur tous les types de shooting.
                            La personne qui la reçoit pourra l'utiliser librement pour réserver la séance de son choix.
                        </p>

                        <!-- Features -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left mb-12">
                            <div v-for="feature in features" :key="feature" class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-gold mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-gray-300 font-light">{{ feature }}</span>
                            </div>
                        </div>

                        <!-- CTA Buttons -->
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <a
                                :href="`mailto:${CONTACT_INFO.email}?subject=Demande de carte cadeau`"
                                class="w-full sm:w-auto inline-block px-10 py-4 bg-gold text-black hover:bg-gold/90 transition-all duration-300 text-sm uppercase tracking-widest font-medium"
                            >
                                Commander par mail
                            </a>
                            <a
                                :href="SOCIAL_LINKS[0].url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full sm:w-auto inline-block px-10 py-4 border-2 border-gold text-gold hover:bg-gold hover:text-black transition-all duration-300 text-sm uppercase tracking-widest font-light"
                            >
                                Commander sur Instagram
                            </a>
                        </div>
                    </div>
                </div>

                <!-- How it works -->
                <div class="mb-16">
                    <h2 class="text-2xl font-light mb-8 text-center">Comment ça marche ?</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-gold/10 text-gold rounded-full flex items-center justify-center mx-auto mb-4 text-xl font-light">1</div>
                            <h3 class="font-medium mb-2">Contactez-moi</h3>
                            <p class="text-gray-500 font-light text-sm">Indiquez-moi le montant souhaité, le nom de la personne à qui vous l'offrez ainsi que le message que vous voulez laisser</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-gold/10 text-gold rounded-full flex items-center justify-center mx-auto mb-4 text-xl font-light">2</div>
                            <h3 class="font-medium mb-2">Recevez la carte</h3>
                            <p class="text-gray-500 font-light text-sm">Je vous envoie une carte cadeau personnalisée par mail, prête à offrir</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-gold/10 text-gold rounded-full flex items-center justify-center mx-auto mb-4 text-xl font-light">3</div>
                            <h3 class="font-medium mb-2">Réservation</h3>
                            <p class="text-gray-500 font-light text-sm">La personne me contacte pour organiser son shooting quand elle le souhaite</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-6 px-6 lg:px-12 bg-gray-50">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-light mb-8 text-center">Questions fréquentes</h2>

                <div class="space-y-6">
                    <div v-for="faq in faqs" :key="faq.question" class="bg-white p-6 border border-gray-200">
                        <h3 class="text-lg font-medium mb-2">{{ faq.question }}</h3>
                        <p
                            v-for="(paragraph, idx) in faq.answers"
                            :key="idx"
                            class="text-gray-600 font-light"
                        >
                            {{ paragraph }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue'
import {SOCIAL_LINKS, CONTACT_INFO} from '@/config/constants'

const features = [
    'Montant de votre choix (à partir de 50€)',
    'Valable sur tous les types de shooting',
    'Carte personnalisée avec le nom du destinataire',
    'Laissez un message personnalisé pour offrir',
    'Envoi par mail sous 24h',
    'Validité 1 an',
    'Utilisable en une ou plusieurs fois',
]

const faqs = [
    {
        question: 'Comment fonctionne la carte cadeau ?',
        answers: [
            "Contactez-moi par mail ou sur Instagram pour personnaliser votre carte avec : le nom de la personne à qui vous voulez offrir, le message que vous voulez lui laisser et le montant de la carte cadeau." +
            " Je vous envoie ensuite la carte personnalisée par mail, prête à être offerte.",
        ],
    },
    {
        question: 'Quelle est la durée de validité de la carte cadeau ?',
        answers: [
            "La carte cadeau est valable 1 an à partir de la date d'envoi. Elle peut être utilisée en une ou plusieurs fois.",
        ],
    },
    {
        question: 'Sur quels shootings peut-elle être utilisée ?',
        answers: [
            "La carte cadeau est utilisable sur tous les types de shooting : portrait, couple, famille, animaux, sport, automobile... Le destinataire choisira librement quand il me contactera !",
        ],
    },
    {
        question: 'Comment le destinataire réserve sa séance ?',
        answers: [
            "Il lui suffit de me contacter par mail ou sur Instagram avec son code carte cadeau pour organiser ensemble la séance de son choix.",
        ],
    },
]
</script>
