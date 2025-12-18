<template>
  <svg :class="classes" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
  </svg>
</template>

<script setup lang="ts">
interface Props {
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  class: 'w-6 h-6'
})

const classes = props.class
</script>
