<template>
  <AdminLayout>
    <AdminHeader title="Clients" subtitle="Gérez votre base clients" />

    <div class="p-6">
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

        <template #cell-phone="{ value }">
          <span class="text-gray-600">{{ value || '-' }}</span>
        </template>

        <template #cell-reservations_count="{ value }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gold/10 text-gold">
            {{ value }} réservation{{ (value as number) > 1 ? 's' : '' }}
          </span>
        </template>

        <template #cell-total_spent="{ value }">
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
              title="Voir détails"
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
              title="Supprimer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Client Detail Modal -->
    <Modal v-model="showDetailModal" :title="selectedClient?.name || 'Détails'" size="lg">
      <div v-if="selectedClient" class="space-y-6">
        <!-- Contact Info -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Informations de contact</h3>
          <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <p><span class="font-medium">Email:</span> {{ selectedClient.email }}</p>
            <p><span class="font-medium">Téléphone:</span> {{ selectedClient.phone || 'Non renseigné' }}</p>
            <p><span class="font-medium">Client depuis:</span> {{ formatDate(selectedClient.created_at) }}</p>
          </div>
        </div>

        <!-- Statistics -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Statistiques</h3>
          <div class="grid grid-cols-3 gap-4">
            <div class="bg-gray-50 rounded-lg p-4 text-center">
              <p class="text-2xl font-bold text-gray-900">{{ selectedClient.reservations_count || 0 }}</p>
              <p class="text-sm text-gray-500">Réservations</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 text-center">
              <p class="text-2xl font-bold text-gray-900">{{ selectedClient.galleries_count || 0 }}</p>
              <p class="text-sm text-gray-500">Galeries</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 text-center">
              <p class="text-2xl font-bold text-gold">{{ formatCurrency(selectedClient.total_spent || 0) }}</p>
              <p class="text-sm text-gray-500">Total dépensé</p>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div>
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Actions</h3>
          <div class="flex gap-3">
            <Button variant="secondary" size="sm">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              Voir réservations
            </Button>
            <Button variant="secondary" size="sm">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              Voir galeries
            </Button>
          </div>
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" @click="showDetailModal = false">Fermer</Button>
      </template>
    </Modal>

    <!-- Edit Client Modal -->
    <Modal v-model="showEditModal" title="Modifier le client" size="md">
      <form @submit.prevent="saveClient" class="space-y-4">
        <FormField
          v-model="editForm.name"
          label="Nom"
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
          label="Téléphone"
        />
      </form>

      <template #footer>
        <Button variant="secondary" @click="showEditModal = false">Annuler</Button>
        <Button :loading="isSaving" @click="saveClient">Enregistrer</Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal v-model="showDeleteModal" title="Confirmer la suppression" size="sm">
      <p class="text-gray-600">
        Êtes-vous sûr de vouloir supprimer le client <strong>{{ clientToDelete?.name }}</strong> ?
        Cette action est irréversible.
      </p>

      <template #footer>
        <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
        <Button variant="danger" :loading="isDeleting" @click="deleteClient">Supprimer</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import AdminLayout from '@/components/admin/AdminLayout.vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import DataTable from '@/components/admin/ui/DataTable.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import { adminApi } from '@/services/adminApi'
import type { Client, TableColumn } from '@/types/admin'

const clients = ref<Client[]>([])
const searchQuery = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const showDetailModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const selectedClient = ref<Client | null>(null)
const clientToDelete = ref<Client | null>(null)
const isSaving = ref(false)
const isDeleting = ref(false)

const editForm = reactive({
  id: '',
  name: '',
  email: '',
  phone: '',
})

const columns: TableColumn<Client>[] = [
  { key: 'name', label: 'Client', sortable: true },
  { key: 'phone', label: 'Téléphone' },
  { key: 'reservations_count', label: 'Réservations', align: 'center' },
  { key: 'total_spent', label: 'Total dépensé', align: 'right' },
  { key: 'created_at', label: 'Inscrit le', sortable: true },
]

function getInitials(name: string): string {
  return name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
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
    // Silently fail
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
    })
    showEditModal.value = false
    fetchClients()
  } catch {
    // Handle error
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
    fetchClients()
  } catch {
    // Handle error
  } finally {
    isDeleting.value = false
  }
}

watch([currentPage, searchQuery], () => {
  fetchClients()
})

onMounted(() => {
  fetchClients()
})
</script>
