<template>
  <div>
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

    <!-- Loading State -->
    <div v-if="isLoading" class="p-6 flex items-center justify-center min-h-[400px]">
      <div class="flex flex-col items-center gap-3">
        <svg class="animate-spin h-8 w-8 text-gold" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-gray-500">Chargement...</span>
      </div>
    </div>

    <div v-else class="p-6">
      <!-- Calendar View -->
      <Calendar
        v-if="viewMode === 'calendar'"
        :events="calendarEvents"
        @event-click="openReservationDetail"
        @day-click="handleDayClick"
      />

      <!-- List View -->
      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-visible">
        <!-- Filters -->
        <div class="p-4 border-b border-gray-200 flex items-center gap-4">
          <select
            v-model="statusFilter"
            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
          >
            <option value="">Tous les statuts</option>
            <option value="pending">En attente</option>
            <option value="confirmed">Confirme</option>
            <option value="cancelled">Annule</option>
            <option value="completed">Termine</option>
          </select>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prestation</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="reservation in reservations" :key="reservation.id" class="hover:bg-gray-50">
                <td class="px-6 py-4">
                  <div>
                    <div class="flex items-center gap-2">
                      <p class="font-medium text-gray-900">{{ reservation.client_name || 'N/A' }}</p>
                      <span v-if="reservation.is_guest" class="px-1.5 py-0.5 text-xs bg-amber-100 text-amber-700 rounded">Demande</span>
                    </div>
                    <p class="text-sm text-gray-500">{{ reservation.client_email || '' }}</p>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <p class="font-medium text-gray-900">{{ reservation.prestation?.title || 'N/A' }}</p>
                </td>
                <td class="px-6 py-4">
                  <div v-if="reservation.date">
                    <p class="font-medium text-gray-900">{{ formatDate(reservation.date) }}</p>
                    <p class="text-sm text-gray-500">{{ formatTime(reservation.date) }}</p>
                  </div>
                  <span v-else class="text-amber-600 text-sm">A definir</span>
                </td>
                <td class="px-6 py-4">
                  <StatusBadge :status="reservation.status" />
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-end gap-1">
                    <button
                      @click="openReservationDetail(reservation)"
                      class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                      title="Voir details"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                    <button
                      @click="openEditModal(reservation)"
                      class="p-2 text-blue-500 hover:text-blue-700 rounded-lg hover:bg-blue-50"
                      title="Editer"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <button
                      v-if="reservation.status === 'pending'"
                      @click="confirmReservation(reservation)"
                      class="p-2 text-green-500 hover:text-green-700 rounded-lg hover:bg-green-50"
                      title="Confirmer"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                    <button
                      v-if="reservation.status !== 'cancelled'"
                      @click="cancelReservation(reservation)"
                      class="p-2 text-red-500 hover:text-red-700 rounded-lg hover:bg-red-50"
                      title="Annuler"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                    <button
                      @click="deleteReservation(reservation)"
                      class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50"
                      title="Supprimer"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="reservations.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                  Aucune reservation trouvee
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="p-4 border-t border-gray-200 flex items-center justify-between">
          <p class="text-sm text-gray-500">
            {{ from }} - {{ to }} sur {{ total }} resultats
          </p>
          <div class="flex gap-2">
            <button
              @click="currentPage--"
              :disabled="currentPage === 1"
              class="px-3 py-1 border border-gray-300 rounded-lg text-sm disabled:opacity-50"
            >
              Precedent
            </button>
            <button
              @click="currentPage++"
              :disabled="currentPage === totalPages"
              class="px-3 py-1 border border-gray-300 rounded-lg text-sm disabled:opacity-50"
            >
              Suivant
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reservation Detail Modal -->
    <Modal v-model="showDetailModal" :title="getReservationTitle(selectedReservation)" size="lg">
      <div v-if="selectedReservation" class="space-y-6">
        <!-- Client/Guest Info -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">
            {{ selectedReservation.is_guest ? 'Demandeur' : 'Client' }}
            <span v-if="selectedReservation.is_guest" class="ml-2 px-2 py-0.5 text-xs bg-amber-100 text-amber-700 rounded">Demande externe</span>
          </h3>
          <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <p><span class="font-medium">Nom:</span> {{ selectedReservation.client_name || 'N/A' }}</p>
            <p><span class="font-medium">Email:</span> {{ selectedReservation.client_email || 'N/A' }}</p>
            <p v-if="selectedReservation.client_phone">
              <span class="font-medium">Telephone:</span> {{ selectedReservation.client_phone }}
            </p>
          </div>
        </div>

        <!-- Date Preferences (for guest requests) -->
        <div v-if="selectedReservation.date_preferences">
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Dates souhaitées</h3>
          <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <p class="text-gray-700 whitespace-pre-wrap">{{ selectedReservation.date_preferences }}</p>
          </div>
        </div>

        <!-- Prestation Info -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Prestation</h3>
          <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <p><span class="font-medium">Type:</span> {{ selectedReservation.prestation?.title || 'N/A' }}</p>
            <p v-if="selectedReservation.date">
              <span class="font-medium">Date:</span> {{ formatDate(selectedReservation.date) }} a {{ formatTime(selectedReservation.date) }}
            </p>
            <p v-else class="text-amber-600"><span class="font-medium">Date:</span> A definir</p>
            <p><span class="font-medium">Statut:</span> <StatusBadge :status="selectedReservation.status" /></p>
          </div>
        </div>

        <!-- Notes/Message -->
        <div v-if="selectedReservation.message || selectedReservation.notes">
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Message / Notes</h3>
          <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <p v-if="selectedReservation.message" class="text-gray-700 whitespace-pre-wrap">{{ selectedReservation.message }}</p>
            <p v-if="selectedReservation.notes" class="text-gray-600 italic whitespace-pre-wrap">{{ selectedReservation.notes }}</p>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <Button
              v-if="selectedReservation?.status !== 'cancelled'"
              variant="danger"
              @click="cancelReservationFromModal"
            >
              Annuler
            </Button>
            <button
              @click="deleteReservationFromModal"
              class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
            >
              Supprimer
            </button>
          </div>
          <div class="flex items-center gap-2">
            <Button variant="secondary" @click="showDetailModal = false">Fermer</Button>
            <Button
              v-if="selectedReservation?.status === 'pending'"
              variant="success"
              @click="confirmReservationFromModal"
            >
              Confirmer
            </Button>
            <Button @click="showDetailModal = false; openEditModal(selectedReservation!)">Editer</Button>
          </div>
        </div>
      </template>
    </Modal>

    <!-- Edit Reservation Modal -->
    <Modal v-model="showEditModal" title="Editer la reservation" size="lg">
      <form @submit.prevent="saveReservation" class="space-y-6">
        <!-- Date et Heure -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Date et heure du rendez-vous</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
              <input
                v-model="editForm.date"
                type="date"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Heure</label>
              <input
                v-model="editForm.time"
                type="time"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
              />
            </div>
          </div>
        </div>

        <!-- Statut -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
          <select
            v-model="editForm.status"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold"
          >
            <option value="pending">En attente</option>
            <option value="confirmed">Confirme</option>
            <option value="cancelled">Annule</option>
            <option value="completed">Termine</option>
          </select>
        </div>

        <!-- Notes admin -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes (privées)</label>
          <textarea
            v-model="editForm.notes"
            rows="3"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold focus:border-gold resize-none"
            placeholder="Notes internes..."
          />
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" @click="showEditModal = false">Annuler</Button>
        <Button :loading="isSaving" @click="saveReservation">Enregistrer</Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Calendar from '@/components/admin/ui/Calendar.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import { adminApi } from '@/services/adminApi'
