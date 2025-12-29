<template>
    <button
        @click="handleLike"
        class="like-button"
        :class="{ 'liked': isLiked, 'animating': isAnimating }"
        :disabled="isLoading"
        :aria-label="isLiked ? 'Retirer le like' : 'Ajouter un like'"
    >
        <svg
            class="heart-icon"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                :fill="isLiked ? 'currentColor' : 'none'"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
            />
        </svg>
        <span v-if="showCount && likesCount > 0" class="likes-count">{{ likesCount }}</span>
    </button>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
    photoId: string
    initialLikesCount?: number
    showCount?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    initialLikesCount: 0,
    showCount: true
})

const emit = defineEmits<{
    like: [photoId: string, newCount: number]
}>()

const isLiked = ref(false)
const isLoading = ref(false)
const isAnimating = ref(false)
const likesCount = ref(props.initialLikesCount)

watch(() => props.initialLikesCount, (newVal) => {
    likesCount.value = newVal
})

const handleLike = async () => {
    if (isLoading.value) return

    isLoading.value = true
    isAnimating.value = true

    try {
        const response = await fetch(`/api/photos/${props.photoId}/like`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })

        if (response.ok) {
            const data = await response.json()
            likesCount.value = data.likes_count
            isLiked.value = true
            emit('like', props.photoId, data.likes_count)
        }
    } catch (error) {
        console.error('Error liking photo:', error)
    } finally {
        isLoading.value = false
        setTimeout(() => {
            isAnimating.value = false
        }, 300)
    }
}
</script>

<style scoped>
.like-button {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem;
    background: rgba(0, 0, 0, 0.4);
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: white;
}

.like-button:hover {
    background: rgba(0, 0, 0, 0.6);
    transform: scale(1.05);
}

.like-button:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.like-button.liked {
    color: #ef4444;
}

.like-button.animating .heart-icon {
    animation: heartbeat 0.3s ease-in-out;
}

.heart-icon {
    width: 1.25rem;
    height: 1.25rem;
    transition: transform 0.2s ease;
}

.likes-count {
    font-size: 0.75rem;
    font-weight: 500;
    padding-right: 0.25rem;
}

@keyframes heartbeat {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.3);
    }
    100% {
        transform: scale(1);
    }
}
</style>
