<template>
    <Modal v-model="visible" :title="modalTitle" size="md">
        <form @submit.prevent="saveGallery" class="space-y-4">
            <FormField
                v-model="form.title"
                :label="parentId ? 'Nom de la sous-galerie' : 'Nom de l\'événement'"
                required
                :placeholder="parentId ? 'Ex: Épreuve 1 - Shetlands' : 'Ex: Mariage Julie & Thomas'"
            />

            <div v-if="!parentId">
                <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
                <select
                    v-model="form.event_category_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-gold focus:border-gold text-sm"
                >
                    <option value="">Aucune catégorie</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
            </div>

            <!-- Parent mode toggle (only for top-level creation) -->
            <div v-if="!parentId && !gallery" class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        v-model="isParentMode"
                        type="checkbox"
                        class="w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
                    />
                    <span class="text-sm font-medium text-gray-700">Galerie parent</span>
                </label>
                <span class="text-xs text-gray-500">Contiendra des sous-galeries au lieu de photos directes</span>
            </div>

            <FormField
                v-model="form.event_date"
                type="date"
                label="Date de l'événement"
                placeholder="Date de l'événement"
            />

            <FormField
                v-model="form.description"
                type="textarea"
                label="Description (optionnel)"
                :rows="3"
                placeholder="Une courte description de l'événement..."
            />

            <FormField
                v-model="form.event_link"
                label="Lien du site (optionnel)"
                placeholder="https://www.exemple.com"
            />

            <!-- Product Types Configuration (hidden for parent galleries) -->
            <div v-if="!isParentMode" class="border-t border-gray-200 pt-4 mt-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Produits disponibles</h4>
                <div class="space-y-3">
                    <div
                        v-for="pt in productTypesList"
                        :key="pt.key"
                        class="p-3 rounded-lg border transition-colors"
                        :class="pt.is_enabled ? 'border-gold/30 bg-gold/5' : 'border-gray-200 bg-gray-50'"
                    >
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer flex-shrink-0">
                                <input
                                    type="checkbox"
                                    :checked="pt.is_enabled"
                                    @change="toggleProductType(pt.key)"
                                    class="w-4 h-4 text-gold border-gray-300 rounded focus:ring-gold"
                                />
                                <span class="text-sm font-medium" :class="pt.is_enabled ? 'text-gray-900' : 'text-gray-400'">
                                    {{ pt.label }}
                                </span>
                            </label>
                            <div class="flex items-center gap-1 ml-auto">
                                <input
                                    type="number"
                                    :value="pt.price"
                                    @input="updateProductPrice(pt.key, ($event.target as HTMLInputElement).value)"
                                    :disabled="!pt.is_enabled"
                                    step="0.01"
                                    min="0.01"
                                    class="w-20 px-2 py-1 text-sm text-right border rounded-md focus:ring-gold focus:border-gold disabled:opacity-40 disabled:bg-gray-100"
                                    :class="pt.is_enabled ? 'border-gray-300' : 'border-gray-200'"
                                />
                                <span class="text-sm text-gray-500">&euro;</span>
                            </div>
                        </div>
                        <!-- Pack Tiers -->
                        <div v-if="pt.is_enabled" class="mt-3 ml-6 space-y-2">
                            <div v-for="(tier, ti) in pt.tiers" :key="ti" class="flex items-center gap-2 text-sm">
                                <span class="text-gray-500 whitespace-nowrap">À partir de</span>
                                <input
                                    type="number"
                                    :value="tier.min_quantity"
                                    @input="updateTierQuantity(pt.key, ti, ($event.target as HTMLInputElement).value)"
                                    min="2"
                                    class="w-16 px-2 py-1 text-sm text-center border border-gray-300 rounded-md focus:ring-gold focus:border-gold"
                                />
                                <span class="text-gray-500">photos &rarr;</span>
                                <input
                                    type="number"
                                    :value="tier.unit_price"
                                    @input="updateTierPrice(pt.key, ti, ($event.target as HTMLInputElement).value)"
                                    step="0.01"
                                    min="0.01"
                                    class="w-20 px-2 py-1 text-sm text-right border border-gray-300 rounded-md focus:ring-gold focus:border-gold"
                                />
                                <span class="text-gray-500">&euro;/photo</span>
                                <button type="button" @click="removeTier(pt.key, ti)" class="text-red-400 hover:text-red-600 ml-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <button
                                v-if="pt.tiers.length < 3"
                                type="button"
                                @click="addTier(pt.key)"
                                class="text-xs text-gold hover:text-gold/80 font-medium flex items-center gap-1"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Ajouter un palier pack
                            </button>
                        </div>
                    </div>
                </div>
                <p v-if="productTypesError" class="text-xs text-red-500 mt-2">{{ productTypesError }}</p>
            </div>
        </form>

        <template #footer>
            <Button variant="secondary" @click="visible = false">Annuler</Button>
            <Button :loading="isSaving" @click="saveGallery">
                {{ gallery ? 'Enregistrer' : 'Créer' }}
            </Button>
        </template>
    </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import Modal from '@/components/admin/ui/Modal.vue'
