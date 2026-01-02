<template>
    <div class="pt-20">
        <section class="py-16 px-6 lg:px-12 bg-white min-h-[70vh] flex items-center relative overflow-hidden">
            <div class="absolute top-10 left-0 w-64 h-64 bg-gold/5 rounded-full blur-3xl" />

            <div class="max-w-4xl mx-auto w-full relative">
                <PageHeader title="Contact" subtitle="Parlons de votre projet" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-16">
                    <!-- Contact Info -->
                    <div>
                        <h2 class="text-3xl font-light mb-8">Restons en contact</h2>

                        <div class="space-y-8">
                            <ContactItem icon="email" title="Email">
                                <a :href="`mailto:${CONTACT_INFO.email}`" class="hover:text-gold transition-colors">
                                    {{ CONTACT_INFO.email }}
                                </a>
                            </ContactItem>

                            <ContactItem icon="instagram" title="Instagram">
                                <a
                                    :href="SOCIAL_LINKS[0].url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="hover:text-gold transition-colors"
                                >
                                    @{{ CONTACT_INFO.instagram }}
                                </a>
                            </ContactItem>

                            <div>
                                <h3 class="text-xl font-normal mb-3">Réponse rapide</h3>
                                <p class="text-gray-600 font-light leading-relaxed">
                                    Je réponds généralement sous 24h. Pour une réponse encore plus rapide, n'hésitez pas à me contacter directement via Instagram.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Practical Info Box -->
                    <div class="bg-gray-50 p-10 lg:p-12">
                        <h3 class="text-2xl font-light mb-6">Informations pratiques</h3>

                        <div class="space-y-6 text-gray-600 font-light">
                            <div>
                                <h4 class="font-normal text-black mb-2">Horaires</h4>
                                <p>
                                    {{ BUSINESS_INFO.workingHours.days }}<br>
                                    {{ BUSINESS_INFO.workingHours.hours }}
                                </p>
                            </div>

                            <div>
                                <h4 class="font-normal text-black mb-2">Zone d'intervention</h4>
                                <p>
                                    Je me déplace dans toute la région Auverge Rhône Alpes. Des frais de déplacement peuvent s'appliquer au-delà de {{ BUSINESS_INFO.travelZone.free }}{{ BUSINESS_INFO.travelZone.unit }} de trajet.
                                </p>
                            </div>

                            <div>
                                <h4 class="font-normal text-black mb-2">Devis gratuit</h4>
                                <p>
                                    Tous mes devis sont gratuits et sans engagement. Contactez-moi pour discuter de votre projet.
                                </p>
                            </div>
                        </div>

                        <div class="mt-10">
                            <a
                                :href="SOCIAL_LINKS[0].url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block w-full px-8 py-4 bg-black text-white hover:bg-gold transition-all duration-300 text-sm uppercase tracking-widest font-light text-center"
                            >
                                Me contacter via Instagram
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Steps Before Contacting -->
                <div class="mt-16 pt-12 border-t border-gray-200">
                    <h3 class="text-2xl font-light mb-8 text-center">Avant de me contacter</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <StepItem
                            v-for="(step, index) in steps"
                            :key="step.title"
                            :number="index + 1"
                            :title="step.title"
                            :description="step.description"
                        />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import {h, type FunctionalComponent} from 'vue'
import {SOCIAL_LINKS, CONTACT_INFO, BUSINESS_INFO} from '@/config/constants'
import PageHeader from '@/components/PageHeader.vue'
import {IconInstagram} from '@/components/icons'

interface ContactItemProps {
    icon: 'email' | 'instagram'
    title: string
}

const ContactItem: FunctionalComponent<ContactItemProps> = (props, {slots}) => {
    const icons: Record<string, () => ReturnType<typeof h>> = {
        email: () => h('svg', {class: 'w-6 h-6 text-gold mr-3', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24'}, [
            h('path', {'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'})
        ]),
        instagram: () => h(IconInstagram, {class: 'w-6 h-6 text-gold mr-3'})
    }

    return h('div', null, [
        h('h3', {class: 'text-xl font-normal mb-3 flex items-center'}, [
            icons[props.icon](),
            props.title
        ]),
        h('p', {class: 'text-gray-600 font-light'}, slots.default?.())
    ])
}

interface StepItemProps {
    number: number
    title: string
    description: string
}

const StepItem: FunctionalComponent<StepItemProps> = (props) => {
    return h('div', {class: 'text-center'}, [
        h('div', {class: 'w-12 h-12 mx-auto mb-4 flex items-center justify-center border border-gold'}, [
            h('span', {class: 'text-gold font-light text-xl'}, String(props.number))
        ]),
        h('h4', {class: 'font-normal mb-2'}, props.title),
        h('p', {class: 'text-gray-600 font-light text-sm'}, props.description)
    ])
}

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
