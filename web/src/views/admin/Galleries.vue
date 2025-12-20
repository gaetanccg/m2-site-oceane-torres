<template>
  <AdminLayout>
    <AdminHeader title="Galeries" subtitle="Gérez vos galeries photos publiques et privées">
      <template #actions>
        <Button @click="openCreateModal">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nouvelle galerie
        </Button>
      </template>
    </AdminHeader>

    <div class="p-6">
      <!-- Filters -->
      <div class="flex gap-4 mb-6">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="activeTab = tab.value"
          :class="[
            'px-4 py-2 rounded-lg font-medium transition-colors',
            activeTab === tab.value
              ? 'bg-gold text-white'
              : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
          ]"
        >
          {{ tab.label }}
          <span
            :class="[
              'ml-2 px-2 py-0.5 rounded-full text-xs',
              activeTab === tab.value ? 'bg-white/20' : 'bg-gray-100'
            ]"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- Galleries Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="gallery in filteredGalleries"
          :key="gallery.id"
          class="bg-white rounded-xl border border-gray-200 overflow-hidden group"
        >
          <!-- Cover Image -->
          <div class="relative h-48 bg-gray-100">
            <img
              v-if="gallery.cover_image"
              :src="gallery.cover_image"
              :alt="gallery.title"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>

            <!-- Overlay -->
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
              <div class="flex gap-2">
                <button
                  @click="openGallery(gallery)"
                  class="p-2 bg-white rounded-lg text-gray-700 hover:bg-gray-100"
                  title="Gérer les photos"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </button>
                <button
                  @click="openEditModal(gallery)"
                  class="p-2 bg-white rounded-lg text-gray-700 hover:bg-gray-100"
                  title="Modifier"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Type Badge -->
            <div class="absolute top-3 left-3">
              <StatusBadge :status="gallery.type" />
            </div>
          </div>

          <!-- Info -->
          <div class="p-4">
            <div class="flex items-start justify-between mb-2">
              <h3 class="font-semibold text-gray-900">{{ gallery.title }}</h3>
              <span
                :class="[
                  'w-2 h-2 rounded-full',
                  gallery.is_active ? 'bg-green-500' : 'bg-gray-300'
                ]"
                :title="gallery.is_active ? 'Active' : 'Inactive'"
              />
            </div>

            <p v-if="gallery.description" class="text-sm text-gray-500 mb-3 line-clamp-2">
              {{ gallery.description }}
            </p>

            <div class="flex items-center justify-between text-sm">
              <span class="text-gray-500">
                {{ gallery.photos_count }} photo{{ gallery.photos_count > 1 ? 's' : '' }}
              </span>

              <div v-if="gallery.type === 'private' && gallery.client" class="flex items-center gap-1 text-gray-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ gallery.client.first_name }} {{ gallery.client.last_name }}
              </div>
            </div>

            <!-- Private Gallery Token -->
            <div v-if="gallery.type === 'private' && gallery.access_token" class="mt-3 pt-3 border-t border-gray-100">
              <div class="flex items-center gap-2">
                <input
                  type="text"
                  :value="getGalleryUrl(gallery.access_token)"
                  readonly
                  class="flex-1 px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg truncate"
                />
                <button
                  @click="copyToClipboard(getGalleryUrl(gallery.access_token))"
                  class="p-1.5 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                  title="Copier le lien"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </button>
                <button
                  @click="regenerateToken(gallery)"
                  class="p-1.5 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                  title="Régénérer le token"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                </button>
              </div>
              <p v-if="gallery.expires_at" class="text-xs text-gray-400 mt-2">
                Expire le {{ formatDate(gallery.expires_at) }}
              </p>
            </div>

            <!-- Actions -->
            <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
              <button
                @click="openGallery(gallery)"
                class="flex-1 px-3 py-2 text-sm font-medium text-gold bg-gold/10 rounded-lg hover:bg-gold/20 transition-colors"
              >
                Gérer les photos
              </button>
              <button
                @click="confirmDelete(gallery)"
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
        <div
          v-if="filteredGalleries.length === 0"
          class="col-span-full bg-white rounded-xl border border-gray-200 p-12 text-center"
        >
          <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <p class="text-gray-500 mb-4">Aucune galerie {{ activeTab !== 'all' ? activeTab === 'public' ? 'publique' : 'privée' : '' }}</p>
          <Button @click="openCreateModal">Créer une galerie</Button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Modal v-model="showFormModal" :title="isEditing ? 'Modifier la galerie' : 'Nouvelle galerie'" size="lg">
      <form @submit.prevent="saveGallery" class="space-y-4">
        <FormField
          v-model="form.title"
          label="Titre"
          required
        />

        <FormField
          v-model="form.description"
          type="textarea"
          label="Description"
          :rows="3"
        />

        <FormField
          v-model="form.type"
          type="select"
          label="Type"
          required
          :options="typeOptions"
        />

        <FormField
          v-if="form.type === 'private'"
          v-model="form.client_id"
          type="select"
          label="Client"
          :options="clientOptions"
          placeholder="Sélectionner un client"
        />

        <FormField
          v-if="form.type === 'private'"
          v-model="form.expires_at"
          type="date"
          label="Date d'expiration"
          helper="Laisser vide pour un accès permanent"
        />

        <FormField
          v-model="form.is_active"
          type="checkbox"
          checkbox-label="Galerie active (visible)"
        />
      </form>

      <template #footer>
        <Button variant="secondary" @click="showFormModal = false">Annuler</Button>
        <Button :loading="isSaving" @click="saveGallery">
          {{ isEditing ? 'Enregistrer' : 'Créer' }}
        </Button>
      </template>
    </Modal>

    <!-- Gallery Photos Modal -->
    <Modal v-model="showPhotosModal" :title="selectedGallery?.title || 'Photos'" size="full">
      <div v-if="selectedGallery" class="space-y-4">
        <!-- Upload Zone -->
        <div
          @dragover.prevent="isDragging = true"
          @dragleave="isDragging = false"
          @drop.prevent="handleDrop"
          :class="[
            'border-2 border-dashed rounded-xl p-8 text-center transition-colors',
            isDragging ? 'border-gold bg-gold/5' : 'border-gray-300 hover:border-gray-400'
          ]"
        >
          <input
            type="file"
            ref="fileInput"
            multiple
            accept="image/*"
            class="hidden"
            @change="handleFileSelect"
          />
          <svg class="w-10 h-10 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          <p class="text-gray-600 mb-2">Glissez vos photos ici ou</p>
          <Button variant="secondary" size="sm" @click="triggerFileInput">
            Parcourir
          </Button>
        </div>

        <!-- Upload Progress -->
        <div v-if="uploadProgress > 0 && uploadProgress < 100" class="space-y-2">
          <div class="flex justify-between text-sm text-gray-600">
            <span>Upload en cours...</span>
            <span>{{ uploadProgress }}%</span>
          </div>
          <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div
              class="h-full bg-gold transition-all duration-300"
              :style="{ width: `${uploadProgress}%` }"
            />
          </div>
        </div>

        <!-- Photos Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
          <div
            v-for="photo in galleryPhotos"
            :key="photo.id"
            class="relative group aspect-square rounded-lg overflow-hidden bg-gray-100"
          >
            <img
              :src="photo.thumbnail_path || photo.path"
              :alt="photo.original_filename"
              class="w-full h-full object-cover"
            />
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
              <button
                @click="deletePhoto(photo.id)"
                class="p-2 bg-red-500 rounded-lg text-white hover:bg-red-600"
                title="Supprimer"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
            <div class="absolute bottom-2 left-2 right-2">
              <div class="flex items-center gap-1 text-white text-xs bg-black/50 rounded px-2 py-1">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                </svg>
                {{ photo.likes_count }}
              </div>
            </div>
          </div>
        </div>

        <div v-if="galleryPhotos.length === 0" class="py-12 text-center text-gray-500">
          Aucune photo dans cette galerie
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" @click="showPhotosModal = false">Fermer</Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal v-model="showDeleteModal" title="Confirmer la suppression" size="sm">
      <p class="text-gray-600">
        Êtes-vous sûr de vouloir supprimer la galerie <strong>{{ galleryToDelete?.title }}</strong> ?
        Toutes les photos seront également supprimées. Cette action est irréversible.
      </p>

      <template #footer>
        <Button variant="secondary" @click="showDeleteModal = false">Annuler</Button>
        <Button variant="danger" :loading="isDeleting" @click="deleteGallery">Supprimer</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import AdminLayout from '@/components/admin/AdminLayout.vue'
