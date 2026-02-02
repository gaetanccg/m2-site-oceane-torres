<template>
    <div v-if="progress" class="space-y-4">
        <!-- Global Progress -->
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <svg v-if="!progress.isComplete" class="w-5 h-5 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="font-medium text-gray-900">
                        {{ progress.isComplete ? 'Upload termine' : 'Upload en cours...' }}
                    </span>
                </div>
                <span class="text-sm font-medium text-gray-600">
                    {{ progress.completed + progress.failed }} / {{ progress.total }}
                </span>
            </div>

            <!-- Progress Bar -->
            <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
                <div
                    class="h-full transition-all duration-300 rounded-full"
                    :class="progressBarClass"
                    :style="{ width: `${progress.percentage}%` }"
                />
            </div>

            <!-- Stats -->
            <div class="flex items-center gap-4 mt-3 text-xs">
                <span class="flex items-center gap-1 text-green-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ progress.completed }} termine(s)
                </span>
                <span v-if="progress.failed > 0" class="flex items-center gap-1 text-red-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    {{ progress.failed }} erreur(s)
                </span>
                <span v-if="progress.processing > 0" class="flex items-center gap-1 text-blue-600">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ progress.processing }} en cours
                </span>
            </div>
        </div>

        <!-- File List -->
        <div v-if="showFileList" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100">
                <div
                    v-for="file in progress.files"
                    :key="file.id"
                    class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50"
                >
                    <!-- Status Icon -->
                    <div class="flex-shrink-0">
                        <!-- Pending -->
                        <div v-if="file.status === 'pending'" class="w-5 h-5 rounded-full border-2 border-gray-300" />
                        <!-- Uploading -->
                        <svg v-else-if="file.status === 'uploading'" class="w-5 h-5 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <!-- Processing -->
                        <svg v-else-if="file.status === 'processing'" class="w-5 h-5 text-gold animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <!-- Completed -->
                        <svg v-else-if="file.status === 'completed'" class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <!-- Failed -->
                        <svg v-else-if="file.status === 'failed'" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>

                    <!-- File Info -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900 truncate">{{ file.originalFilename }}</p>
                        <p v-if="file.errorMessage" class="text-xs text-red-500 truncate">
                            {{ file.errorMessage }}
                        </p>
                        <p v-else-if="file.status === 'uploading'" class="text-xs text-blue-500">
                            Envoi en cours...
                        </p>
                        <p v-else-if="file.status === 'processing'" class="text-xs text-gold">
                            Traitement...
                        </p>
                    </div>

                    <!-- Progress -->
                    <div class="flex-shrink-0 w-16">
                        <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                            <div
                                class="h-full transition-all duration-300 rounded-full"
                                :class="getFileProgressClass(file)"
                                :style="{ width: `${file.progress}%` }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Button -->
        <div v-if="!progress.isComplete && showCancelButton" class="flex justify-end">
            <button
                @click="$emit('cancel')"
                class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
            >
                Annuler l'upload
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { UploadProgress, FileUploadState } from '@/types/upload'

interface Props {
    progress: UploadProgress | null
    showFileList?: boolean
    showCancelButton?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    showFileList: true,
    showCancelButton: true,
})

defineEmits<{
    cancel: []
}>()

const progressBarClass = computed(() => {
    if (!props.progress) return 'bg-gray-400'
    if (props.progress.failed > 0 && props.progress.isComplete) {
        return 'bg-gradient-to-r from-green-500 to-red-500'
    }
    if (props.progress.isComplete) {
        return 'bg-green-500'
    }
    return 'bg-gold'
})

function getFileProgressClass(file: FileUploadState): string {
    switch (file.status) {
        case 'completed':
            return 'bg-green-500'
        case 'failed':
            return 'bg-red-500'
        case 'processing':
            return 'bg-gold'
        case 'uploading':
            return 'bg-blue-500'
        default:
            return 'bg-gray-300'
    }
}
</script>
