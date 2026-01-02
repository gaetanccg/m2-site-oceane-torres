<template>
    <div class="pt-20 relative">
        <!-- Main Content Section -->
        <section class="py-16 px-6 lg:px-12 bg-white">
            <div class="max-w-6xl mx-auto relative z-10">
                <PageHeader title="À propos de moi" subtitle="Découvrez moi et parlons de vos projets ensemble !" />

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-start lg:items-center mb-24">
                    <div class="lg:col-span-5 lg:self-center">
                        <img
                            :src="contactImage"
                            alt="Océane - Photographe"
                            class="w-full h-auto border border-gray-200 rounded-lg"
                            loading="lazy"
                        />
                    </div>

                    <div class="lg:col-span-7">
                        <h2 class="text-4xl font-light mb-6">
                            Bonjour, je suis Océane
                        </h2>

                        <div class="space-y-6 text-gray-600 font-light leading-relaxed text-lg">
                            <p>
                                Photographe passionnée depuis plus de 4 ans, je suis maintenant professionnelle depuis 1 an. J'ai eu la chance de travailler avec plusieurs professionnels, comme une chorale (Les Choeurs Du Sud), l'équipe féminine de l'ASSE, j'ai fais quelques colaborations avec des cavaliers internationaux à EquitaLyon. J'ai aussi travaillé avec des artistes locaux comme des chanteurs et un restaurateur.
                            </p>
                            <p>
                                Bien sûr, en plus des professionnels et artistes, j'ai également travaillé avec de nombreux particuliers pour des séances portraits, couple, famille, sport, moto, animaux de compagnie et événements divers.
                            </p>
                            <p>
                                Mon approche se veut naturelle et authentique. Je crois que les plus belles photos sont celles qui capturent l'essence même d'un moment, sans artifice. Je cherche à mettre en valeur la beauté naturelle de chaque personne et l'émotion sincère de chaque instant.
                                La précision d'un mouvement, la maîtrise d'une action, la beauté d'un regard, d'un geste, d'un moment...
                            </p>
                            <p>
                                Que ce soit pour un portrait, une séance en couple, un sport ou tout autre événement important de votre vie, je m'engage à créer des images intemporelles que vous chérirez pour toujours.
                            </p>
                        </div>

                        <div class="mt-10">
                            <a
                                :href="SOCIAL_LINKS[0].url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-block px-8 py-3 bg-black text-white hover:bg-gold transition-all duration-300 text-sm uppercase tracking-widest font-light"
                            >
                                Me contacter
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Philosophy Section -->
                <div class="relative py-8">
                    <div class="absolute top-0 left-0 w-64 h-64 bg-gold/5 rounded-full blur-3xl" />
                    <div class="absolute bottom-0 right-0 w-96 h-96 bg-gold/5 rounded-full blur-3xl" />

                    <div class="relative">
                        <h2 class="text-3xl font-light mb-12 text-center">Ma philosophie</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                            <PhilosophyItem
                                v-for="item in philosophyItems"
                                :key="item.title"
                                :icon="item.icon"
                                :title="item.title"
                                :description="item.description"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <CtaSection
            title="Envie de travailler ensemble ?"
            subtitle="Je serais ravie d'échanger avec vous sur votre projet"
        />
    </div>
</template>

<script setup lang="ts">
import {h, type FunctionalComponent} from 'vue'
import {SOCIAL_LINKS} from '@/config/constants'
import PageHeader from '@/components/PageHeader.vue'
import CtaSection from '@/components/CtaSection.vue'

const contactImage = '/optimized/contact_1.webp'

interface PhilosophyItemProps {
    icon: string
    title: string
    description: string
}

const PhilosophyItem: FunctionalComponent<PhilosophyItemProps> = (props) => {
    const iconPaths: Record<string, string> = {
        heart: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
        eye: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
        star: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    }

    return h('div', {class: 'text-center group'}, [
        h('div', {class: 'w-20 h-20 mx-auto mb-6 flex items-center justify-center border-2 border-gold/30 group-hover:border-gold group-hover:bg-gold/5 transition-all duration-300 rounded-full'}, [
            h('svg', {class: 'w-9 h-9 text-gold', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24'}, [
                h('path', {'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '1.5', d: iconPaths[props.icon]})
            ])
        ]),
        h('h3', {class: 'text-xl font-light mb-3 text-gold'}, props.title),
        h('p', {class: 'text-gray-600 font-light leading-relaxed'}, props.description)
    ])
}

const philosophyItems = [
    {
        icon: 'heart',
        title: 'Authenticité',
        description: "Je capture vos moments tels qu'ils sont, sans artifice. L'authenticité crée les plus belles émotions et images."
    },
    {
        icon: 'eye',
        title: 'Créativité',
        description: "Chaque séance est unique. Je m'adapte à votre personnalité pour créer des images qui vous ressemblent."
    },
    {
        icon: 'star',
        title: 'Excellence',
        description: "De la prise de contact à la livraison, j'apporte un soin particulier à chaque étape pour un résultat à la hauteur de vos attentes."
    }
]
</script>
