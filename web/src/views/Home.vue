<template>
    <div>
        <!-- Hero Section -->
        <section class="relative min-h-[100svh] sm:min-h-[85vh] flex items-center justify-center overflow-hidden">
            <div
                class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                :style="{ backgroundImage: `url(${heroImage})` }"
            />

            <div class="relative z-10 text-center text-white px-6 max-w-5xl mx-auto hero-text">
                <h1 class="hero-title-main">OCÉANE TORRES</h1>
                <h2 class="hero-title-script">Océane Torres Photographie</h2>
                <p class="hero-subtitle">PHOTOGRAPHE ET VIDÉASTE</p>

                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6 justify-center mt-8 sm:mt-12 px-4">
                    <router-link
                        to="/portfolio"
                        class="px-6 sm:px-10 py-3 sm:py-4 bg-white text-black hover:bg-gold hover:text-white transition-all duration-300 text-xs sm:text-sm uppercase tracking-widest font-light text-center"
                    >
                        Voir le portfolio
                    </router-link>
                    <router-link
                        to="/contact"
                        class="px-6 sm:px-10 py-3 sm:py-4 border-2 border-white text-white hover:bg-white hover:text-black transition-all duration-300 text-xs sm:text-sm uppercase tracking-widest font-light text-center"
                    >
                        Me contacter
                    </router-link>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section class="py-8 px-6 lg:px-12 bg-white relative overflow-hidden">
            <div class="absolute bottom-20 right-10 w-48 h-48 bg-gold/5 rounded-full blur-3xl" />

            <div class="max-w-6xl mx-auto relative">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <img :src="personaImage" alt="Océane Torres - Photographe professionnelle Saint-Étienne Loire" class="w-full h-auto rounded-lg" loading="lazy" />
                    </div>
                    <div class="text-justify">
                        <h2 class="text-4xl md:text-5xl font-light mb-6 text-balance title-with-bar">
                            À propos de moi
                        </h2>
                        <p class="text-gray-600 font-light leading-relaxed mb-6 text-lg">
                            Je m'appelle Océane, j'ai 23 ans et je suis photographe et vidéaste professionnelle basée en Auvergne-Rhône-Alpes. J'interviens dans toute la région, notamment dans le Rhône et la Loire et plus précisément autour de Rive-de-Gier, Lorette, Saint-Chamond, Givors, Saint-Étienne et Lyon.
                        </p>
                        <p class="text-gray-600 font-light leading-relaxed mb-8 text-lg">
                            J'ai envie de créer des images qui racontent, qui touchent, qui marquent. Je propose un accompagnement qui reflète ce que j'aime réellement capturer : la spontanéité, l'émotion, l'énergie, et tout ce qui raconte une histoire.
                        </p>
                        <p class="text-gray-600 font-light leading-relaxed mb-6 text-lg">
                            Je réalise des photos animalières, des reportages sportifs, des portraits sous toutes leurs formes : lors de concerts, en séance individuelle, en couple, pendant une grossesse… mais aussi des contenus pour les entreprises (produits, restaurants, mise en avant d'un savoir-faire…). J'accompagne également celles et ceux qui souhaitent des photos professionnelles pour leur CV ou leurs projets personnels, sans oublier les shooting automobiles et la création de vidéos.
                        </p>
                        <p class="text-gray-600 font-light leading-relaxed mb-8 text-lg">
                            Mon objectif est simple : créer des images qui vous ressemblent, qui valorisent vos projets et qui laissent une trace.
                        </p>
                        <router-link
                            to="/a-propos"
                            class="inline-block px-8 py-3 border border-black hover:bg-black hover:text-white transition-all duration-300 text-sm uppercase tracking-widest font-light"
                        >
                            En savoir plus
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Gallery Preview Section -->
        <section class="py-16 px-6 lg:px-12 bg-white relative overflow-hidden">
            <div class="absolute bottom-10 left-10 w-24 h-24 bg-gold/5 rounded-full blur-2xl" />
            <div class="max-w-7xl mx-auto relative">
                <MasonryGallery :items="masonryItems" :show-all-tab="false" />
            </div>
        </section>

        <!-- Prestations Section -->
        <section class="py-24 px-6 lg:px-12 bg-gray-50 relative overflow-hidden">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl md:text-5xl font-light mb-4 title-with-bar">Mes prestations</h2>
                    <p class="text-gray-600 font-light text-lg">
                        Des séances photo sur mesure pour tous vos besoins
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <PrestationCardMini
                        v-for="prestation in featuredPrestations"
                        :key="prestation.title"
                        :icon="prestation.icon"
                        :title="prestation.title"
                        :description="prestation.description"
                        :features="prestation.features"
                    />
                </div>

                <div class="text-center mt-12">
                    <router-link
                        to="/prestations"
                        class="inline-block px-8 py-3 bg-black text-white hover:bg-gold transition-all duration-300 text-sm uppercase tracking-widest font-light"
                    >
                        Toutes les prestations
                    </router-link>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <CtaSection
            title="Prêt à capturer vos souvenirs ?"
            subtitle="Contactez-moi par mail ou sur Instagram pour discuter de votre projet"
        />
    </div>
</template>

<script setup lang="ts">
import type {GalleryItem, ServiceIconType} from '@/types'
import PrestationCardMini from '@/components/PrestationCardMini.vue'
import MasonryGallery from '@/components/MasonryGallery.vue'
import CtaSection from '@/components/CtaSection.vue'

const heroImage = '/images/hero.png'
const personaImage = '/optimized/persona_rev_2.webp'

const masonryItems: GalleryItem[] = [
    {url: '/optimized/Automobile/4.webp', alt: 'Shooting photo automobile par Océane Torres photographe Loire', type: 'image', category: 'Automobile'},
    {url: '/optimized/Entreprise/2.webp', alt: 'Photo entreprise professionnelle Saint-Étienne Lyon', type: 'image', category: 'Entreprise'},
    {url: '/optimized/Portraits/1.webp', alt: 'Portrait photo professionnelle Auvergne-Rhône-Alpes', type: 'image', category: 'Portraits'},
    {url: '/optimized/Animalier/1.webp', alt: 'Photographie animalière cheval Loire par Océane Torres', type: 'image', category: 'Animalier'},
    {url: '/optimized/Sport/5.webp', alt: 'Photo reportage sportif équitation Saint-Étienne', type: 'image', category: 'Sport'},
    {url: '/optimized/Portraits/10.webp', alt: 'Séance portrait couple photographe Rive-de-Gier', type: 'image', category: 'Portraits'},
    {url: '/optimized/Animalier/3.webp', alt: 'Photo animalière chien photographe Loire Rhône', type: 'image', category: 'Animalier'},
    {url: '/optimized/Sport/18.webp', alt: 'Reportage photo sport action Lyon Saint-Étienne', type: 'image', category: 'Sport'},
    {url: '/optimized/Automobile/8.webp', alt: 'Photo voiture shooting automobile Givors Saint-Chamond', type: 'image', category: 'Automobile'},
    {url: '/optimized/Entreprise/4.webp', alt: 'Photographe corporate entreprise Auvergne-Rhône-Alpes', type: 'image', category: 'Entreprise'},
    {url: '/optimized/Sport/7.webp', alt: 'Photographie sportive boxe danse photographe Loire', type: 'image', category: 'Sport'},
    {url: '/optimized/Automobile/10.webp', alt: 'Shooting moto voiture photographe Lorette Saint-Chamond', type: 'image', category: 'Automobile'},
    {url: '/optimized/Portraits/20.webp', alt: 'Portrait professionnel CV LinkedIn photographe Lyon', type: 'image', category: 'Portraits'},
    {url: '/optimized/Portraits/2.webp', alt: 'Séance photo portrait famille grossesse Saint-Étienne', type: 'image', category: 'Portraits'},
    {url: '/optimized/Animalier/2.webp', alt: 'Photo animaux de compagnie photographe Rhône Loire', type: 'image', category: 'Animalier'},
]

interface FeaturedPrestation {
    icon: ServiceIconType
    title: string
    description: string
    features: string[]
}

const featuredPrestations: FeaturedPrestation[] = [
    {
        icon: 'portrait',
        title: 'Portraits',
        description: "Capturer l'émotion et l'authenticité de chaque instant : concerts, couples, familles, grossesses ou portraits professionnels.",
        features: ['1h de shooting', '5 photos retouchées', 'Conseils poses', 'Galerie privée'],
    },
    {
        icon: 'sport',
        title: 'Sport',
        description: "Figer la puissance et l'intensité du mouvement : équitation, football, boxe, danse... Chaque discipline mérite ses images.",
        features: ['Couverture complète', 'Toutes les photos envoyées', 'Action & portrait', 'Option vidéo'],
    },
    {
        icon: 'video',
        title: 'Vidéo',
        description: 'Donnez vie à vos projets en images animées : contenu réseaux sociaux, montages dynamiques et création sur mesure.',
        features: ['Contenu réseaux sociaux', 'Montage vidéo', 'Création sur mesure'],
    },
]
</script>

<style>
@keyframes fade-in{
    from{
        opacity: 0;
        transform: translateY(20px);
    }
    to{
        opacity: 1;
        transform: translateY(0);
    }
}

:root{
    --gold: #D4AF37;
}

.title-with-bar{
    position: relative;
    display: inline-block;
    padding-bottom: 0.6rem;
}

.title-with-bar::after{
    content: "";
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    bottom: 0;
    width: 56px;
    height: 4px;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--gold), rgba(212, 175, 55, 0.9));
    box-shadow: 0 2px 8px rgba(212, 175, 55, 0.08);
    opacity: 0.98;
}

