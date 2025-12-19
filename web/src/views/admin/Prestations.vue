<template>
  <AdminLayout>
    <AdminHeader title="Prestations" subtitle="Gérez vos offres de services">
      <template #actions>
        <Button @click="openCreateModal">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nouvelle prestation
        </Button>
      </template>
    </AdminHeader>

    <div class="p-6">
      <div class="grid gap-6">
        <!-- Prestations List -->
        <div
          v-for="prestation in prestations"
          :key="prestation.id"
          :class="[
            'bg-white rounded-xl border border-gray-200 p-6 transition-all',
            !prestation.is_active && 'opacity-60'
          ]"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <h3 class="text-lg font-semibold text-gray-900">{{ prestation.title }}</h3>
                <span
                  :class="[
                    'px-2 py-0.5 text-xs font-medium rounded-full',
                    prestation.is_active
                      ? 'bg-green-100 text-green-800'
                      : 'bg-gray-100 text-gray-600'
                  ]"
                >
                  {{ prestation.is_active ? 'Active' : 'Inactive' }}
                </span>
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gold/10 text-gold">
                  {{ prestation.category }}
                </span>
              </div>
              <p class="text-gray-600 mb-4">{{ prestation.description }}</p>
              <div class="flex items-center gap-6 text-sm">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="font-semibold text-gray-900">{{ formatCurrency(prestation.price) }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="text-gray-600">{{ formatDuration(prestation.duration) }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="toggleActive(prestation)"
                :class="[
                  'p-2 rounded-lg transition-colors',
                  prestation.is_active
                    ? 'text-green-600 hover:bg-green-50'
                    : 'text-gray-400 hover:bg-gray-100'
                ]"
                :title="prestation.is_active ? 'Désactiver' : 'Activer'"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    v-if="prestation.is_active"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="prestation.is_active
                      ? 'M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'
                      : 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21'"
                  />
                </svg>
              </button>
              <button
                @click="openEditModal(prestation)"
                class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                title="Modifier"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
              </button>
              <button
                @click="confirmDelete(prestation)"
                class="p-2 text-red-500 hover:text-red-700 rounded-lg hover:bg-red-50"
                title="Supprimer"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="prestations.length === 0" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
          </svg>
          <p class="text-gray-500 mb-4">Aucune prestation créée</p>
          <Button @click="openCreateModal">Créer une prestation</Button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Modal v-model="showFormModal" :title="isEditing ? 'Modifier la prestation' : 'Nouvelle prestation'" size="lg">
      <form @submit.prevent="savePrestation" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <FormField
            v-model="form.title"
            label="Titre"
            required
            :error="formErrors.title"
          />
          <FormField
            v-model="form.category"
            type="select"
            label="Catégorie"
            required
            :options="categoryOptions"
            :error="formErrors.category"
          />
        </div>

        <FormField
          v-model="form.description"
          type="textarea"
          label="Description"
          required
          :rows="4"
          :error="formErrors.description"
        />

        <div class="grid grid-cols-2 gap-4">
          <FormField
            v-model.number="form.price"
            type="number"
            label="Prix (€)"
            required
            :min="0"
            :step="0.01"
            :error="formErrors.price"
          />
          <FormField
            v-model.number="form.duration"
            type="number"
            label="Durée (minutes)"
            required
            :min="15"
            :step="15"
            :error="formErrors.duration"
          />
        </div>

        <FormField
          v-model="form.is_active"
          type="checkbox"
          checkbox-label="Prestation active (visible sur le site)"
        />
      </form>

      <template #footer>
        <Button variant="secondary" @click="showFormModal = false">Annuler</Button>
        <Button :loading="isSaving" @click="savePrestation">
          {{ isEditing ? 'Enregistrer' : 'Créer' }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal v-model="showDeleteModal" title="Confirmer la suppression" size="sm">
      <p class="text-gray-600">
        Êtes-vous sûr de vouloir supprimer la prestation <strong>{{ prestationToDelete?.title }}</strong> ?
        Cette action est irréversible.
      </p>

      <template #footer>
        <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
        <Button variant="danger" :loading="isDeleting" @click="deletePrestation">Supprimer</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AdminLayout from '@/components/admin/AdminLayout.vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import { adminApi } from '@/services/adminApi'
import type { AdminPrestation, PrestationFormData } from '@/types/admin'

const prestations = ref<AdminPrestation[]>([])
const showFormModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const editingId = ref<string | null>(null)
const prestationToDelete = ref<AdminPrestation | null>(null)

const form = reactive<PrestationFormData>({
  title: '',
  description: '',
  price: 0,
  duration: 60,
  category: '',
  is_active: true,
})

const formErrors = reactive({
  title: '',
  description: '',
  price: '',
  duration: '',
  category: '',
})

const categoryOptions = [
  { value: 'portrait', label: 'Portrait' },
  { value: 'sport', label: 'Sport' },
  { value: 'animalier', label: 'Animalier' },
  { value: 'automobile', label: 'Automobile' },
  { value: 'entreprise', label: 'Entreprise' },
  { value: 'video', label: 'Vidéo' },
]

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
  }).format(amount)
}

