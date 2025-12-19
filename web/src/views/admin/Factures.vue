<template>
  <AdminLayout>
    <AdminHeader title="Factures" subtitle="Gérez vos factures et paiements">
      <template #actions>
        <Button @click="openCreateModal">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nouvelle facture
        </Button>
      </template>
    </AdminHeader>

    <div class="p-6">
      <!-- Stats Summary -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <StatCard
          label="Total facturé"
          :value="stats.total"
          format="currency"
        />
        <StatCard
          label="En attente"
          :value="stats.pending"
          format="currency"
          icon-bg-class="bg-yellow-100"
        >
          <template #icon>
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </template>
        </StatCard>
        <StatCard
          label="Payées"
          :value="stats.paid"
          format="currency"
          icon-bg-class="bg-green-100"
        >
          <template #icon>
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </template>
        </StatCard>
        <StatCard
          label="Ce mois"
          :value="stats.thisMonth"
          format="currency"
          icon-bg-class="bg-blue-100"
        >
          <template #icon>
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </template>
        </StatCard>
      </div>

      <!-- Factures Table -->
      <DataTable
        :columns="columns"
        :data="factures"
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
            <option value="draft">Brouillon</option>
            <option value="sent">Envoyée</option>
            <option value="paid">Payée</option>
            <option value="cancelled">Annulée</option>
          </select>
        </template>

        <template #cell-number="{ row }">
          <span class="font-mono font-medium text-gray-900">{{ row.number }}</span>
        </template>

        <template #cell-client.name="{ row }">
          <div>
            <p class="font-medium text-gray-900">{{ row.client.name }}</p>
            <p class="text-sm text-gray-500">{{ row.client.email }}</p>
          </div>
        </template>

        <template #cell-total_amount="{ value }">
          <span class="font-semibold text-gray-900">{{ formatCurrency(value as number) }}</span>
        </template>

        <template #cell-status="{ row }">
          <StatusBadge :status="row.status" />
        </template>

        <template #cell-due_date="{ row }">
          <div>
            <p :class="isOverdue(row) ? 'text-red-600 font-medium' : 'text-gray-900'">
              {{ formatDate(row.due_date) }}
            </p>
            <p v-if="isOverdue(row)" class="text-xs text-red-500">En retard</p>
          </div>
        </template>

        <template #cell-created_at="{ value }">
          <span class="text-gray-600">{{ formatDate(value as string) }}</span>
        </template>

        <template #actions="{ row }">
          <div class="flex items-center gap-2">
            <button
              @click="downloadPdf(row)"
              class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
              title="Télécharger PDF"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </button>
            <button
              v-if="row.status === 'draft' || row.status === 'sent'"
              @click="sendEmail(row)"
              class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
              title="Envoyer par email"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </button>
            <div v-if="canChangeStatus(row)" class="relative group">
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
                  v-if="canMarkAsPaid(row)"
                  @click="markAsPaid(row)"
                  class="w-full px-4 py-2 text-left text-sm text-green-600 hover:bg-green-50 rounded-t-lg"
                >
                  Marquer payée
                </button>
                <button
                  v-if="canCancel(row)"
                  @click="cancelFacture(row)"
                  class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 rounded-b-lg"
                >
                  Annuler
                </button>
              </div>
            </div>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Create Facture Modal -->
    <Modal v-model="showFormModal" title="Nouvelle facture" size="lg">
      <form @submit.prevent="saveFacture" class="space-y-4">
        <FormField
          v-model="form.client_id"
          type="select"
          label="Client"
          required
          :options="clientOptions"
          placeholder="Sélectionner un client"
        />

        <FormField
          v-model="form.reservation_id"
          type="select"
          label="Réservation associée (optionnel)"
          :options="reservationOptions"
          placeholder="Aucune réservation"
        />

        <div class="grid grid-cols-2 gap-4">
          <FormField
            v-model.number="form.amount"
            type="number"
            label="Montant HT (€)"
            required
            :min="0"
            :step="0.01"
          />
          <FormField
            v-model.number="form.tax_rate"
            type="number"
            label="TVA (%)"
            required
            :min="0"
            :max="100"
            :step="0.1"
          />
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Montant HT</span>
            <span class="font-medium">{{ formatCurrency(form.amount) }}</span>
          </div>
          <div class="flex justify-between text-sm mt-2">
            <span class="text-gray-600">TVA ({{ form.tax_rate }}%)</span>
            <span class="font-medium">{{ formatCurrency(taxAmount) }}</span>
          </div>
          <div class="flex justify-between text-lg font-semibold mt-3 pt-3 border-t border-gray-200">
            <span>Total TTC</span>
            <span class="text-gold">{{ formatCurrency(totalAmount) }}</span>
          </div>
        </div>

        <FormField
          v-model="form.due_date"
          type="date"
          label="Date d'échéance"
          required
        />
      </form>

      <template #footer>
        <Button variant="secondary" @click="showFormModal = false">Annuler</Button>
        <Button :loading="isSaving" @click="saveFacture">Créer</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import AdminLayout from '@/components/admin/AdminLayout.vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import DataTable from '@/components/admin/ui/DataTable.vue'
