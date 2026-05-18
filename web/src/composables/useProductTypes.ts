import { ref, computed } from 'vue'
import type { ProductType, GalleryProductTypeConfig, PackTier } from '@/types/admin'

interface TierState {
    min_quantity: number
    unit_price: number
}

interface ProductTypeState {
    is_enabled: boolean
    price: number
    requires_shipping: boolean
    tiers: TierState[]
}

interface ProductTypeDefaults {
    label: string
    price: number
    requires_shipping: boolean
    enabled_by_default: boolean
}

// Mirrors api CartItem::PRODUCT_TYPES — keep prices in sync.
// print_scolaire is opt-in: livré à l'école, donc requires_shipping = false par défaut.
const DEFAULT_PRODUCT_TYPES: Record<ProductType, ProductTypeDefaults> = {
    digital: { label: 'Photo numerique', price: 13, requires_shipping: false, enabled_by_default: true },
    print_10x15: { label: 'Impression 10x15', price: 10, requires_shipping: true, enabled_by_default: true },
    print_15x20: { label: 'Impression 15x20', price: 15, requires_shipping: true, enabled_by_default: true },
    print_scolaire: { label: 'Tirage scolaire', price: 6, requires_shipping: false, enabled_by_default: false },
}

function createDefaultState(): Record<ProductType, ProductTypeState> {
    const state = {} as Record<ProductType, ProductTypeState>
    for (const key of Object.keys(DEFAULT_PRODUCT_TYPES) as ProductType[]) {
        const d = DEFAULT_PRODUCT_TYPES[key]
        state[key] = {
            is_enabled: d.enabled_by_default,
            price: d.price,
            requires_shipping: d.requires_shipping,
            tiers: [],
        }
    }
    return state
}

export function useProductTypes() {
    const productTypesState = ref<Record<ProductType, ProductTypeState>>(createDefaultState())
    const productTypesError = ref('')

    const productTypesList = computed(() =>
        (Object.keys(DEFAULT_PRODUCT_TYPES) as ProductType[]).map(key => ({
            key,
            label: DEFAULT_PRODUCT_TYPES[key].label,
            is_enabled: productTypesState.value[key].is_enabled,
            price: productTypesState.value[key].price,
            requires_shipping: productTypesState.value[key].requires_shipping,
            tiers: productTypesState.value[key].tiers,
        }))
    )

    function toggleProductType(key: ProductType) {
        productTypesState.value[key].is_enabled = !productTypesState.value[key].is_enabled
        productTypesError.value = ''
    }

    function updateProductPrice(key: ProductType, value: string) {
        const num = parseFloat(value)
        if (!isNaN(num) && num > 0) {
            productTypesState.value[key].price = num
        }
    }

    function toggleRequiresShipping(key: ProductType) {
        productTypesState.value[key].requires_shipping = !productTypesState.value[key].requires_shipping
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

        const state = {} as Record<ProductType, ProductTypeState>
        for (const key of Object.keys(DEFAULT_PRODUCT_TYPES) as ProductType[]) {
            const d = DEFAULT_PRODUCT_TYPES[key]
            const config = configs.find(c => c.product_type === key)
            const tiers: TierState[] = ((config?.pack_tiers ?? []) as PackTier[]).map(t => ({
                min_quantity: t.min_quantity,
                unit_price: Number(t.unit_price),
            }))
            state[key] = {
                is_enabled: config ? config.is_enabled : false,
                price: config?.price !== null && config?.price !== undefined
                    ? Number(config.price)
                    : d.price,
                requires_shipping: config?.requires_shipping !== null && config?.requires_shipping !== undefined
                    ? Boolean(config.requires_shipping)
                    : d.requires_shipping,
                tiers,
            }
        }
        productTypesState.value = state
        productTypesError.value = ''
    }

    function buildProductTypesPayload() {
        return (Object.keys(productTypesState.value) as ProductType[]).map(key => ({
            product_type: key,
            is_enabled: productTypesState.value[key].is_enabled,
            price: productTypesState.value[key].price !== DEFAULT_PRODUCT_TYPES[key].price
                ? productTypesState.value[key].price
                : null,
            requires_shipping: productTypesState.value[key].requires_shipping,
            tiers: productTypesState.value[key].tiers
                .filter(t => t.min_quantity >= 2 && t.unit_price > 0)
                .map(t => ({ min_quantity: t.min_quantity, unit_price: t.unit_price })),
        }))
    }

    function addTier(key: ProductType) {
        if (productTypesState.value[key].tiers.length >= 3) return
        productTypesState.value[key].tiers.push({ min_quantity: 2, unit_price: 0 })
    }

    function removeTier(key: ProductType, index: number) {
        productTypesState.value[key].tiers.splice(index, 1)
    }

    function updateTierQuantity(key: ProductType, index: number, value: string) {
        const num = parseInt(value)
        if (!isNaN(num) && num >= 2) {
            productTypesState.value[key].tiers[index].min_quantity = num
        }
    }

    function updateTierPrice(key: ProductType, index: number, value: string) {
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
        toggleRequiresShipping,
        resetProductTypes,
        loadProductTypesFromGallery,
        buildProductTypesPayload,
        addTier,
        removeTier,
        updateTierQuantity,
        updateTierPrice,
    }
}
