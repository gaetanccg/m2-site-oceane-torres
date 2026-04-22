<template>
    <div class="space-y-4">
        <div class="flex items-start gap-3 bg-gold/5 border border-gold/30 rounded-lg p-3">
            <svg class="w-5 h-5 text-gold flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            <div class="text-sm">
                <h3 class="font-medium text-gray-800">Livraison des tirages</h3>
                <p class="text-gray-600 text-xs mt-0.5">Vos tirages seront envoyés à cette adresse (France métropolitaine uniquement).</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Téléphone *
            </label>
            <input
                :value="modelValue.shipping_phone"
                @input="update('shipping_phone', ($event.target as HTMLInputElement).value)"
                type="tel"
                required
                autocomplete="tel-national"
                :class="inputClass('shipping_phone')"
                placeholder="0612345678"
            />
            <p v-if="errors.shipping_phone" class="mt-1 text-xs text-red-600">{{ errors.shipping_phone }}</p>
            <p v-else class="mt-1 text-xs text-gray-500">Numéro français à 10 chiffres.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Adresse *
            </label>
            <input
                :value="modelValue.shipping_address_line1"
                @input="update('shipping_address_line1', ($event.target as HTMLInputElement).value)"
                type="text"
                required
                autocomplete="address-line1"
                :class="inputClass('shipping_address_line1')"
                placeholder="12 rue des Photographes"
            />
            <p v-if="errors.shipping_address_line1" class="mt-1 text-xs text-red-600">{{ errors.shipping_address_line1 }}</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Complément d'adresse (appt, bâtiment, etc.)
            </label>
            <input
                :value="modelValue.shipping_address_line2 ?? ''"
                @input="update('shipping_address_line2', ($event.target as HTMLInputElement).value)"
                type="text"
                autocomplete="address-line2"
                :class="inputClass('shipping_address_line2')"
                placeholder="Bât. A, appartement 3 (optionnel)"
            />
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Code postal *
                </label>
                <input
                    :value="modelValue.shipping_postal_code"
                    @input="update('shipping_postal_code', ($event.target as HTMLInputElement).value)"
                    type="text"
                    required
                    autocomplete="postal-code"
                    maxlength="5"
                    :class="inputClass('shipping_postal_code')"
                    placeholder="42000"
                />
                <p v-if="errors.shipping_postal_code" class="mt-1 text-xs text-red-600">{{ errors.shipping_postal_code }}</p>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Ville *
                </label>
                <input
                    :value="modelValue.shipping_city"
                    @input="update('shipping_city', ($event.target as HTMLInputElement).value)"
                    type="text"
                    required
                    autocomplete="address-level2"
                    :class="inputClass('shipping_city')"
                    placeholder="Saint-Étienne"
                />
                <p v-if="errors.shipping_city" class="mt-1 text-xs text-red-600">{{ errors.shipping_city }}</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Pays
            </label>
            <input
                type="text"
                value="France"
                readonly
                class="w-full px-4 py-2 border border-gray-200 bg-gray-50 text-gray-500 rounded-lg cursor-not-allowed"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import {computed} from 'vue'
import type {ShippingAddress} from '@/services/cartApi'

const props = defineProps<{
    modelValue: ShippingAddress
    errors?: Partial<Record<keyof ShippingAddress, string>>
}>()

const emit = defineEmits<{
    'update:modelValue': [value: ShippingAddress]
}>()

const errors = computed(() => props.errors ?? {})

function update<K extends keyof ShippingAddress>(key: K, value: string): void {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value,
    })
}

function inputClass(field: keyof ShippingAddress): string {
    const base = 'w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-gold focus:border-transparent'
    return errors.value[field]
        ? `${base} border-red-400 bg-red-50`
        : `${base} border-gray-300`
}
</script>
