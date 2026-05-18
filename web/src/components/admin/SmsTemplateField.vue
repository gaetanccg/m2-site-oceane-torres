<template>
    <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">
            Modèle de SMS personnalisé (optionnel)
        </label>
        <p class="text-xs text-gray-500">
            Laissez vide pour utiliser le message par défaut. Cliquez pour insérer un placeholder :
        </p>
        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="ph in PLACEHOLDERS"
                :key="ph.token"
                type="button"
                @click="insertPlaceholder(ph.token)"
                class="px-2 py-1 text-xs bg-gold/10 text-gold rounded hover:bg-gold/20 transition-colors"
            >
                <span class="font-mono font-medium">{{ ph.token }}</span>
                <span class="text-gray-500 ml-1">{{ ph.label }}</span>
            </button>
        </div>
        <textarea
            ref="textareaRef"
            :value="modelValue ?? ''"
            @input="handleInput"
            :rows="3"
            :placeholder="DEFAULT_TEMPLATE"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-gold focus:border-gold"
        ></textarea>
        <div class="flex items-center justify-between text-xs">
            <span :class="charCountClass">
                {{ previewLength }} caractères envoyés
                <span v-if="segmentInfo" class="ml-1">— {{ segmentInfo }}</span>
            </span>
            <span class="text-gray-400">{{ templateLength }} / 320 (modèle)</span>
        </div>
        <div v-if="modelValue && modelValue.trim()" class="mt-2 p-3 bg-gray-50 border border-gray-200 rounded-md">
            <p class="text-[10px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Aperçu (valeurs d'exemple)</p>
            <p class="text-sm text-gray-800 whitespace-pre-wrap font-mono leading-snug">{{ previewText }}</p>
        </div>
        <p v-if="validationError" class="text-xs text-red-500 mt-1">{{ validationError }}</p>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const DEFAULT_TEMPLATE = `Bonjour {nom}, voici l'accès à vos photos : {lien} (code: {code}). Oceane Torres`

const PLACEHOLDERS = [
    { token: '{nom}', label: 'Nom destinataire' },
    { token: '{lien}', label: 'Lien galerie' },
    { token: '{code}', label: 'Code partage' },
] as const

const SAMPLE: Record<string, string> = {
    '{nom}': 'Marie Dupont',
    '{lien}': 'https://oceanetorresphotographie.fr/gallery/A1B2C3',
    '{code}': 'A1B2C3',
}

const props = defineProps<{
    modelValue: string | null | undefined
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const textareaRef = ref<HTMLTextAreaElement | null>(null)

function stripAccents(text: string): string {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '')
}

const previewText = computed(() => {
    const tpl = props.modelValue ?? ''
    const substituted = tpl.replace(/\{nom\}|\{lien\}|\{code\}/g, m => SAMPLE[m] ?? m)
    return stripAccents(substituted)
})

const previewLength = computed(() => previewText.value.length)
const templateLength = computed(() => (props.modelValue ?? '').length)

const validationError = computed(() => {
    const value = props.modelValue ?? ''
    if (value.trim() === '') return ''
    if (!value.includes('{code}') && !value.includes('{lien}')) {
        return 'Le modèle doit contenir au moins {code} ou {lien} pour permettre l\'accès à la galerie.'
    }
    if (value.length > 320) return 'Le modèle dépasse 320 caractères.'
    return ''
})

const charCountClass = computed(() => {
    const len = previewLength.value
    if (len === 0) return 'text-gray-400'
    if (len <= 160) return 'text-green-600 font-medium'
    if (len <= 320) return 'text-orange-600 font-medium'
    return 'text-red-600 font-medium'
})

const segmentInfo = computed(() => {
    const len = previewLength.value
    if (len === 0) return ''
    if (len <= 160) return '1 SMS'
    if (len <= 320) return '2 SMS (cout double)'
    return 'trop long'
})

function handleInput(e: Event) {
    emit('update:modelValue', (e.target as HTMLTextAreaElement).value)
}

function insertPlaceholder(token: string) {
    const textarea = textareaRef.value
    const current = props.modelValue ?? ''
    if (!textarea) {
        emit('update:modelValue', current + token)
        return
    }
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const newValue = current.slice(0, start) + token + current.slice(end)
    emit('update:modelValue', newValue)
    setTimeout(() => {
        textarea.focus()
        textarea.setSelectionRange(start + token.length, start + token.length)
    }, 0)
}
</script>
