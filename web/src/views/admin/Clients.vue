<template>
  <div>
    <AdminHeader title="Clients" subtitle="Gerez votre base clients">
      <template #actions>
        <Button @click="openCreateModal">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nouveau client
        </Button>
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
      <DataTable
        :columns="columns"
        :data="clients"
        searchable
        v-model:search-query="searchQuery"
        v-model:current-page="currentPage"
        :total-pages="totalPages"
        :total="total"
        :from="from"
        :to="to"
      >
        <template #cell-name="{ row }">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gold/10 flex items-center justify-center">
              <span class="text-gold font-semibold">{{ getInitials(row.name) }}</span>
            </div>
            <div>
              <p class="font-medium text-gray-900">{{ row.name }}</p>
              <p class="text-sm text-gray-500">{{ row.email }}</p>
            </div>
          </div>
        </template>

        <template #cell-source="{ value }">
          <span :class="getSourceBadgeClass(value as string)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
            {{ getSourceLabel(value as string) }}
          </span>
        </template>

        <template #cell-phone="{ value }">
          <span class="text-gray-600">{{ value || '-' }}</span>
        </template>

        <template #cell-reservations_count="{ value }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gold/10 text-gold">
            {{ value }} reservation{{ (value as number) > 1 ? 's' : '' }}
          </span>
        </template>

        <template #cell-total_paid="{ value }">
          <span class="font-medium text-gray-900">{{ formatCurrency(value as number) }}</span>
        </template>

        <template #cell-created_at="{ value }">
          <span class="text-gray-600">{{ formatDate(value as string) }}</span>
        </template>

        <template #actions="{ row }">
          <div class="flex items-center gap-2">
            <button
              @click="openClientDetail(row)"
              class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
              title="Voir details"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
            <button
              @click="openEditModal(row)"
              class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
              title="Modifier"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button
              @click="confirmDelete(row)"
              class="p-2 text-red-500 hover:text-red-700 rounded-lg hover:bg-red-50"
              title="Supprimer (RGPD)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Create Client Modal -->
    <Modal v-model="showCreateModal" title="Nouveau client" size="md">
      <form @submit.prevent="createClient" class="space-y-4">
        <FormField
          v-model="createForm.name"
          label="Nom complet"
          required
          placeholder="Jean Dupont"
        />
        <FormField
          v-model="createForm.email"
          type="email"
          label="Email"
          required
          placeholder="jean.dupont@example.com"
        />
        <FormField
          v-model="createForm.phone"
          label="Telephone"
          placeholder="06 12 34 56 78"
        />
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
          <textarea
            v-model="createForm.notes"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold"
            placeholder="Notes visibles uniquement par l'admin..."
          ></textarea>
        </div>

        <!-- RGPD Consent -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              type="checkbox"
              v-model="createForm.gdpr_consent"
              class="mt-1 rounded border-gray-300 text-gold focus:ring-gold"
            />
            <div class="text-sm">
              <p class="font-medium text-blue-900">Consentement RGPD</p>
              <p class="text-blue-700 mt-1">
                Le client a donne son consentement pour le traitement de ses données personnelles
                conformement a notre politique de confidentialite.
              </p>
            </div>
          </label>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" @click="showCreateModal = false">Annuler</Button>
        <Button :loading="isCreating" @click="createClient">Creer le client</Button>
      </template>
    </Modal>

    <!-- Client Detail Modal -->
    <Modal v-model="showDetailModal" :title="selectedClient?.name || 'Details'" size="lg">
      <div v-if="selectedClient" class="space-y-6">
        <!-- Contact Info -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Informations de contact</h3>
          <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <p><span class="font-medium">Email:</span> {{ selectedClient.email }}</p>
            <p><span class="font-medium">Telephone:</span> {{ selectedClient.phone || 'Non renseigne' }}</p>
            <p><span class="font-medium">Source:</span> {{ getSourceLabel(selectedClient.source) }}</p>
            <p><span class="font-medium">Client depuis:</span> {{ formatDate(selectedClient.created_at) }}</p>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="selectedClient.notes">
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Notes internes</h3>
          <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-gray-700 whitespace-pre-wrap">{{ selectedClient.notes }}</p>
          </div>
        </div>

        <!-- Statistics -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Statistiques</h3>
          <div class="grid grid-cols-3 gap-4">
            <div class="bg-gray-50 rounded-lg p-4 text-center">
              <p class="text-2xl font-bold text-gray-900">{{ selectedClient.reservations_count || 0 }}</p>
              <p class="text-sm text-gray-500">Reservations</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 text-center">
              <p class="text-2xl font-bold text-gray-900">{{ selectedClient.galleries_count || 0 }}</p>
              <p class="text-sm text-gray-500">Galeries</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 text-center">
              <p class="text-2xl font-bold text-gold">{{ formatCurrency(selectedClient.total_paid || 0) }}</p>
              <p class="text-sm text-gray-500">Total verse</p>
            </div>
          </div>
        </div>

        <!-- RGPD Status -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">RGPD</h3>
          <div :class="selectedClient.gdpr_consent ? 'bg-green-50 border-green-200' : 'bg-orange-50 border-orange-200'" class="border rounded-lg p-4">
            <div class="flex items-center gap-2">
              <svg v-if="selectedClient.gdpr_consent" class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <svg v-else class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <span :class="selectedClient.gdpr_consent ? 'text-green-800' : 'text-orange-800'" class="font-medium">
                {{ selectedClient.gdpr_consent ? 'Consentement donne' : 'Consentement non enregistre' }}
              </span>
            </div>
            <p v-if="selectedClient.gdpr_consent && selectedClient.gdpr_consent_at" class="text-sm text-green-700 mt-1">
              Le {{ formatDate(selectedClient.gdpr_consent_at) }}
            </p>
          </div>
        </div>

        <!-- Quick Actions -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Actions</h3>
          <div class="flex flex-wrap gap-3">
            <Button variant="secondary" size="sm" @click="viewReservations">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              Voir reservations
            </Button>
            <Button variant="secondary" size="sm" @click="exportGdpr" :loading="isExporting">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Export RGPD
            </Button>
          </div>
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" @click="showDetailModal = false">Fermer</Button>
      </template>
    </Modal>

    <!-- Client Reservations Modal -->
    <Modal v-model="showReservationsModal" :title="`Reservations - ${selectedClient?.name || ''}`" size="xl">
      <div v-if="isLoadingReservations" class="flex justify-center py-8">
        <svg class="animate-spin h-8 w-8 text-gold" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
      <div v-else-if="clientReservations.length === 0" class="text-center py-8 text-gray-500">
        Aucune reservation pour ce client.
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="reservation in clientReservations"
          :key="reservation.id"
          class="border rounded-lg p-4 hover:bg-gray-50"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="font-medium">{{ reservation.prestation?.title || 'Prestation' }}</p>
              <p class="text-sm text-gray-500">
                {{ reservation.date ? formatDate(reservation.date) : 'Date a definir' }}
              </p>
            </div>
            <span :class="getStatusBadgeClass(reservation.status)" class="px-2.5 py-0.5 rounded-full text-xs font-medium">
              {{ getStatusLabel(reservation.status) }}
            </span>
          </div>
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" @click="showReservationsModal = false">Fermer</Button>
      </template>
    </Modal>

    <!-- Edit Client Modal -->
    <Modal v-model="showEditModal" title="Modifier le client" size="md">
      <form @submit.prevent="saveClient" class="space-y-4">
        <FormField
          v-model="editForm.name"
          label="Nom complet"
          required
        />
        <FormField
          v-model="editForm.email"
          type="email"
          label="Email"
          required
        />
        <FormField
          v-model="editForm.phone"
          label="Telephone"
        />
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
          <textarea
            v-model="editForm.notes"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold/50 focus:border-gold"
            placeholder="Notes visibles uniquement par l'admin..."
          ></textarea>
        </div>

        <!-- RGPD Consent -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              type="checkbox"
              v-model="editForm.gdpr_consent"
              class="mt-1 rounded border-gray-300 text-gold focus:ring-gold"
            />
            <div class="text-sm">
              <p class="font-medium text-blue-900">Consentement RGPD</p>
              <p class="text-blue-700 mt-1">
                Le client a donne son consentement pour le traitement de ses données personnelles.
              </p>
            </div>
          </label>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" @click="showEditModal = false">Annuler</Button>
        <Button :loading="isSaving" @click="saveClient">Enregistrer</Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal v-model="showDeleteModal" title="Suppression RGPD" size="sm">
      <div class="space-y-4">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="text-sm">
              <p class="font-medium text-red-900">Attention</p>
              <p class="text-red-700 mt-1">
                Cette action supprimera definitivement toutes les données personnelles du client
                <strong>{{ clientToDelete?.name }}</strong> conformement au RGPD (droit a l'oubli).
              </p>
            </div>
          </div>
        </div>
        <p class="text-gray-600 text-sm">
          Les reservations seront conservées de maniere anonymisée pour la comptabilite.
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
        <Button variant="danger" :loading="isDeleting" @click="deleteClient">Supprimer definitivement</Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted, onUnmounted } from 'vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import DataTable from '@/components/admin/ui/DataTable.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import { adminApi } from '@/services/adminApi'
