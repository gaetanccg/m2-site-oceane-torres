<template>
    <section class="max-w-4xl mx-auto px-4 sm:px-6 pt-24 sm:pt-32 pb-12 sm:pb-20 text-center">
        <h1 class="text-5xl sm:text-6xl font-light text-gold mb-4">404</h1>
        <p class="text-xl sm:text-2xl font-light mb-8">Page non trouvée</p>
        <p class="text-gray-600 mb-10">
            La page que vous recherchez n'existe pas ou a été déplacée.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <RouterLink
                to="/"
                class="inline-block px-8 py-3 bg-gold text-white text-sm tracking-wider uppercase hover:bg-gold/90 transition-colors"
            >
                Retour à l'accueil
            </RouterLink>
            <RouterLink
                to="/contact"
                class="inline-block px-8 py-3 border border-gold text-gold text-sm tracking-wider uppercase hover:bg-gold hover:text-white transition-colors"
            >
                Nous contacter
            </RouterLink>
        </div>
    </section>
</template>

<script setup lang="ts">
// 404 page — meta handled by router (noindex, nofollow)
</script>
