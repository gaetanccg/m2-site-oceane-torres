<template>
    <Modal v-model="visible" :title="gallery ? 'Modifier la galerie' : 'Nouvelle galerie'" size="md">
        <form @submit.prevent="saveGallery" class="space-y-4">
            <FormField
                v-model="form.title"
                label="Titre"
                required
                placeholder="Ex: Mariage de Marie et Pierre"
            />

            <FormField
                v-model="form.description"
                type="textarea"
                label="Description"
                :rows="3"
                placeholder="Description optionnelle de la galerie"
            />

            <FormField
                v-model="form.client_id"
                type="select"
                label="Client existant (optionnel)"
                :options="clientOptions"
                placeholder="Selectionner un client"
                :disabled="!!form.assigned_email"
            />

            <div class="relative flex items-center">
                <div class="flex-grow border-t border-gray-200"></div>
                <span class="mx-4 text-xs text-gray-400 uppercase">ou</span>
                <div class="flex-grow border-t border-gray-200"></div>
            </div>

            <FormField
                v-model="form.assigned_email"
                type="email"
                label="Email du futur client"
                placeholder="email@exemple.com"
                :disabled="!!form.client_id"
            />
            <p class="text-xs text-gray-500 -mt-2">
                La galerie sera automatiquement liée au client lorsqu'il s'inscrira avec cet email.
            </p>

            <!-- Product Types Configuration -->
            <div class="border-t border-gray-200 pt-4 mt-4">
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
                {{ gallery ? 'Enregistrer' : 'Creer' }}
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
import type { AdminGallery, Client, GalleryFormData } from '@/types/admin'

const props = defineProps<{
    modelValue: boolean
    gallery: AdminGallery | null
    clients: Client[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    'saved': []
}>()

const toast = useToast()
const isSaving = ref(false)

const {
    productTypesState, productTypesError, productTypesList,
    toggleProductType, updateProductPrice, resetProductTypes,
    loadProductTypesFromGallery, buildProductTypesPayload,
    addTier, removeTier, updateTierQuantity, updateTierPrice,
} = useProductTypes()

const form = reactive<GalleryFormData>({
    title: '',
    description: '',
    client_id: '',
    assigned_email: '',
})

const visible = computed({
    get: () => props.modelValue,
    set: (val: boolean) => emit('update:modelValue', val),
})

const clientOptions = computed(() =>
    props.clients.map(c => ({ value: c.id, label: c.name || c.email }))
)

function resetForm() {
    form.title = ''
    form.description = ''
    form.client_id = ''
    form.assigned_email = ''
    resetProductTypes()
}

// When modal opens, populate form from gallery prop (edit) or reset (create)
watch(() => props.modelValue, (isOpen) => {
    if (!isOpen) return
    if (props.gallery) {
        form.title = props.gallery.title
        form.description = props.gallery.description || ''
        form.client_id = props.gallery.client_id || ''
        form.assigned_email = props.gallery.assigned_email || ''
        loadProductTypesFromGallery(props.gallery.gallery_product_types)
    } else {
        resetForm()
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
            product_types: buildProductTypesPayload(),
        }
        if (props.gallery) {
            await adminApi.updateGallery(props.gallery.id, payload)
            toast.success('Galerie modifiée')
        } else {
            await adminApi.createGallery(payload)
            toast.success('Galerie créée')
        }
        visible.value = false
        emit('saved')
    } catch {
        toast.error('Erreur', 'Impossible de sauvegarder la galerie')
    } finally {
        isSaving.value = false
    }
}
</script>