import { useToast } from '@/composables/useToast'
import type { Client, Reservation, TableColumn, ReservationStatus } from '@/types/admin'

const toast = useToast()
const isLoading = ref(true)
const clients = ref<Client[]>([])
const searchQuery = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const showCreateModal = ref(false)
const showDetailModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const showReservationsModal = ref(false)
const selectedClient = ref<Client | null>(null)
const clientToDelete = ref<Client | null>(null)
const isCreating = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const isExporting = ref(false)
const isLoadingReservations = ref(false)
const clientReservations = ref<Reservation[]>([])

const createForm = reactive({
  name: '',
  email: '',
  phone: '',
  notes: '',
  gdpr_consent: false,
})

const editForm = reactive({
  id: '',
  name: '',
  email: '',
  phone: '',
  notes: '',
  gdpr_consent: false,
})

const columns: TableColumn<Client>[] = [
  { key: 'name', label: 'Client', sortable: true },
  { key: 'source', label: 'Source' },
  { key: 'phone', label: 'Telephone' },
  { key: 'reservations_count', label: 'Reservations', align: 'center' },
  { key: 'total_paid', label: 'Total verse', align: 'right' },
  { key: 'created_at', label: 'Crée le', sortable: true },
]

function getInitials(name: string): string {
  const parts = name.trim().split(' ')
  if (parts.length >= 2) {
    return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase()
  }
  return (name[0] || '?').toUpperCase()
}

