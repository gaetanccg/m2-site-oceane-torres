<template>
  <div class="bg-white rounded-xl border border-gray-200 p-6">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500">{{ label }}</p>
        <p class="mt-2 text-3xl font-bold text-gray-900">{{ formattedValue }}</p>
        <div v-if="change !== undefined" class="mt-2 flex items-center gap-1">
          <svg
            :class="[
              'w-4 h-4',
              change >= 0 ? 'text-green-500' : 'text-red-500'
            ]"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              v-if="change >= 0"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 10l7-7m0 0l7 7m-7-7v18"
            />
            <path
              v-else
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 14l-7 7m0 0l-7-7m7 7V3"
            />
          </svg>
          <span
            :class="[
              'text-sm font-medium',
              change >= 0 ? 'text-green-500' : 'text-red-500'
            ]"
          >
            {{ Math.abs(change) }}%
          </span>
          <span class="text-sm text-gray-500">vs mois dernier</span>
        </div>
      </div>
      <div
        :class="[
          'p-3 rounded-xl',
          iconBgClass
        ]"
      >
        <slot name="icon">
          <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
          </svg>
        </slot>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    value: number | string
    change?: number
    format?: 'number' | 'currency' | 'percent'
    iconBgClass?: string
  }>(),
  {
    format: 'number',
    iconBgClass: 'bg-gold/10',
  }
)

const formattedValue = computed(() => {
  if (typeof props.value === 'string') return props.value

  switch (props.format) {
    case 'currency':
      return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
      }).format(props.value)
    case 'percent':
      return `${props.value}%`
    default:
      return new Intl.NumberFormat('fr-FR').format(props.value)
  }
})
</script>
