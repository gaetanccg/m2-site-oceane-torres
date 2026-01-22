<template>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
        <!-- Cover image -->
        <div class="aspect-[4/3] bg-gray-100 relative overflow-hidden">
            <img
                v-if="coverPhoto"
                :src="coverPhoto"
                :alt="gallery.title"
                class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>

            <!-- Photo count badge -->
            <div class="absolute bottom-2 right-2 bg-black/60 text-white text-xs px-2 py-1 rounded-full flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {{ gallery.photos_count }}
            </div>
        </div>

        <!-- Content -->
        <div class="p-4">
            <h3 class="font-medium text-gray-900 truncate">{{ gallery.title }}</h3>
            <p v-if="gallery.description" class="text-sm text-gray-500 mt-1 line-clamp-2">
                {{ gallery.description }}
            </p>
            <p class="text-xs text-gray-400 mt-2">
                {{ formatDate(gallery.created_at) }}
            </p>

            <!-- Actions -->
            <div class="mt-4 flex gap-2">
                <router-link
                    :to="`/gallery/${gallery.share_code}`"
                    class="flex-1 py-2 px-3 bg-gold text-white text-sm font-medium rounded-lg text-center hover:bg-gold/90 transition-colors"
                >
                    Voir la galerie
                </router-link>
                <router-link
                    :to="`/gallery/download/${gallery.access_token}`"
                    class="py-2 px-3 border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors"
                    title="Telecharger"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AccountGallery } from '@/types/account'

const props = defineProps<{
    gallery: AccountGallery
}>()

const coverPhoto = computed(() => {
    if (props.gallery.photos && props.gallery.photos.length > 0) {
        const photo = props.gallery.photos[0]
        return photo.display_url || photo.file_path || photo.thumbnail_path
    }
    return null
})

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    })
}
</script>