import AdminHeader from '@/components/admin/AdminHeader.vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import StatusBadge from '@/components/admin/ui/StatusBadge.vue'
import { adminApi } from '@/services/adminApi'
import type { AdminGallery, AdminPhoto, GalleryFormData, Client } from '@/types/admin'

const galleries = ref<AdminGallery[]>([])
const clients = ref<Client[]>([])
const galleryPhotos = ref<AdminPhoto[]>([])
const activeTab = ref<'all' | 'public' | 'private'>('all')
const showFormModal = ref(false)
const showPhotosModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const isDragging = ref(false)
const uploadProgress = ref(0)
const fileInput = ref<HTMLInputElement | null>(null)
const editingId = ref<string | null>(null)
const selectedGallery = ref<AdminGallery | null>(null)
const galleryToDelete = ref<AdminGallery | null>(null)

const form = reactive<GalleryFormData>({
  title: '',
  description: '',
  type: 'public',
  client_id: '',
  expires_at: '',
  is_active: true,
})

const tabs = computed(() => [
  { value: 'all' as const, label: 'Toutes', count: galleries.value.length },
  { value: 'public' as const, label: 'Publiques', count: galleries.value.filter(g => g.type === 'public').length },
  { value: 'private' as const, label: 'Privées', count: galleries.value.filter(g => g.type === 'private').length },
])

