<template>
    <div class="pt-20">
        <section class="py-16 px-6 lg:px-12 bg-white relative overflow-hidden">
            <!-- Decorative Elements -->
            <div class="absolute top-0 left-10 w-64 h-64 bg-gold/5 rounded-full blur-3xl" />
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-gold/5 rounded-full blur-3xl" />
            <div class="absolute top-20 right-20 w-2 h-40 bg-gold/10" />

            <div class="max-w-7xl mx-auto relative">
                <PageHeader title="Portfolio" subtitle="Une sélection de mes créations" />
                <MasonryGallery
                    :items="galleryItems"
                    :filters="categories"
                    :category-descriptions="CATEGORY_DESCRIPTIONS"
                />
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import {CATEGORY_DESCRIPTIONS} from '@/config/constants'
import {getGalleryItems, getCategories} from '@/config/gallery'
import MasonryGallery from '@/components/MasonryGallery.vue'
import PageHeader from '@/components/PageHeader.vue'

// Récupère les items depuis la config statique (pas d'import JS d'images)
const galleryItems = getGalleryItems()
const categories = getCategories()
</script>