import Button from '@/components/admin/ui/Button.vue'
import FormField from '@/components/admin/ui/FormField.vue'
import { adminApi } from '@/services/adminApi'
import { useToast } from '@/composables/useToast'
import { useProductTypes } from '@/composables/useProductTypes'
import type { AdminGallery, EventGalleryFormData, EventCategory } from '@/types/admin'

const props = defineProps<{
    modelValue: boolean
    gallery: AdminGallery | null
    categories: EventCategory[]
    parentId: string | null
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    'saved': []
}>()

const toast = useToast()
const isSaving = ref(false)
const isParentMode = ref(false)

const {
    productTypesState, productTypesError, productTypesList,
    toggleProductType, updateProductPrice, resetProductTypes,
    loadProductTypesFromGallery, buildProductTypesPayload,
    addTier, removeTier, updateTierQuantity, updateTierPrice,
} = useProductTypes()

const form = reactive<EventGalleryFormData>({
    title: '',
    description: '',
    event_date: '',
    event_link: '',
    event_category_id: '',
    parent_id: null,
})

const visible = computed({
    get: () => props.modelValue,
    set: (val: boolean) => emit('update:modelValue', val),
})

const modalTitle = computed(() => {
    if (props.gallery) {
        return props.parentId ? 'Modifier la sous-galerie' : 'Modifier l\'événement'
    }
    return props.parentId ? 'Nouvelle sous-galerie' : 'Nouvel événement'
})

function resetForm() {
    form.title = ''
    form.description = ''
    form.event_date = ''
    form.event_link = ''
    form.event_category_id = ''
    form.parent_id = null
    isParentMode.value = false
    resetProductTypes()
}

// When modal opens, populate form from gallery prop (edit) or reset (create)
watch(() => props.modelValue, (isOpen) => {
    if (!isOpen) return
    if (props.gallery) {
        form.title = props.gallery.title
        form.description = props.gallery.description || ''
        form.event_date = props.gallery.event_date || ''
        form.event_link = props.gallery.event_link || ''
        form.event_category_id = props.gallery.event_category_id || ''
        isParentMode.value = (props.gallery.children_count ?? 0) > 0
        loadProductTypesFromGallery(props.gallery.gallery_product_types)
    } else {
        resetForm()
        if (props.parentId) {
            form.parent_id = props.parentId
        }
    }
})

async function saveGallery() {
    const hasEnabled = Object.values(productTypesState.value).some(pt => pt.is_enabled)
    if (!hasEnabled) {
        productTypesError.value = 'Au moins un type de produit doit etre actif.'
        return
    }

    isSaving.value = true
    try {
        const payload = {
            ...form,
            event_category_id: form.event_category_id || undefined,
            parent_id: form.parent_id || undefined,
            product_types: buildProductTypesPayload(),
        }
        if (props.gallery) {
            await adminApi.updateEventGallery(props.gallery.id, payload)
        } else {
            await adminApi.createEventGallery(payload)
        }
        visible.value = false
        const label = props.parentId ? 'Sous-galerie' : 'Événement'
        toast.success(props.gallery ? `${label} modifié` : `${label} créé`)
        emit('saved')
    } catch {
        toast.error('Erreur', 'Impossible de sauvegarder')
    } finally {
        isSaving.value = false
    }
}
</script>