const filteredGalleries = computed(() => {
  if (activeTab.value === 'all') return galleries.value
  return galleries.value.filter(g => g.type === activeTab.value)
})

const typeOptions = [
  { value: 'public', label: 'Publique' },
  { value: 'private', label: 'Privée' },
]

const clientOptions = computed(() =>
  clients.value.map(c => ({ value: c.id, label: `${c.first_name} ${c.last_name}` }))
)

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function getGalleryUrl(token: string): string {
  return `${window.location.origin}/gallery/${token}`
}

async function copyToClipboard(text: string) {
  try {
    await navigator.clipboard.writeText(text)
  } catch {
    // Fallback
  }
}

function resetForm() {
  form.title = ''
  form.description = ''
  form.type = 'public'
  form.client_id = ''
  form.expires_at = ''
  form.is_active = true
}

function openCreateModal() {
  resetForm()
  isEditing.value = false
  editingId.value = null
  showFormModal.value = true
}

function openEditModal(gallery: AdminGallery) {
  form.title = gallery.title
  form.description = gallery.description || ''
  form.type = gallery.type
  form.client_id = gallery.client_id || ''
  form.expires_at = gallery.expires_at?.split('T')[0] || ''
  form.is_active = gallery.is_active
  isEditing.value = true
  editingId.value = gallery.id
  showFormModal.value = true
}

function openGallery(gallery: AdminGallery) {
  selectedGallery.value = gallery
  galleryPhotos.value = []
  showPhotosModal.value = true
  // In a real app, fetch photos here
}

function confirmDelete(gallery: AdminGallery) {
  galleryToDelete.value = gallery
  showDeleteModal.value = true
}

async function fetchGalleries() {
  try {
    const response = await adminApi.getGalleries()
    galleries.value = response.data
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

function triggerFileInput() {
  fileInput.value?.click()
}

async function saveGallery() {
  isSaving.value = true
  try {
    if (isEditing.value && editingId.value) {
      await adminApi.updateGallery(editingId.value, form)
    } else {
      await adminApi.createGallery(form)
    }
    showFormModal.value = false
    fetchGalleries()
  } catch {
    // Handle error
  } finally {
    isSaving.value = false
  }
}

async function deleteGallery() {
  if (!galleryToDelete.value) return

  isDeleting.value = true
  try {
    await adminApi.deleteGallery(galleryToDelete.value.id)
    showDeleteModal.value = false
    galleryToDelete.value = null
    fetchGalleries()
  } catch {
    // Handle error
  } finally {
    isDeleting.value = false
  }
}

async function regenerateToken(gallery: AdminGallery) {
  try {
    const response = await adminApi.regenerateGalleryToken(gallery.id)
    if (response.success) {
      gallery.access_token = response.data.token
    }
  } catch {
    // Handle error
  }
}

function handleDrop(event: DragEvent) {
  isDragging.value = false
  const files = Array.from(event.dataTransfer?.files || [])
  uploadPhotos(files)
}

function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement
  const files = Array.from(target.files || [])
  uploadPhotos(files)
}

async function uploadPhotos(files: File[]) {
  if (!selectedGallery.value || files.length === 0) return

  uploadProgress.value = 1
  try {
    const response = await adminApi.uploadPhotos(selectedGallery.value.id, files)
    if (response.success) {
      galleryPhotos.value.push(...response.data)
    }
  } catch {
    // Handle error
  } finally {
    uploadProgress.value = 100
    setTimeout(() => {
      uploadProgress.value = 0
    }, 1000)
  }
}

async function deletePhoto(photoId: string) {
  try {
    await adminApi.deletePhoto(photoId)
    galleryPhotos.value = galleryPhotos.value.filter(p => p.id !== photoId)
  } catch {
    // Handle error
  }
}

onMounted(() => {
  fetchGalleries()
  fetchClients()
})
</script>
