<template>
  <AdminLayout>
    <AdminHeader title="Réservations" subtitle="Gérez vos rendez-vous clients">
      <template #actions>
        <div class="flex items-center gap-3">
          <button
            @click="viewMode = viewMode === 'list' ? 'calendar' : 'list'"
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            <svg v-if="viewMode === 'list'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
            {{ viewMode === 'list' ? 'Calendrier' : 'Liste' }}
          </button>
        </div>
      </template>
    </AdminHeader>

    <div class="p-6">
      <!-- Calendar View -->
      <Calendar
        v-if="viewMode === 'calendar'"
        :events="calendarEvents"
        @event-click="openReservationDetail"
        @day-click="handleDayClick"
      />

      <!-- List View -->
      <DataTable
        v-else
        :columns="columns"
        :data="reservations"
        title=""
        searchable
        v-model:search-query="searchQuery"
        v-model:current-page="currentPage"
        :total-pages="totalPages"
        :total="total"
        :from="from"
        :to="to"
      >
        <template #filters>
          <select
            v-model="statusFilter"
            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
          >
            <option value="">Tous les statuts</option>
            <option value="pending">En attente</option>
            <option value="confirmed">Confirmé</option>
            <option value="cancelled">Annulé</option>
            <option value="completed">Terminé</option>
          </select>
        </template>

        <template #cell-client.name="{ row }">
          <div>
            <p class="font-medium text-gray-900">{{ row.client.first_name }} {{ row.client.last_name }}</p>
            <p class="text-sm text-gray-500">{{ row.client.email }}</p>
          </div>
        </template>

        <template #cell-prestation.title="{ row }">
          <p class="font-medium text-gray-900">{{ row.prestation.title }}</p>
        </template>

        <template #cell-date="{ row }">
          <div>
            <p class="font-medium text-gray-900">{{ formatDate(row.date) }}</p>
            <p class="text-sm text-gray-500">{{ row.time }}</p>
          </div>
        </template>

        <template #cell-status="{ row }">
          <StatusBadge :status="row.status" />
        </template>

        <template #actions="{ row }">
          <div class="flex items-center gap-2">
            <button
              @click="openReservationDetail(row)"
              class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
              title="Voir détails"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
            <div class="relative group">
              <button
                class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                title="Changer statut"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                </svg>
              </button>
              <div class="absolute right-0 mt-1 w-40 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-10">
                <button
                  v-for="status in statusOptions"
                  :key="status.value"
                  @click="updateStatus(row.id, status.value)"
                  :class="[
                    'w-full px-4 py-2 text-left text-sm hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg',
                    row.status === status.value ? 'bg-gray-50 text-gray-400' : 'text-gray-700'
                  ]"
                  :disabled="row.status === status.value"
                >
                  {{ status.label }}
                </button>
              </div>
            </div>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Reservation Detail Modal -->
    <Modal v-model="showDetailModal" :title="selectedReservation?.client ? `${selectedReservation.client.first_name} ${selectedReservation.client.last_name}` : 'Détails'" size="lg">
      <div v-if="selectedReservation" class="space-y-6">
        <!-- Client Info -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Client</h3>
          <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <p><span class="font-medium">Nom:</span> {{ selectedReservation.client.first_name }} {{ selectedReservation.client.last_name }}</p>
            <p><span class="font-medium">Mail:</span> {{ selectedReservation.client.email }}</p>
            <p v-if="selectedReservation.client.phone">
              <span class="font-medium">Téléphone:</span> {{ selectedReservation.client.phone }}
            </p>
          </div>
        </div>

        <!-- Prestation Info -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Prestation</h3>
          <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <p><span class="font-medium">Type:</span> {{ selectedReservation.prestation.title }}</p>
            <p><span class="font-medium">Date:</span> {{ formatDate(selectedReservation.date) }} à {{ selectedReservation.time }}</p>
            <p><span class="font-medium">Statut:</span> <StatusBadge :status="selectedReservation.status" /></p>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="selectedReservation.message">
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Message du client</h3>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-700 whitespace-pre-wrap">{{ selectedReservation.message }}</p>
          </div>
        </div>

        <!-- Status Update -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Changer le statut</h3>
          <div class="flex gap-2">
            <Button
              v-for="status in statusOptions"
              :key="status.value"
              :variant="selectedReservation.status === status.value ? 'primary' : 'secondary'"
              size="sm"
              :disabled="selectedReservation.status === status.value"
              @click="updateStatus(selectedReservation.id, status.value); showDetailModal = false"
            >
              {{ status.label }}
            </Button>
          </div>
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" @click="showDetailModal = false">Fermer</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import AdminLayout from '@/components/admin/AdminLayout.vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import DataTable from '@/components/admin/ui/DataTable.vue'
import Calendar from '@/components/admin/ui/Calendar.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import { adminApi } from '@/services/adminApi'
import type { Reservation, CalendarEvent, ReservationStatus, TableColumn } from '@/types/admin'

