import type { AvailableProductType, ProductType } from '@/services/cartApi'

export type { AvailableProductType, ProductType }

/** Forme servie par PhotoResource (api/app/Http/Resources/PhotoResource.php). */
export interface Photo {
    id: string
    title: string | null
    is_video: boolean
    is_liked: boolean
    is_downloadable: boolean
    price: number | null
    display_url: string | null
    preview_url: string | null
    thumbnail_url: string | null
    /** Ajouté par le seul endpoint de téléchargement. */
    clean_thumbnail_url?: string | null
}

export interface EventCategory {
    id: string
    name: string
    slug: string
    sort_order: number
}

/** Forme servie par GalleryResource. Les clés optionnelles suivent whenLoaded/whenCounted. */
export interface Gallery {
    id: string
    title: string
    description: string | null
    class_name: string | null
    event_date: string | null
    event_link: string | null
    sort_order: number | null
    event_category_id: string | null
    created_at: string | null
    photos?: Photo[]
    photos_count?: number
    children_count?: number
    event_category?: EventCategory | null
    children?: Gallery[]
    parent?: Gallery | null
    cover_photo?: Photo | null
}

export interface DownloadableGallery extends Gallery {
    access_token: string
}

export interface PackTier {
    min_quantity: number
    unit_price: number
}

export interface PackPricingEntry {
    label: string
    base_price: number
    tiers: PackTier[]
}

export type ProductTypeMap<T> = Record<ProductType, T>

export interface ShareCodeResponse {
    gallery: Gallery
    mode: string
    available_product_types?: ProductTypeMap<AvailableProductType>
    pack_pricing?: ProductTypeMap<PackPricingEntry>
    school_message?: string | null
    school_closed?: boolean
}

export interface DownloadGalleryResponse {
    gallery: DownloadableGallery
    mode: string
}

export interface EventGalleryResponse {
    gallery: Gallery
    is_parent: boolean
    available_product_types?: ProductTypeMap<AvailableProductType>
    pack_pricing?: ProductTypeMap<PackPricingEntry>
}