import { useToast } from '@/composables/useToast'
import type { Reservation, CalendarEvent, ReservationStatus } from '@/types/admin'

const toast = useToast()
const viewMode = ref<'list' | 'calendar'>('list')
const isLoading = ref(true)
const reservations = ref<Reservation[]>([])
const calendarEvents = ref<CalendarEvent[]>([])
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const showDetailModal = ref(false)
const showEditModal = ref(false)
const selectedReservation = ref<Reservation | null>(null)
const isSaving = ref(false)
const isLoadingReservation = ref(false)

const editForm = reactive({
  date: '',
  time: '',
  status: 'pending' as ReservationStatus,
  notes: '',
})

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('fr-FR', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function formatTime(dateStr: string): string {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function getReservationTitle(reservation: Reservation | null): string {
  if (!reservation) return 'Details'
  return reservation.client_name || 'Details'
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
    from.value = response.meta.from || 0
    to.value = response.meta.to || 0
  } catch {
    toast.error('Erreur', 'Impossible de charger les réservations')
  } finally {
    isLoading.value = false
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
    toast.error('Erreur', 'Impossible de charger le calendrier')
  }
}

async function openReservationDetail(item: Reservation | CalendarEvent) {
  // Detecter si c'est une Reservation complete ou un CalendarEvent
  // CalendarEvent a prestation comme string, Reservation a prestation comme objet
  const isFullReservation = item.prestation && typeof item.prestation === 'object'

  if (isFullReservation) {
    selectedReservation.value = item as Reservation
    showDetailModal.value = true
    return
  }

  // C'est un CalendarEvent, on charge la reservation complete
  isLoadingReservation.value = true

  try {
    const response = await adminApi.getReservation(item.id)
    if (response.success && response.data) {
      selectedReservation.value = response.data
      showDetailModal.value = true
    }
  } catch {
    toast.error('Erreur', 'Impossible de charger la réservation')
  } finally {
    isLoadingReservation.value = false
  }
}

function openEditModal(reservation: Reservation) {
  selectedReservation.value = reservation

  // Remplir le formulaire
  if (reservation.date) {
    const date = new Date(reservation.date)
    editForm.date = date.toISOString().split('T')[0]
    editForm.time = date.toTimeString().slice(0, 5)
  } else {
    editForm.date = ''
    editForm.time = ''
  }
  editForm.status = reservation.status
  editForm.notes = reservation.notes || ''

  showEditModal.value = true
}

async function saveReservation() {
  if (!selectedReservation.value) return

  isSaving.value = true
  try {
    const response = await adminApi.updateReservation(selectedReservation.value.id, {
      date: editForm.date || undefined,
      time: editForm.time || undefined,
      status: editForm.status,
      notes: editForm.notes || undefined,
    })

    if (response.success && response.data) {
      // Mettre a jour la liste locale
      const index = reservations.value.findIndex(r => r.id === selectedReservation.value!.id)
      if (index !== -1) {
        reservations.value[index] = response.data
      }
    }

    showEditModal.value = false
    toast.success('Réservation mise à jour')
    fetchCalendarEvents()
  } catch {
    toast.error('Erreur', 'Impossible de sauvegarder la réservation')
  } finally {
    isSaving.value = false
  }
}

async function confirmReservation(reservation: Reservation) {
  if (!reservation.date) {
    // Ouvrir le modal d'edition pour definir la date
    openEditModal(reservation)
    editForm.status = 'confirmed'
    return
  }

  try {
    await adminApi.updateReservationStatus(reservation.id, 'confirmed')
    reservation.status = 'confirmed'
    toast.success('Réservation confirmée')
    fetchCalendarEvents()
  } catch {
    toast.error('Erreur', 'Impossible de confirmer la réservation')
  }
}

async function cancelReservation(reservation: Reservation) {
  if (!confirm('Annuler cette reservation ?')) return

  try {
    await adminApi.updateReservationStatus(reservation.id, 'cancelled')
    reservation.status = 'cancelled'
    toast.success('Réservation annulée')
    fetchCalendarEvents()
  } catch {
    toast.error('Erreur', 'Impossible d\'annuler la réservation')
  }
}

async function confirmReservationFromModal() {
  if (!selectedReservation.value) return

  if (!selectedReservation.value.date) {
    // Ouvrir le modal d'edition pour definir la date
    showDetailModal.value = false
    openEditModal(selectedReservation.value)
    editForm.status = 'confirmed'
    return
  }

  try {
    await adminApi.updateReservationStatus(selectedReservation.value.id, 'confirmed')
    selectedReservation.value.status = 'confirmed'
    toast.success('Réservation confirmée')
    fetchReservations()
    fetchCalendarEvents()
  } catch {
    toast.error('Erreur', 'Impossible de confirmer la réservation')
  }
}

async function cancelReservationFromModal() {
  if (!selectedReservation.value) return
  if (!confirm('Annuler cette reservation ?')) return

  try {
    await adminApi.updateReservationStatus(selectedReservation.value.id, 'cancelled')
    selectedReservation.value.status = 'cancelled'
    showDetailModal.value = false
    toast.success('Réservation annulée')
    fetchReservations()
    fetchCalendarEvents()
  } catch {
    toast.error('Erreur', 'Impossible d\'annuler la réservation')
  }
}

async function deleteReservation(reservation: Reservation) {
  if (!confirm('Supprimer definitivement cette reservation ? Cette action est irreversible.')) return

  try {
    await adminApi.deleteReservation(reservation.id)
    reservations.value = reservations.value.filter(r => r.id !== reservation.id)
    toast.success('Réservation supprimée')
    fetchCalendarEvents()
  } catch {
    toast.error('Erreur', 'Impossible de supprimer la réservation')
  }
}

async function deleteReservationFromModal() {
  if (!selectedReservation.value) return
  if (!confirm('Supprimer definitivement cette reservation ? Cette action est irreversible.')) return

  try {
    await adminApi.deleteReservation(selectedReservation.value.id)
    showDetailModal.value = false
    toast.success('Réservation supprimée')
    fetchReservations()
    fetchCalendarEvents()
  } catch {
    toast.error('Erreur', 'Impossible de supprimer la réservation')
  }
}

function handleDayClick(_date: string) {
  // Day click handler - could open day detail view
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