import StatCard from '@/components/admin/ui/StatCard.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import { adminApi } from '@/services/adminApi'
import type { Facture, FactureFormData, Client, Reservation, TableColumn } from '@/types/admin'

const factures = ref<Facture[]>([])
const clients = ref<Client[]>([])
const reservations = ref<Reservation[]>([])
const searchQuery = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const showFormModal = ref(false)
const isSaving = ref(false)

const stats = reactive({
  total: 0,
  pending: 0,
  paid: 0,
  thisMonth: 0,
})

const form = reactive<FactureFormData>({
  client_id: '',
  reservation_id: '',
  amount: 0,
  tax_rate: 20,
  due_date: '',
})

const taxAmount = computed(() => form.amount * (form.tax_rate / 100))
const totalAmount = computed(() => form.amount + taxAmount.value)

const columns: TableColumn<Facture>[] = [
  { key: 'number', label: 'N° Facture', sortable: true },
  { key: 'client.name', label: 'Client' },
  { key: 'total_amount', label: 'Montant', align: 'right' },
  { key: 'status', label: 'Statut' },
  { key: 'due_date', label: 'Échéance', sortable: true },
  { key: 'created_at', label: 'Créée le', sortable: true },
]

const clientOptions = computed(() =>
  clients.value.map(c => ({ value: c.id, label: c.name }))
)

const reservationOptions = computed(() => [
  { value: '', label: 'Aucune' },
  ...reservations.value.map(r => ({
    value: r.id,
    label: `${r.prestation.title} - ${formatDate(r.date)}`,
  })),
])

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
  }).format(amount)
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function isOverdue(facture: Facture): boolean {
  if (facture.status === 'paid' || facture.status === 'cancelled') return false
  return new Date(facture.due_date) < new Date()
}

function canChangeStatus(facture: Facture): boolean {
  return facture.status !== 'paid' && facture.status !== 'cancelled'
}

function canMarkAsPaid(facture: Facture): boolean {
  return facture.status !== 'paid'
}

function canCancel(facture: Facture): boolean {
  return facture.status !== 'cancelled'
}

function resetForm() {
  form.client_id = ''
  form.reservation_id = ''
  form.amount = 0
  form.tax_rate = 20
  // Set default due date to 30 days from now
  const dueDate = new Date()
  dueDate.setDate(dueDate.getDate() + 30)
  form.due_date = dueDate.toISOString().split('T')[0]
}

function openCreateModal() {
  resetForm()
  showFormModal.value = true
}

async function fetchFactures() {
  try {
    const response = await adminApi.getFactures(currentPage.value, 20)
    factures.value = response.data
    totalPages.value = response.meta.last_page
    total.value = response.meta.total
    from.value = response.meta.from
    to.value = response.meta.to

    // Calculate stats
    stats.total = factures.value.reduce((sum, f) => sum + f.total_amount, 0)
    stats.pending = factures.value
      .filter(f => f.status === 'sent' || f.status === 'draft')
      .reduce((sum, f) => sum + f.total_amount, 0)
    stats.paid = factures.value
      .filter(f => f.status === 'paid')
      .reduce((sum, f) => sum + f.total_amount, 0)

    const now = new Date()
    stats.thisMonth = factures.value
      .filter(f => {
        const date = new Date(f.created_at)
        return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear()
      })
      .reduce((sum, f) => sum + f.total_amount, 0)
  } catch {
    // Silently fail
  }
}

async function fetchClients() {
  try {
    const response = await adminApi.getClients(1, 100)
    clients.value = response.data
  } catch {
    // Silently fail
  }
}

async function fetchReservations() {
  try {
    const response = await adminApi.getReservations(1, 100)
    reservations.value = response.data
  } catch {
    // Silently fail
  }
}

async function saveFacture() {
  isSaving.value = true
  try {
    await adminApi.createFacture(form)
    showFormModal.value = false
    fetchFactures()
  } catch {
    // Handle error
  } finally {
    isSaving.value = false
  }
}

async function downloadPdf(facture: Facture) {
  try {
    const blob = await adminApi.downloadFacturePdf(facture.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `facture-${facture.number}.pdf`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch {
    // Handle error
  }
}

async function sendEmail(facture: Facture) {
  try {
    await adminApi.sendFactureEmail(facture.id)
    facture.status = 'sent'
  } catch {
    // Handle error
  }
}

async function markAsPaid(facture: Facture) {
  // In a real app, you'd have a dedicated endpoint for this
  facture.status = 'paid'
}

async function cancelFacture(facture: Facture) {
  // In a real app, you'd have a dedicated endpoint for this
  facture.status = 'cancelled'
}

watch([currentPage, statusFilter], () => {
  fetchFactures()
})

onMounted(() => {
  fetchFactures()
  fetchClients()
  fetchReservations()
})
</script>