const viewMode = ref<'list' | 'calendar'>('list')
const reservations = ref<Reservation[]>([])
const calendarEvents = ref<CalendarEvent[]>([])
const searchQuery = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const showDetailModal = ref(false)
const selectedReservation = ref<Reservation | null>(null)

const columns: TableColumn<Reservation>[] = [
  { key: 'client.name', label: 'Client', sortable: true },
  { key: 'prestation.title', label: 'Prestation' },
  { key: 'date', label: 'Date', sortable: true },
  { key: 'status', label: 'Statut' },
]

const statusOptions = [
  { value: 'pending' as ReservationStatus, label: 'En attente' },
  { value: 'confirmed' as ReservationStatus, label: 'Confirmer' },
  { value: 'cancelled' as ReservationStatus, label: 'Annuler' },
  { value: 'completed' as ReservationStatus, label: 'Terminer' },
]

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

async function fetchReservations() {
  try {
    const response = await adminApi.getReservations(
      currentPage.value,
      20,
      statusFilter.value as ReservationStatus || undefined
    )
    reservations.value = response.data
    totalPages.value = response.meta.last_page
    total.value = response.meta.total
    from.value = response.meta.from
    to.value = response.meta.to
  } catch {
    // Silently fail
  }
}

async function fetchCalendarEvents() {
  try {
    const now = new Date()
    const start = new Date(now.getFullYear(), now.getMonth() - 1, 1).toISOString().split('T')[0]
    const end = new Date(now.getFullYear(), now.getMonth() + 2, 0).toISOString().split('T')[0]

    const response = await adminApi.getCalendarEvents(start, end)
    if (response.success) {
      calendarEvents.value = response.data
    }
  } catch {
    // Silently fail
  }
}

function openReservationDetail(item: Reservation | CalendarEvent) {
  // Check if it's a Reservation (has client property) or CalendarEvent
  const reservation = item as Reservation
  if (reservation.client) {
    // It's a full Reservation
    selectedReservation.value = reservation
  } else {
    // It's a calendar event, need to find the full reservation
    const calendarItem = item as CalendarEvent
    const found = reservations.value.find(r => r.id === calendarItem.id)
    if (found) {
      selectedReservation.value = found
    }
  }
  showDetailModal.value = true
}

function handleDayClick(date: string) {
  console.log('Day clicked:', date)
}

async function updateStatus(id: string, status: ReservationStatus) {
  try {
    await adminApi.updateReservationStatus(id, status)
    // Update local state
    const reservation = reservations.value.find(r => r.id === id)
    if (reservation) {
      reservation.status = status
    }
    // Refresh calendar events
    fetchCalendarEvents()
  } catch {
    // Handle error
  }
}

watch([currentPage, statusFilter], () => {
  fetchReservations()
})

watch(viewMode, (mode) => {
  if (mode === 'calendar') {
    fetchCalendarEvents()
  }
})

onMounted(() => {
  fetchReservations()
})
</script>
