<template>
    <div class="share-code-input">
        <div class="code-inputs">
            <input
                v-for="(_, index) in 6"
                :key="index"
                :ref="el => inputRefs[index] = el as HTMLInputElement"
                type="text"
                maxlength="1"
                :value="codeDigits[index]"
                @input="handleInput(index, $event)"
                @keydown="handleKeydown(index, $event)"
                @paste="handlePaste"
                @focus="handleFocus(index)"
                class="code-input"
                :class="{ 'has-value': codeDigits[index], 'error': hasError }"
                autocomplete="off"
                autocapitalize="characters"
                inputmode="text"
            />
        </div>
        <p v-if="hasError" class="error-message">{{ errorMessage }}</p>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'

interface Props {
    modelValue?: string
    hasError?: boolean
    errorMessage?: string
    autofocus?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    hasError: false,
    errorMessage: 'Code invalide',
    autofocus: true
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
    'complete': [code: string]
}>()

const inputRefs = ref<(HTMLInputElement | null)[]>([])
const codeDigits = ref<string[]>(['', '', '', '', '', ''])

const fullCode = computed(() => codeDigits.value.join('').toUpperCase())

watch(fullCode, (newCode) => {
    emit('update:modelValue', newCode)
    if (newCode.length === 6) {
        emit('complete', newCode)
    }
})

watch(() => props.modelValue, (newValue) => {
    if (newValue) {
        const chars = newValue.toUpperCase().split('').slice(0, 6)
        codeDigits.value = [...chars, ...Array(6 - chars.length).fill('')]
    }
}, { immediate: true })

const handleInput = (index: number, event: Event) => {
    const input = event.target as HTMLInputElement
    const value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '')

    if (value) {
        codeDigits.value[index] = value[0]
        if (index < 5) {
            inputRefs.value[index + 1]?.focus()
        }
    }
}

const handleKeydown = (index: number, event: KeyboardEvent) => {
    if (event.key === 'Backspace') {
        if (!codeDigits.value[index] && index > 0) {
            codeDigits.value[index - 1] = ''
            inputRefs.value[index - 1]?.focus()
        } else {
            codeDigits.value[index] = ''
        }
        event.preventDefault()
    } else if (event.key === 'ArrowLeft' && index > 0) {
        inputRefs.value[index - 1]?.focus()
    } else if (event.key === 'ArrowRight' && index < 5) {
        inputRefs.value[index + 1]?.focus()
    }
}

const handlePaste = (event: ClipboardEvent) => {
    event.preventDefault()
    const pastedText = event.clipboardData?.getData('text')?.toUpperCase().replace(/[^A-Z0-9]/g, '') || ''

    for (let i = 0; i < 6 && i < pastedText.length; i++) {
        codeDigits.value[i] = pastedText[i]
    }

    const focusIndex = Math.min(pastedText.length, 5)
    inputRefs.value[focusIndex]?.focus()
}

const handleFocus = (index: number) => {
    inputRefs.value[index]?.select()
}

onMounted(() => {
    if (props.autofocus) {
        inputRefs.value[0]?.focus()
    }
})

defineExpose({
    focus: () => inputRefs.value[0]?.focus(),
    clear: () => {
        codeDigits.value = ['', '', '', '', '', '']
        inputRefs.value[0]?.focus()
    }
})
</script>

<style scoped>
.share-code-input {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.code-inputs {
    display: flex;
    gap: 0.75rem;
}

.code-input {
    width: 3.5rem;
    height: 4rem;
    text-align: center;
    font-size: 1.5rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 2px solid #e5e7eb;
    border-radius: 0.5rem;
    background: #fff;
    transition: all 0.2s ease;
    outline: none;
}

.code-input:focus {
    border-color: #D4AF37;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
}

.code-input.has-value {
    border-color: #D4AF37;
    background: rgba(212, 175, 55, 0.05);
}

.code-input.error {
    border-color: #ef4444;
    background: rgba(239, 68, 68, 0.05);
}

.error-message {
    color: #ef4444;
    font-size: 0.875rem;
    margin: 0;
}

@media (max-width: 480px) {
    .code-inputs {
        gap: 0.5rem;
    }

    .code-input {
        width: 2.75rem;
        height: 3.25rem;
        font-size: 1.25rem;
    }
}
</style>
