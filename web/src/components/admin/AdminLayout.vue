<template>
  <div class="min-h-screen bg-gray-50">
    <AdminSidebar />
    <div class="pl-64 transition-all duration-300">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import AdminSidebar from './AdminSidebar.vue'
</script>
