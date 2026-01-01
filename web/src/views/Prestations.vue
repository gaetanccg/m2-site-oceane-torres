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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 mb-20">

                    <PrestationCard
                        icon="portrait"
                        title="Portraits"
                        description="Chaque portrait est une rencontre, un moment suspendu où je cherche à capturer bien plus qu'un visage. Que ce soit lors d'un concert, d'un défilé, en couple, en famille, pour une grossesse ou un portrait professionnel, je m'attache à faire ressortir l'histoire et les émotions de chacun. Mon objectif est que tu te sentes à l'aise, toi-même, pour que chaque photo reflète le meilleur de qui tu es et devienne un souvenir sincère et vivant."
                        price="50€"
                        priceUnit="À partir de"
                        :features="['1h de shooting','5 photos retouchées','Conseils poses','Mise à disposition d\'une galerie privée']"
                        :background="portraitsBackground"
                        :backgroundOpacity="0.17"
                        disclaimer="⚠ L'offre est évolutive en fonction de vos envies et besoins (nombre de photo, durée...) - contactez moi directement pour en discuter !"
                    />

                    <PrestationCard
                        icon="sport"
                        title="Sport"
                        description="La photographie sportive, c’est l’art de figer l’instant où tout bascule : la puissance d’un coup, la vitesse d’une action, la grâce d’un mouvement. Que ce soit en équitation, sur un terrain de football, sur un ring de boxe, sur une scène de danse... chaque discipline raconte une histoire faite de force, de dépassement et d’émotions brutes. Mon objectif est de transformer ces moments en images intenses et mémorables, qui transmettent toute l’énergie et la beauté du sport."
                        price="Forfait journalier sur devis"
                        priceUnit=""
                        :features="['Couverture complète : préparation + séance / passage en compétition','Envoie de la totalité des photos prises','De la préparation jusuq\'à la fin du passage','Action & portrait','Options vidéo (à discuter)', 'Mise à disposition d\'une galerie privée']"
                        :background="sportBackground"
                        :backgroundOpacity="0.2"
                    />

                    <PrestationCard
                        icon="animal"
                        title="Animaux"
                        description="Photographier les animaux, c’est saisir leur spontanéité, leur regard et leur énergie unique. Chats, chiens, chevaux ou espèces plus sauvages, chaque rencontre raconte une histoire différente, faite de complicité, de liberté ou de puissance. Mon objectif est de capturer ces instants naturels, pour que chaque image transmette toute l’authenticité et le caractère de l’animal."
                        price="50€"
                        priceUnit="À partir de"
                        :features="['Portraits animalier','5 photos retouchées' ,'Shooting en concours','Possible à domicile ou en extérieur','Mise à disposition d\'une galerie privée']"
                        :background="animalBackground"
                        :backgroundOpacity="0.2"
                        disclaimer="⚠ L'offre est évolutive en fonction de vos envies et besoins (nombre de photo, durée...) - contactez moi directement pour en discuter !"
                    />

                    <PrestationCard
                        icon="moto"
                        title="Automobile"
                        description="La brillance d’une carrosserie, la précision d’une ligne, l’allure unique d’une moto ou d’une voiture… L’automobile et la moto sont bien plus que de simples bolides : elles incarnent une passion et une identité. Mon objectif est de capturer cette puissance et ce caractère, que ce soit en mouvement ou en statique, pour créer des images qui mettent en valeur à la fois la performance et l’élégance de chaque véhicule."
                        price="sur devis"
                        priceUnit="En fonction du nombre de photos, du lieu etc..."
                        :features="['Photos détails et plans larges', 'Envoie de la totalité des photos prises', 'Options vidéo (à discuter)', 'Possibilité de plans au drone', 'Mise à disposition d\'une galerie privée']"
                        :background="automobileBackground"
                        :backgroundOpacity="0.15"
                    />

                    <PrestationCard
                        icon="entreprise"
                        title="Entreprise"
                        description="Les photos d'entreprise, c'est une manière directe et authentique de montrer ce que votre marque ou votre établissement a réellement à offrir. Que vous soyez un restaurant, une boutique, une marque ou un service, je cherche à capturer votre univers, votre identité et l'atmosphère qui vous distingue. Mon objectif est simple : créer des visuels qui servent votre communication, qui renforcent votre image et qui transmettent clairement ce que vous faites. Des photos pensées pour vos sites, vos réseaux, vos campagnes ou vos supports print…"
                        price="sur devis"
                        priceUnit="En fonction du besoin "
                        :features="['Encadrement d\'évènement d\'entreprise, photos produit, etc...','Livraison rapide','Mise à disposition d\'une galerie privée']"
                        :background="entrepriseBackground"
                        :backgroundOpacity="0.15"
                    />

                    <PrestationCard
                        icon="video"
                        title="Vidéo"
                        description="La vidéo permet de raconter des histoires autrement : elle capture le mouvement, l'ambiance et les émotions d'un instant avec une intensité unique. Que ce soit des paysages, le rythme effréné d'une journée de concours équestre ou le suivi d'un athlète tout au long d'une compétition, chaque séquence est pensée pour immerger le spectateur et faire ressentir l'énergie et la beauté du moment. Mon objectif est de transformer ces instants en images vivantes, où chaque détail raconte sa propre histoire."
                        price="sur devis"
                        priceUnit="En fonction du besoin : "
                        :features="['Contenu pour les réseaux', 'Vidéo pour alimenter vos sites' ,'Tournage & montage vidéo','Discutons pour trouver LA vidéo qui vous fait envie']"
                        :background="videoBackground"
                        :backgroundOpacity="0.15"
                    />

                </div>

                <!-- Why Choose Me Section -->
                <div class="bg-white border border-gold/20 p-12 rounded-lg shadow-sm">
                    <h3 class="text-3xl font-light text-center mb-8">Pourquoi me choisir ?</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-10 text-center">
                        <div>
                            <p class="text-gold text-4xl mb-3">🎨</p>
                            <h4 class="font-normal text-lg mb-2">Style naturel & élégant</h4>
                            <p class="text-gray-600 font-light">Des images authentiques, qui marquent l'instant sans artifices.</p>
                        </div>

                        <div>
                            <p class="text-gold text-4xl mb-3">🤝</p>
                            <h4 class="font-normal text-lg mb-2">Accompagnement complet</h4>
                            <p class="text-gray-600 font-light">Conseils poses, tenues et lieu pour un résultat harmonieux qui vous ressemble.</p>
                        </div>

                        <div>
                            <p class="text-gold text-4xl mb-3">📸</p>
                            <h4 class="font-normal text-lg mb-2">Expérience personnalisée</h4>
                            <p class="text-gray-600 font-light">Chaque séance est conçue pour refléter votre personnalité, nous discutons, nous nous comprennons, nous choissisons ensemble afin d'avoir le résultat que vous souhaitez.</p>
                        </div>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="text-center mt-20 mb-10">
                    <h2 class="text-3xl font-light mb-4">Prête à réserver ? Des questions ?</h2>
                    <p class="text-gray-600 font-light mb-8 text-lg">
                        Contactez-moi sur Instagram pour réserver votre séance
                    </p>
                    <a
                        href="https://instagram.com/oceanetorresphotographie"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-block px-12 py-4 bg-black text-white hover:bg-gold transition-all duration-300 text-sm uppercase tracking-widest font-light"
                    >
                        Réserver via Instagram
                    </a>
                </div>
            </div>
        </section>

        <!-- Practical Information Section -->
        <section class="py-16 px-6 lg:px-12 bg-white border-t">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-light mb-8 text-center">Informations pratiques</h2>

                <div class="space-y-6 text-gray-600 font-light">

                    <div>
                        <h3 class="text-xl font-normal text-black mb-2">Réservation</h3>
                        <p>Les réservations se font par mail ou via Instagram, avec un acompte de 30% pour confirmer votre date.</p>
                        <p>En cas de réel imprévu, il est possible d'annuler ou de déplacer le shooting en fonction des disponibilités.</p>
                        <p>Merci de me prévenir dans un délais maximum de 48h avant la date initiale afin que nous puissions nous réorganiser.</p>
                    </div>

                    <div>
                        <h3 class="text-xl font-normal text-black mb-2">Livraison</h3>
                        <p>Vos photos sont livrées en moyenne sous 10 jours ouvrés via une galerie privée. Vous serez contactés par mail lorsque la galerie vous sera accessible.</p>
                    </div>

                    <div>
                        <h3 class="text-xl font-normal text-black mb-2">Zone d'intervention</h3>
                        <p>Déplacement dans toute la région Auvergne Rhône Alpes. Des frais additionels peuvent s'ajouter en fonction de la distance.</p>
                    </div>

                </div>
            </div>
        </section>

    </div>
</template>

<script setup lang="ts">
import PageHeader from '../components/PageHeader.vue'
import PrestationCard from '../components/PrestationCard.vue'

const portraitsBackground = '/optimized/Portraits/20.webp'
const sportBackground = '/optimized/Sport/11.webp'
const automobileBackground = '/optimized/Automobile/9.webp'
const animalBackground = '/optimized/Animalier/15.webp'
const entrepriseBackground = '/optimized/Entreprise/2.webp'
const videoBackground = '/optimized/persona_2.webp'
</script>
