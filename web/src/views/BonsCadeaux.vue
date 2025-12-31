<template>
    <div class="pt-20">
        <!-- Bons Plans Section -->
        <section class="py-16 px-6 lg:px-12 bg-white relative overflow-hidden">
            <div class="max-w-7xl mx-auto relative">
                <PageHeader
                    title="Bons Cadeaux"
                    subtitle="Pour offrir un shooting à des proches, optez pour une carte cadeau personnalisée !"
                />

                <!-- Gift Cards Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-16">
                    <GiftCard
                        v-for="card in giftCards"
                        :key="card.title"
                        :title="card.title"
                        :subtitle="card.subtitle"
                        :price="card.price"
                        :features="card.features"
                        :is-popular="card.isPopular"
                        :variant="card.variant"
                    />
                </div>

                <!-- CTA Section -->
                <div class="bg-black text-white p-12 text-center">
                    <h2 class="text-3xl font-light mb-4">Prête à réserver votre séance ?</h2>
                    <p class="text-gray-300 font-light mb-8 text-lg">
                        Pour réserver, contactez-moi sur Instagram ou par mail
                    </p>

                    <div class="flex items-center justify-center gap-4">
                        <a
                            :href="SOCIAL_LINKS[0].url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-block px-10 py-4 border-2 border-gold text-gold hover:bg-gold hover:text-black transition-all duration-300 text-sm uppercase tracking-widest font-light"
                        >
                            Réserver via Instagram
                        </a>

                        <a
                            :href="`mailto:${CONTACT_INFO.email}`"
                            class="inline-block px-6 py-3 border-2 border-gold text-gold bg-white hover:bg-gold hover:text-black transition-all duration-300 text-sm uppercase tracking-widest font-light"
                        >
                            Envoyer un mail
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-16 px-6 lg:px-12 bg-white border-t">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-light mb-8 text-center">Questions fréquentes</h2>

                <div class="space-y-6">
                    <div v-for="faq in faqs" :key="faq.question">
                        <h3 class="text-xl font-normal mb-2">{{ faq.question }}</h3>
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
import GiftCard from '@/components/GiftCard.vue'
import {SOCIAL_LINKS, CONTACT_INFO} from '@/config/constants'

const giftCards = [
    {
        title: 'Essentiel',
        subtitle: 'Portrait individuel',
        price: '100€',
        features: [
            '1h de shooting',
            '15 photos retouchées',
            'Galerie de téléchargement en ligne',
            'Lieu au choix',
        ],
        isPopular: false,
        variant: 'light' as const,
    },
    {
        title: 'Sur mesure',
        subtitle: 'Discutons de ce que vous souhaitez offrir !',
        price: 'Sur devis',
        features: [
            'Durée personnalisée',
            'Nombre de photos retouchées personnalisé',
            'Tirage photo possible',
            'Galerie de téléchargement en ligne',
            'Option vidéo & Option plan au drone',
        ],
        isPopular: true,
        variant: 'dark' as const,
    },
    {
        title: 'Premium',
        subtitle: 'Couple / Famille / Animaux',
        price: '150€',
        features: [
            '1h30 de shooting',
            '20 photos retouchées',
            'Galerie de téléchargement en ligne',
            'Lieu au choix',
            'Conseils personnalisés',
        ],
        isPopular: false,
        variant: 'light' as const,
    },
]

const faqs = [
    {
        question: 'Comment se déroule la réservation ?',
        answers: [
            "Prenez contact avec moi pour définir le besoin de la carte cadeau que vous souhaitez faire. Une fois décidé, je vous envoie par mail une carte cadeau personnalisée correspondant à ce que vous souhaitez offrir !",
            "Pour utiliser la carte cadeau, vous ou la personne à qui vous l'offrez avez juste à me contacter sur Instagram ou par Mail afin d'organiser le shooting.",
        ],
    },
    {
        question: 'Quand reçois-je mes photos ?',
        answers: [
            "En fonction du nombre de photos, je m'engage à livrer les photos dans un délai moyen de 1 à 2 semaines. Si vous avez une urgence ou un besoin particulier, n'hésitez pas à me le faire savoir lors de la réservation !",
        ],
    },
    {
        question: 'Puis-je acheter des photos supplémentaires ?',
        answers: [
            "Oui, toutes les photos de votre séance sont disponibles à l'achat au tarif de 8€/photo retouchée supplémentaire.",
        ],
    },
    {
        question: 'Que se passe-t-il en cas de mauvais temps ?',
        answers: [
            'Pour les séances extérieures, nous pouvons reprogrammer sans frais si la météo ne permet pas la séance.',
        ],
    },
]
</script>
