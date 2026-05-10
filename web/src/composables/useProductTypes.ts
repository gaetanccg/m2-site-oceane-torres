import { ref, computed } from 'vue'
import type { ProductType, GalleryProductTypeConfig, PackTier } from '@/types/admin'

// `print_scolaire` is configured via SchoolSession, not the regular gallery admin UI
type RegularProductType = Exclude<ProductType, 'print_scolaire'>

interface TierState {
    min_quantity: number
    unit_price: number
}

interface ProductTypeState {
    is_enabled: boolean
    price: number
    tiers: TierState[]
}

const DEFAULT_PRODUCT_TYPES: Record<RegularProductType, { label: string; price: number }> = {
    digital: { label: 'Photo numerique', price: 13 },
    print_10x15: { label: 'Impression 10x15', price: 10 },
    print_15x20: { label: 'Impression 15x20', price: 15 },
}

function createDefaultState(): Record<RegularProductType, ProductTypeState> {
    return {
        digital: { is_enabled: true, price: DEFAULT_PRODUCT_TYPES.digital.price, tiers: [] },
        print_10x15: { is_enabled: true, price: DEFAULT_PRODUCT_TYPES.print_10x15.price, tiers: [] },
        print_15x20: { is_enabled: true, price: DEFAULT_PRODUCT_TYPES.print_15x20.price, tiers: [] },
    }
}

export function useProductTypes() {
    const productTypesState = ref<Record<RegularProductType, ProductTypeState>>(createDefaultState())
    const productTypesError = ref('')

    const productTypesList = computed(() =>
        (Object.keys(DEFAULT_PRODUCT_TYPES) as RegularProductType[]).map(key => ({
            key,
            label: DEFAULT_PRODUCT_TYPES[key].label,
            is_enabled: productTypesState.value[key].is_enabled,
            price: productTypesState.value[key].price,
            tiers: productTypesState.value[key].tiers,
        }))
    )

    function toggleProductType(key: RegularProductType) {
        productTypesState.value[key].is_enabled = !productTypesState.value[key].is_enabled
        productTypesError.value = ''
    }

    function updateProductPrice(key: RegularProductType, value: string) {
        const num = parseFloat(value)
        if (!isNaN(num) && num > 0) {
            productTypesState.value[key].price = num
        }
    }

    function resetProductTypes() {
        productTypesState.value = createDefaultState()
        productTypesError.value = ''
    }

    function loadProductTypesFromGallery(configs?: GalleryProductTypeConfig[]) {
        if (!configs || configs.length === 0) {
            resetProductTypes()
            return
        }

        const state = {} as Record<RegularProductType, ProductTypeState>
        for (const key of Object.keys(DEFAULT_PRODUCT_TYPES) as RegularProductType[]) {
            const config = configs.find(c => c.product_type === key)
            const tiers: TierState[] = ((config?.pack_tiers ?? []) as PackTier[]).map(t => ({
                min_quantity: t.min_quantity,
                unit_price: Number(t.unit_price),
            }))
            state[key] = {
                is_enabled: config ? config.is_enabled : false,
                price: config?.price !== null && config?.price !== undefined
                    ? Number(config.price)
                    : DEFAULT_PRODUCT_TYPES[key].price,
                tiers,
            }
        }
        productTypesState.value = state
        productTypesError.value = ''
    }

    function buildProductTypesPayload() {
        return (Object.keys(productTypesState.value) as RegularProductType[]).map(key => ({
            product_type: key,
            is_enabled: productTypesState.value[key].is_enabled,
            price: productTypesState.value[key].price !== DEFAULT_PRODUCT_TYPES[key].price
                ? productTypesState.value[key].price
                : null,
            tiers: productTypesState.value[key].tiers
                .filter(t => t.min_quantity >= 2 && t.unit_price > 0)
                .map(t => ({ min_quantity: t.min_quantity, unit_price: t.unit_price })),
        }))
    }

    function addTier(key: RegularProductType) {
        if (productTypesState.value[key].tiers.length >= 3) return
        productTypesState.value[key].tiers.push({ min_quantity: 2, unit_price: 0 })
    }

    function removeTier(key: RegularProductType, index: number) {
        productTypesState.value[key].tiers.splice(index, 1)
    }

    function updateTierQuantity(key: RegularProductType, index: number, value: string) {
        const num = parseInt(value)
        if (!isNaN(num) && num >= 2) {
            productTypesState.value[key].tiers[index].min_quantity = num
        }
    }

    function updateTierPrice(key: RegularProductType, index: number, value: string) {
        const num = parseFloat(value)
        if (!isNaN(num) && num > 0) {
            productTypesState.value[key].tiers[index].unit_price = num
        }
    }

    return {
        productTypesState,
        productTypesError,
        productTypesList,
        toggleProductType,
        updateProductPrice,
        resetProductTypes,
        loadProductTypesFromGallery,
        buildProductTypesPayload,
        addTier,
        removeTier,
        updateTierQuantity,
        updateTierPrice,
    }
}
