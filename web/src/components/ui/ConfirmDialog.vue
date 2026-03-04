<template>
    <Teleport to="body">
        <Transition name="confirm">
            <div
                v-if="state.visible"
                class="fixed inset-0 z-[60] flex items-center justify-center p-4"
                @click.self="cancel"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" />

                <!-- Dialog -->
                <div
                    ref="dialogRef"
                    class="relative bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden"
                >
                    <div class="px-6 pt-6 pb-2 text-center">
                        <!-- Icon -->
                        <div
                            :class="[
                                'mx-auto w-12 h-12 rounded-full flex items-center justify-center mb-4',
                                state.variant === 'danger' ? 'bg-red-100' : 'bg-gold/10'
                            ]"
                        >
                            <!-- Danger: warning triangle -->
                            <svg
                                v-if="state.variant === 'danger'"
                                class="w-6 h-6 text-red-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <!-- Default: question mark -->
                            <svg
                                v-else
                                class="w-6 h-6 text-gold"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>

                        <!-- Title -->
                        <h3 v-if="state.title" class="text-lg font-semibold text-gray-900 mb-1">
                            {{ state.title }}
                        </h3>

                        <!-- Message -->
                        <p class="text-sm text-gray-600 whitespace-pre-line">
                            {{ state.message }}
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-3 px-6 py-4">
                        <button
                            @click="cancel"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            {{ state.cancelLabel }}
                        </button>
                        <button
                            ref="confirmBtnRef"
                            @click="doConfirm"
                            :class="[
                                'flex-1 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors',
                                state.variant === 'danger'
                                    ? 'bg-red-600 hover:bg-red-700'
                                    : 'bg-gold hover:bg-gold/90'
                            ]"
                        >
                            {{ state.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const { state, resolve } = useConfirm()
const confirmBtnRef = ref<HTMLButtonElement | null>(null)

function cancel() {
    resolve(false)
}

function doConfirm() {
    resolve(true)
}

function handleKeydown(e: KeyboardEvent) {
    if (!state.value.visible) return
    if (e.key === 'Escape') {
        cancel()
    }
}

// Auto-focus confirm button + body scroll lock
watch(
    () => state.value.visible,
    async (visible) => {
        document.body.style.overflow = visible ? 'hidden' : ''
        if (visible) {
            await nextTick()
            confirmBtnRef.value?.focus()
        }
    }
)

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown)
    document.body.style.overflow = ''
})
</script>

<style scoped>
.confirm-enter-active,
.confirm-leave-active {
    transition: opacity 0.2s ease;
}

.confirm-enter-active > div:last-child,
.confirm-leave-active > div:last-child {
    transition: transform 0.2s ease;
}

.confirm-enter-from,
.confirm-leave-to {
    opacity: 0;
}

.confirm-enter-from > div:last-child,
.confirm-leave-to > div:last-child {
    transform: scale(0.95);
}
</style>
