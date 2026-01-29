<template>
    <div class="pt-20">
        <!-- Prestations Cards Section -->
        <section class="py-16 px-6 lg:px-12 bg-white relative overflow-hidden">
            <div class="max-w-7xl mx-auto relative">
                <PageHeader title="Prestations" subtitle="Des prestations sur mesure adaptées à vos besoins, vos envies et votre univers." />
            </div>

            <!-- Decorative Elements -->
            <div class="absolute top-32 right-0 w-32 h-32 bg-gold/10 blur-3xl rounded-full" />
            <div class="absolute bottom-20 left-0 w-20 h-20 bg-gold/5 blur-2xl rounded-full" />

            <div class="max-w-7xl mx-auto relative">
                <!-- Loading State -->
                <div v-if="isLoading" class="flex items-center justify-center py-24">
                    <div class="flex flex-col items-center gap-3">
                        <svg class="animate-spin h-10 w-10 text-gold" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-gray-500 font-light">Chargement des prestations...</span>
                    </div>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="text-center py-20">
                    <div class="max-w-md mx-auto">
                        <svg class="w-16 h-16 text-gold/60 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <p class="text-gray-600 font-light text-lg leading-relaxed">
                            Je suis en train de recréer mes offres pour cette nouvelle année, alors un peu de patience, dans quelques jours tout sera à jour et disponible !
                        </p>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="prestations.length === 0" class="text-center py-20">
                    <div class="max-w-md mx-auto">
                        <svg class="w-16 h-16 text-gold/60 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <p class="text-gray-600 font-light text-lg leading-relaxed">
                            Je suis en train de recréer mes offres pour cette nouvelle année, alors un peu de patience, dans quelques jours tout sera à jour et disponible !
                        </p>
                    </div>
                </div>

                <!-- Prestations Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-10 mb-20">
                    <PrestationCard
                        v-for="prestation in prestations"
                        :key="prestation.id"
                        :icon="prestation.icon || undefined"
                        :title="prestation.title"
                        :description="prestation.description"
                        :price="prestation.price_text || formatPrice(prestation.price)"
                        :price-unit="prestation.price_unit || undefined"
                        :features="prestation.features || []"
                        :background="prestation.background_image || undefined"
                        :background-opacity="prestation.background_opacity"
                        :disclaimer="prestation.disclaimer || undefined"
                    />
                </div>

                <!-- Why Choose Me Section -->
                <div class="bg-white border border-gold/20 p-12 rounded-lg shadow-sm">
                    <h3 class="text-3xl font-light text-center mb-8">Pourquoi me choisir ?</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-10 text-center">
                        <div>
                            <p class="text-gold text-4xl mb-3">&#127912;</p>
                            <h4 class="font-normal text-lg mb-2">Style naturel & elegant</h4>
                            <p class="text-gray-600 font-light">Des images authentiques, qui marquent l'instant sans artifices.</p>
                        </div>

                        <div>
                            <p class="text-gold text-4xl mb-3">&#129309;</p>
                            <h4 class="font-normal text-lg mb-2">Accompagnement complet</h4>
                            <p class="text-gray-600 font-light">Conseils poses, tenues et lieu pour un resultat harmonieux qui vous ressemble.</p>
                        </div>

                        <div>
                            <p class="text-gold text-4xl mb-3">&#128247;</p>
                            <h4 class="font-normal text-lg mb-2">Experience personnalisee</h4>
                            <p class="text-gray-600 font-light">Chaque seance est concue pour refleter votre personnalite, nous discutons, nous nous comprenons, nous choisissons ensemble afin d'avoir le resultat que vous souhaitez.</p>
                        </div>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="text-center mt-20 mb-10">
                    <h2 class="text-3xl font-light mb-4">Prêt à réserver ? Des questions ?</h2>
                    <p class="text-gray-600 font-light mb-8 text-lg">
                        Envoyez-moi une demande de shooting ou contactez-moi directement
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <button
                            @click="showBookingModal = true"
                            class="inline-block px-12 py-4 bg-gold text-white hover:opacity-90 transition-all duration-300 text-sm uppercase tracking-widest font-light"
                        >
                            M'envoyer une demande de shooting
                        </button>
                        <router-link
                            to="/contact"
                            class="inline-block px-12 py-4 border border-black text-black hover:border-gold hover:text-gold transition-all duration-300 text-sm uppercase tracking-widest font-light"
                        >
                            Me contacter
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Practical Information Section -->
        <section class="py-16 px-6 lg:px-12 bg-white border-t">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-light mb-8 text-center">Informations pratiques</h2>

                <div class="space-y-6 text-gray-600 font-light">
                    <div>
                        <h3 class="text-xl font-normal text-black mb-2">Reservation</h3>
                        <p>Les reservations se font par mail ou via Instagram, avec un acompte de 30% pour confirmer votre date.</p>
                        <p>En cas de reel imprevu, il est possible d'annuler ou de deplacer le shooting en fonction des disponibilites.</p>
                        <p>Merci de me prevenir dans un delai maximum de 48h avant la date initiale afin que nous puissions nous reorganiser.</p>
                    </div>

                    <div>
                        <h3 class="text-xl font-normal text-black mb-2">Livraison</h3>
                        <p>Vos photos sont livrées en moyenne sous 10 jours ouvres via une galerie privée. Vous serez contactes par mail lorsque la galerie vous sera accessible.</p>
                    </div>

                    <div>
                        <h3 class="text-xl font-normal text-black mb-2">Zone d'intervention</h3>
                        <p>Deplacement dans toute la region Auvergne-Rhone-Alpes. Des frais additionnels peuvent s'ajouter en fonction de la distance.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Booking Request Modal -->
        <BookingRequestModal
            :is-open="showBookingModal"
            @close="showBookingModal = false"
        />
    </div>
</template>

<script setup lang="ts">
import {ref, onMounted} from 'vue'
import PageHeader from '../components/PageHeader.vue'
import PrestationCard from '../components/PrestationCard.vue'
import BookingRequestModal from '../components/BookingRequestModal.vue'
import {api} from '@/services/api'
import type {Prestation} from '@/types'

const showBookingModal = ref(false)
const prestations = ref<Prestation[]>([])
const isLoading = ref(true)
const error = ref('')

function formatPrice(price: number): string {
    if (price === 0) return 'sur devis'
    return `${price}€`
}

async function loadPrestations() {
    isLoading.value = true
    error.value = ''
    try {
        const response = await api.getPrestations()
        // The API returns { prestations: [...] } for the public endpoint
        if (response && 'prestations' in response) {
            prestations.value = (response as { prestations: Prestation[] }).prestations
        } else if (response && response.data) {
            prestations.value = response.data
        }
    } catch (err) {
        error.value = 'Impossible de charger les prestations. Veuillez réessayer.'
        console.error('Failed to load prestations:', err)
    } finally {
        isLoading.value = false
    }
}

onMounted(loadPrestations)
</script>