function getSourceLabel(source: string): string {
  const labels: Record<string, string> = {
    reservation: 'Reservation',
    manual: 'Manuel',
    contact: 'Contact',
  }
  return labels[source] || source
}

function getSourceBadgeClass(source: string): string {
  const classes: Record<string, string> = {
    reservation: 'bg-blue-100 text-blue-800',
    manual: 'bg-purple-100 text-purple-800',
    contact: 'bg-green-100 text-green-800',
  }
  return classes[source] || 'bg-gray-100 text-gray-800'
}

function getStatusLabel(status: ReservationStatus): string {
  const labels: Record<ReservationStatus, string> = {
    pending: 'En attente',
    confirmed: 'Confirmee',
    cancelled: 'Annulee',
    completed: 'Terminee',
  }
  return labels[status] || status
}

function getStatusBadgeClass(status: ReservationStatus): string {
  const classes: Record<ReservationStatus, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    confirmed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    completed: 'bg-blue-100 text-blue-800',
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
  }).format(amount)
}

async function fetchClients() {
  try {
    const response = await adminApi.getClients(
      currentPage.value,
      20,
      searchQuery.value || undefined
    )
    clients.value = response.data
    totalPages.value = response.meta.last_page
    total.value = response.meta.total
    from.value = response.meta.from
    to.value = response.meta.to
  } catch {
    toast.error('Erreur', 'Impossible de charger les clients')
  } finally {
    isLoading.value = false
  }
}

