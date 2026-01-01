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
    </button>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { API_CONFIG } from '@/config/constants'

interface Props {
    photoId: string
    liked?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    liked: false
})

const emit = defineEmits<{
    like: [photoId: string, isLiked: boolean]
}>()

const isLiked = ref(props.liked)
const isLoading = ref(false)
const isAnimating = ref(false)

// Watch for prop changes
watch(() => props.liked, (newValue) => {
    isLiked.value = newValue
})

const handleLike = async () => {
    if (isLoading.value) return

    isLoading.value = true
    isAnimating.value = true

    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/photos/${props.photoId}/like`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })

        if (response.ok) {
            const data = await response.json()
            isLiked.value = data.is_liked
            emit('like', props.photoId, data.is_liked)
        }
    } catch (error) {
        console.error('Error toggling like:', error)
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
    justify-content: center;
    padding: 0.625rem;
    background: rgba(0, 0, 0, 0.4);
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: white;
}

.like-button:hover {
    background: rgba(0, 0, 0, 0.6);
    transform: scale(1.1);
}

.like-button:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}

.like-button.liked {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.2);
}

.like-button.animating .heart-icon {
    animation: heartbeat 0.3s ease-in-out;
}

.heart-icon {
    width: 1.5rem;
    height: 1.5rem;
    transition: transform 0.2s ease;
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
