/**
 * Types centralisés pour l'application
 * Prêt pour l'intégration avec l'API Laravel
 */

// ============================================================================
// Navigation
// ============================================================================

export interface NavLink {
    name: string
    path: string
}

// ============================================================================
// Gallery
// ============================================================================

export type MediaType = 'image' | 'video' | 'youtube'

export interface GalleryItem {
    id?: number
    url: string
    thumbnailUrl?: string  // Low-res version for mobile/grid
    previewUrl?: string    // Medium-res version for desktop
    alt: string
    type: MediaType
    category?: string
    aspectRatio?: string
    youtubeId?: string
}

export interface LightboxImage {
    url: string
    alt: string
    type: MediaType
    youtubeId?: string
}

// ============================================================================
// Prestations / Services
// ============================================================================

export type ServiceIconType = 'portrait' | 'moto' | 'animal' | 'sport' | 'entreprise' | 'video' | 'camera'

export interface Prestation {
    id: string
    icon: ServiceIconType | null
    title: string
    description: string
    price: number
    price_text: string | null
    price_unit: string | null
    features: string[] | null
    background_image: string | null
    background_opacity: number
    disclaimer: string | null
    category: string
    is_active: boolean
    sort_order: number
}

export interface PrestationMini {
    icon: ServiceIconType
    title: string
    description: string
    features: string[]
}

// ============================================================================
// Bons Cadeaux (Gift Cards)
// ============================================================================

export interface GiftCard {
    id?: number
    title: string
    subtitle: string
    price: string
    features: string[]
    isPopular?: boolean
    variant?: 'light' | 'dark'
}

// ============================================================================
// Contact / Social
// ============================================================================

export interface SocialLink {
    name: string
    url: string
    icon: 'instagram' | 'tiktok' | 'linkedin'
    ariaLabel: string
}

export interface ContactInfo {
    email: string
    instagram: string
    phone: string
    address?: string
}

// ============================================================================
// Category Descriptions
// ============================================================================

export type CategoryDescriptions = Record<string, string>

// ============================================================================
// API Response Types (pour Laravel)
// ============================================================================

export interface ApiResponse<T> {
    data: T
    message?: string
    success: boolean
}

export interface PaginatedResponse<T> {
    data: T[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
}
