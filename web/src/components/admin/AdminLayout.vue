<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Mobile header with hamburger -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-black h-14 flex items-center justify-between px-4">
      <span class="text-xl font-semibold text-gold">Admin</span>
      <button
        @click="toggleMobileSidebar"
        class="p-2 text-white hover:text-gold transition-colors"
        aria-label="Menu"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path v-if="!isMobileSidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Mobile overlay -->
    <Transition name="fade">
      <div
        v-if="isMobileSidebarOpen"
        class="lg:hidden fixed inset-0 bg-black/50 z-40"
        @click="isMobileSidebarOpen = false"
      />
    </Transition>

    <AdminSidebar
      :is-mobile-open="isMobileSidebarOpen"
      @close-mobile="isMobileSidebarOpen = false"
    />

    <!-- Main content with responsive padding -->
    <div class="pt-14 lg:pt-0 lg:pl-64 transition-all duration-300">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import AdminSidebar from './AdminSidebar.vue'

const isMobileSidebarOpen = ref(false)

function toggleMobileSidebar() {
  isMobileSidebarOpen.value = !isMobileSidebarOpen.value
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
