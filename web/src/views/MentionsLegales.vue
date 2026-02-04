<template>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 pt-24 sm:pt-32 pb-12 sm:pb-20">
        <h1 class="text-2xl sm:text-3xl font-light mb-6">Mentions légales</h1>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">1. Éditeur du site</h2>
            <p>
                Nom / Raison sociale : <strong>Océane Torres Photographie</strong><br />
                Adresse : <strong>Lorette</strong><br />
                E-mail : <strong>oceanetorresphotographie@gmail.com</strong><br />
                SIRET : <strong>993 948 587</strong><br />
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">2. Directeur de la publication</h2>
            <p>
                Directeur de la publication : <strong>Gaëtan C</strong><br />
                Pour toute question relative au site : <strong>gaetan4269@gmail.com</strong>
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">3. Hébergeur</h2>
            <p>
                Hébergeur : <strong>Render</strong><br />
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">4. Propriété intellectuelle</h2>
            <p>
                L'ensemble du contenu présent sur ce site (textes, photographies, vidéos, graphismes, logos, icônes, etc.) est protégé par le droit d'auteur et appartient à
                <strong>Océane Torres</strong> ou à des tiers ayant autorisé son utilisation. Toute reproduction, représentation, modification, publication,
                adaptation de tout ou partie des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite, sauf autorisation écrite préalable.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">5. Cookies et traceurs</h2>
            <p class="mb-3">
                Le site utilise differentes categories de cookies :
            </p>
            <ul class="list-disc list-inside space-y-1 text-gray-700 ml-4 mb-3">
                <li><strong>Cookies essentiels</strong> : indispensables au fonctionnement du site (session, panier, authentification)</li>
                <li><strong>Cookies analytiques</strong> : mesure d'audience via Google Analytics (soumis a consentement)</li>
            </ul>
            <p class="mb-3">
                Vous pouvez a tout moment gerer vos preferences de cookies en cliquant sur le bouton ci-dessous :
            </p>
            <button
                @click="openCookieSettings"
                class="px-4 py-2 bg-gold text-white rounded-lg hover:bg-gold/90 transition-colors text-sm"
            >
                Gerer mes cookies
            </button>
            <p class="mt-3">
                Pour plus d'informations, consultez notre
                <router-link to="/politique-confidentialite" class="text-gold hover:underline">
                    Politique de confidentialite
                </router-link>.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">6. Protection des donnees personnelles</h2>
            <p class="mb-2">
                Conformement au Reglement General sur la Protection des Donnees (RGPD), vous disposez de droits sur vos donnees personnelles
                (acces, rectification, effacement, portabilite, opposition).
            </p>
            <p>
                Pour exercer ces droits ou pour toute question relative a vos donnees, contactez :
                <a href="mailto:oceanetorresphotographie@gmail.com" class="text-gold hover:underline">
                    oceanetorresphotographie@gmail.com
                </a>
            </p>
            <p class="mt-2">
                Consultez notre
                <router-link to="/politique-confidentialite" class="text-gold hover:underline">
                    Politique de confidentialite complete
                </router-link>.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">7. Liens hypertextes</h2>
            <p>
                Le site peut contenir des liens vers d'autres sites. <strong>Oceane Torres Photographie</strong> n'est pas responsable du contenu de ces sites externes et
                décline toute responsabilité quant aux informations qui y sont publiées.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">8. Conditions d'utilisation</h2>
            <p>
                L'accès au site et son utilisation sont soumis aux présentes mentions légales et à la loi en vigueur. L'utilisateur reconnaît en avoir pris connaissance et
                les accepter.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">9. Photographies</h2>
            <p>
                Toutes les photographies présentées sur ce site sont la propriété de <strong>Oceane Torres Photographie</strong> ou sont utilisées avec l'accord des auteurs.
                Toute reproduction sans autorisation est interdite.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-medium mb-2">10. Modification des mentions</h2>
            <p>
                <strong>Oceane Torres Photographie</strong> se réserve le droit de modifier ces mentions légales à tout moment. Les modifications prendront effet dès leur
                publication sur le site.
            </p>
        </section>

        <section>
            <h2 class="text-xl font-medium mb-2">Contact</h2>
            <p>
                Pour toute question relative aux présentes mentions légales ou à la protection des données, contactez :
                <strong>oceanetorresphotographie@gmail.com</strong>
            </p>
        </section>
    </section>
</template>

<script setup lang="ts">
import { useConsentStore } from '@/stores/consent'

const consentStore = useConsentStore()

function openCookieSettings() {
    consentStore.openSettings()
}
</script>
