<template>
    <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg transition-shadow">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <h3 class="font-medium text-gray-900 truncate">
                    {{ reservation.prestation?.title || 'Demande de reservation' }}
                </h3>
                <p v-if="reservation.date" class="text-sm text-gray-500 mt-1">
                    {{ formatDate(reservation.date) }}
                </p>
                <p v-else-if="reservation.date_preferences" class="text-sm text-gray-500 mt-1">
                    Preferences : {{ reservation.date_preferences }}
                </p>
            </div>
            <!-- Status badge -->
            <span :class="['px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap', statusColor]">
                {{ statusLabel }}
            </span>
        </div>

        <!-- Message if any -->
        <p v-if="reservation.message" class="text-sm text-gray-600 mt-3 line-clamp-2">
            {{ reservation.message }}
        </p>

        <!-- Footer -->
        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
            <span class="text-xs text-gray-400">
                Demande du {{ formatDate(reservation.created_at) }}
            </span>
            <router-link
                v-if="reservation.status === 'confirmed'"
                to="/contact"
                class="text-sm text-gold hover:underline"
            >
                Contacter
            </router-link>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AccountReservation } from '@/types/account'
import { RESERVATION_STATUS_LABELS, RESERVATION_STATUS_COLORS } from '@/types/account'

const props = defineProps<{
    reservation: AccountReservation
}>()

const statusLabel = computed(() => RESERVATION_STATUS_LABELS[props.reservation.status] || props.reservation.status)
const statusColor = computed(() => RESERVATION_STATUS_COLORS[props.reservation.status] || 'bg-gray-100 text-gray-800')

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    })
}
</script>