function openCreateModal() {
  createForm.name = ''
  createForm.email = ''
  createForm.phone = ''
  createForm.notes = ''
  createForm.gdpr_consent = false
  showCreateModal.value = true
}

async function createClient() {
  isCreating.value = true
  try {
    await adminApi.createClient({
      name: createForm.name,
      email: createForm.email,
      phone: createForm.phone || undefined,
      notes: createForm.notes || undefined,
      gdpr_consent: createForm.gdpr_consent,
    })
    showCreateModal.value = false
    toast.success('Client créé')
    fetchClients()
  } catch {
    toast.error('Erreur', 'Impossible de créer le client')
  } finally {
    isCreating.value = false
  }
}

function openClientDetail(client: Client) {
  selectedClient.value = client
  showDetailModal.value = true
}

function openEditModal(client: Client) {
  editForm.id = client.id
  editForm.name = client.name
  editForm.email = client.email
  editForm.phone = client.phone || ''
  editForm.notes = client.notes || ''
  editForm.gdpr_consent = client.gdpr_consent
  showEditModal.value = true
}

function confirmDelete(client: Client) {
  clientToDelete.value = client
  showDeleteModal.value = true
}

async function saveClient() {
  isSaving.value = true
  try {
    await adminApi.updateClient(editForm.id, {
      name: editForm.name,
      email: editForm.email,
      phone: editForm.phone || undefined,
      notes: editForm.notes || undefined,
      gdpr_consent: editForm.gdpr_consent,
    })
    showEditModal.value = false
    toast.success('Client modifié')
    fetchClients()
  } catch {
    toast.error('Erreur', 'Impossible de modifier le client')
  } finally {
    isSaving.value = false
  }
}

async function deleteClient() {
  if (!clientToDelete.value) return

  isDeleting.value = true
  try {
    await adminApi.deleteClient(clientToDelete.value.id)
    showDeleteModal.value = false
    clientToDelete.value = null
    toast.success('Client supprimé')
    fetchClients()
  } catch {
    toast.error('Erreur', 'Impossible de supprimer le client')
  } finally {
    isDeleting.value = false
  }
}

async function viewReservations() {
  if (!selectedClient.value) return

  showReservationsModal.value = true
  isLoadingReservations.value = true

  try {
    const response = await adminApi.getClientReservations(selectedClient.value.id)
    clientReservations.value = response.data
  } catch {
    clientReservations.value = []
    toast.error('Erreur', 'Impossible de charger les réservations')
  } finally {
    isLoadingReservations.value = false
  }
}

async function exportGdpr() {
  if (!selectedClient.value) return

  isExporting.value = true
  try {
    const response = await adminApi.exportClientGdpr(selectedClient.value.id)

    // Download as JSON file
    const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `export-rgpd-${selectedClient.value.name.replace(/\s+/g, '-').toLowerCase()}-${new Date().toISOString().split('T')[0]}.json`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    toast.success('Export RGPD téléchargé')
  } catch {
    toast.error('Erreur', 'Impossible d\'exporter les données RGPD')
  } finally {
    isExporting.value = false
  }
}

// Debounce timer for search
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

// Watch currentPage changes (immediate fetch)
watch(currentPage, () => {
  fetchClients()
})

// Watch searchQuery with debounce (300ms delay)
watch(searchQuery, () => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }
  searchDebounceTimer = setTimeout(() => {
    currentPage.value = 1 // Reset to first page on search
    fetchClients()
  }, 300)
})

onMounted(() => {
  fetchClients()
})

onUnmounted(() => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }
})
</script>
