<template>
  <div class="space-y-1.5">
    <label v-if="label" :for="id" class="block text-sm font-medium text-gray-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Text / Email / Password / Number -->
    <input
      v-if="type === 'text' || type === 'email' || type === 'password' || type === 'number' || type === 'date' || type === 'time' || type === 'datetime-local'"
      :id="id"
      :type="type"
      :value="String(modelValue ?? '')"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :min="min"
      :max="max"
      :step="step"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      :class="inputClasses"
    />

    <!-- Textarea -->
    <textarea
      v-else-if="type === 'textarea'"
      :id="id"
      :value="String(modelValue ?? '')"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :rows="rows"
      @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
      :class="inputClasses"
    />

    <!-- Select -->
    <select
      v-else-if="type === 'select'"
      :id="id"
      :value="modelValue"
      :disabled="disabled"
      :required="required"
      @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
      :class="inputClasses"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option
        v-for="option in options"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>

    <!-- Checkbox -->
    <div v-else-if="type === 'checkbox'" class="flex items-center gap-2">
      <input
        :id="id"
        type="checkbox"
        :checked="Boolean(modelValue)"
        :disabled="disabled"
        @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
        class="w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
      />
      <span v-if="checkboxLabel" class="text-sm text-gray-600">{{ checkboxLabel }}</span>
    </div>

    <!-- Helper text -->
    <p v-if="helper" class="text-sm text-gray-500">{{ helper }}</p>

    <!-- Error message -->
    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { SelectOption } from '@/types/admin'

const props = withDefaults(
  defineProps<{
    modelValue: string | number | boolean | null
    type?: 'text' | 'email' | 'password' | 'number' | 'date' | 'time' | 'datetime-local' | 'textarea' | 'select' | 'checkbox'
    id?: string
    label?: string
    placeholder?: string
    helper?: string
    error?: string
    required?: boolean
    disabled?: boolean
    options?: SelectOption[]
    rows?: number
    min?: number | string
    max?: number | string
    step?: number | string
    checkboxLabel?: string
  }>(),
  {
    type: 'text',
    rows: 4,
  }
)

defineEmits<{
  'update:modelValue': [value: string | number | boolean]
}>()

const inputClasses = computed(() => [
  'w-full px-4 py-2.5 border rounded-lg transition-colors',
  'focus:ring-2 focus:ring-gold focus:border-gold',
  props.error
    ? 'border-red-500 focus:ring-red-500 focus:border-red-500'
    : 'border-gray-300',
  props.disabled && 'bg-gray-100 cursor-not-allowed opacity-60',
])
</script>