@media (max-width: 640px){
    .title-with-bar::after{
        width: 40px;
        height: 3px;
        bottom: -2px;
    }
}

.hero-text{
    position: relative;
    user-select: none;
}

.hero-title-main{
    font-family: "Glacial Indifference", sans-serif;
    font-size: clamp(3rem, 8vw, 6.5rem);
    font-weight: 200;
    opacity: 50% !important;
    white-space: nowrap;
    overflow: visible;
    display: block;
    position: relative;
    z-index: 1;
    animation: fade-in 1s ease-out forwards;
}

.hero-title-script{
    font-family: "Amsterdam Four", serif;
    font-size: clamp(4rem, 4vw, 4rem);
    font-style: italic;
    font-weight: 400;
    color: var(--gold);
    position: relative;
    z-index: 2;
    transform: translateY(-20%) rotate(-3deg);
    margin-top: -5rem;
    margin-bottom: 2rem;
    animation: fade-in 1.3s ease-out forwards;
}

.hero-subtitle{
    font-size: clamp(0.9rem, 2vw, 1.1rem);
    letter-spacing: 0.45em;
    color: rgba(255, 255, 255, 0.85);
    text-transform: uppercase;
    margin-top: 0.5rem;
    animation: fade-in 1.6s ease-out forwards;
}

@media (max-width: 640px){
    .hero-title-main{
        white-space: normal;
        font-size: clamp(1.8rem, 8vw, 3.6rem);
        letter-spacing: 0.12em;
        line-height: 1.05;
        margin-bottom: 0.6rem;
        padding-bottom: 0.1rem;
        z-index: 1;
    }

    .hero-title-script{
        font-size: clamp(1.1rem, 5vw, 2.2rem);
        transform: translateY(-15%) rotate(-3deg);
        margin-top: -2rem;
        z-index: 2;
    }

    .hero-subtitle{
        font-size: 0.85rem;
        letter-spacing: 0.4em;
    }
}

@media (min-width: 641px) and (max-width: 1024px){
    .hero-title-main{
        white-space: nowrap;
        font-size: clamp(2.6rem, 6.5vw, 5rem);
        letter-spacing: 0.18em;
    }

    .hero-title-script{
        font-size: clamp(1.8rem, 4.5vw, 3.6rem);
        transform: translateY(-12%) rotate(-3deg);
        margin-top: -3rem;
    }
}
</style>