function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes} min`
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  if (mins === 0) return `${hours}h`
  return `${hours}h${mins}`
}

function resetForm() {
  form.title = ''
  form.description = ''
  form.price = 0
  form.duration = 60
  form.category = ''
  form.is_active = true
  Object.keys(formErrors).forEach(key => {
    formErrors[key as keyof typeof formErrors] = ''
  })
}

function openCreateModal() {
  resetForm()
  isEditing.value = false
  editingId.value = null
  showFormModal.value = true
}

function openEditModal(prestation: AdminPrestation) {
  form.title = prestation.title
  form.description = prestation.description
  form.price = prestation.price
  form.duration = prestation.duration
  form.category = prestation.category
  form.is_active = prestation.is_active
  isEditing.value = true
  editingId.value = prestation.id
  showFormModal.value = true
}

function confirmDelete(prestation: AdminPrestation) {
  prestationToDelete.value = prestation
  showDeleteModal.value = true
}

async function fetchPrestations() {
  try {
    const response = await adminApi.getPrestations()
    if (response.success) {
      prestations.value = response.data
    }
  } catch {
    // Silently fail
  }
}

async function toggleActive(prestation: AdminPrestation) {
  try {
    await adminApi.togglePrestation(prestation.id)
    prestation.is_active = !prestation.is_active
  } catch {
    // Handle error
  }
}

function validateForm(): boolean {
  let isValid = true
  Object.keys(formErrors).forEach(key => {
    formErrors[key as keyof typeof formErrors] = ''
  })

  if (!form.title.trim()) {
    formErrors.title = 'Le titre est requis'
    isValid = false
  }
  if (!form.description.trim()) {
    formErrors.description = 'La description est requise'
    isValid = false
  }
  if (form.price <= 0) {
    formErrors.price = 'Le prix doit être supérieur à 0'
    isValid = false
  }
  if (form.duration < 15) {
    formErrors.duration = 'La durée minimum est de 15 minutes'
    isValid = false
  }
  if (!form.category) {
    formErrors.category = 'La catégorie est requise'
    isValid = false
  }

  return isValid
}

async function savePrestation() {
  if (!validateForm()) return

  isSaving.value = true
  try {
    if (isEditing.value && editingId.value) {
      await adminApi.updatePrestation(editingId.value, form)
    } else {
      await adminApi.createPrestation(form)
    }
    showFormModal.value = false
    fetchPrestations()
  } catch {
    // Handle error
  } finally {
    isSaving.value = false
  }
}

async function deletePrestation() {
  if (!prestationToDelete.value) return

  isDeleting.value = true
  try {
    await adminApi.deletePrestation(prestationToDelete.value.id)
    showDeleteModal.value = false
    prestationToDelete.value = null
    fetchPrestations()
  } catch {
    // Handle error
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  fetchPrestations()
})
</script>
