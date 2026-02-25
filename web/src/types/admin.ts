/**
 * Types pour l'interface d'administration
 */

// ============================================================================
// User / Auth
// ============================================================================

export interface User {
    id: string
    email: string
    first_name: string
    last_name: string
    phone?: string
    role: 'admin' | 'client'
    created_at: string
    updated_at: string
}

// Helper pour obtenir le nom complet
export function getUserFullName(user: User): string {
    return `${user.first_name} ${user.last_name}`
}

export interface AuthResponse {
    token: string
    user: User
}

// ============================================================================
// Dashboard
// ============================================================================

export interface DashboardStats {
    reservations: {
        total: number
        pending: number
        confirmed: number
        thisMonth: number
    }
    clients: {
        total: number
        newThisMonth: number
    }
    revenue: {
        total: number
        thisMonth: number
        lastMonth: number
    }
    galleries: {
        total: number
        public: number
        private: number
    }
}

export interface RecentActivity {
    id: string
    type: 'reservation' | 'payment' | 'gallery' | 'contact'
    message: string
    date: string
}

// ============================================================================
// Reservations
// ============================================================================

export type ReservationStatus = 'pending' | 'confirmed' | 'cancelled' | 'completed'

export interface Reservation {
    id: string
    user_id?: string
    user?: Client // Relation user (peut etre null pour les guests)
    prestation?: AdminPrestation
    date?: string
    time?: string
    status: ReservationStatus
    notes?: string
    message?: string
    // Champs guest (demandes sans compte)
    guest_name?: string
    guest_email?: string
    guest_phone?: string
    date_preferences?: string
    // Champs clientForm (anciennes reservations)
    client_form?: {
        fullname?: string
        phone?: string
        requirements?: string
        message?: string
    }
    // Attributs calcules par le backend (via $appends)
    client_name?: string
    client_email?: string
    client_phone?: string
    is_guest?: boolean
    created_at: string
    updated_at: string
}

export interface CalendarEvent {
    id: string
    title: string
    start: string
    end: string
    status: ReservationStatus
    client: string
    prestation: string
}

// ============================================================================
// Clients
// ============================================================================

export type ClientSource = 'reservation' | 'manual' | 'contact'

export interface Client {
    id: string
    name: string
    email: string
    phone?: string
    notes?: string
    source: ClientSource
    total_paid: number
    gdpr_consent: boolean
    gdpr_consent_at?: string
    reservations_count?: number
    galleries_count?: number
    user_id?: string
    created_at: string
    updated_at: string
}

export interface ClientFormData {
    name: string
    email: string
    phone?: string
    notes?: string
    gdpr_consent?: boolean
}

export interface ClientGdprExport {
    personal_data: {
        name: string
        email: string
        phone?: string
        created_at: string
    }
    consent: {
        gdpr_consent: boolean
        gdpr_consent_at?: string
    }
    reservations: Array<{
        id: string
        prestation?: string
        date?: string
        status: string
        created_at: string
    }>
    payments: Array<{
        amount: number
        currency: string
        status: string
        created_at: string
    }>
    exported_at: string
}

// ============================================================================
// Prestations
// ============================================================================

export type PrestationIcon = 'portrait' | 'sport' | 'animal' | 'moto' | 'entreprise' | 'video'

export interface AdminPrestation {
    id: string
    title: string
    icon: PrestationIcon | null
    description: string
    features: string[] | null
    price: number
    price_text: string | null
    price_unit: string | null
    duration: number | null
    category: string
    background_image: string | null
    background_opacity: number
    disclaimer: string | null
    is_active: boolean
    sort_order: number
    created_at: string
    updated_at: string
}

export interface PrestationFormData {
    title: string
    icon: PrestationIcon | null
    description: string
    features: string[]
    price: number
    price_text: string
    price_unit: string
    duration: number | null
    category: string
    background_image: string
    background_opacity: number
    disclaimer: string
    is_active: boolean
    sort_order: number
}

// ============================================================================
// Event Categories
// ============================================================================

export interface EventCategory {
    id: string
    name: string
    slug: string
    description?: string
    sort_order: number
    galleries_count?: number
    created_at: string
    updated_at: string
}

export interface EventCategoryFormData {
    name: string
    description?: string
    sort_order?: number
}

// ============================================================================
// Galleries
// ============================================================================

export type GalleryType = 'public' | 'private' | 'event'

export interface PackTier {
    min_quantity: number
    unit_price: number
}

export interface GalleryProductTypeConfig {
    product_type: ProductType
    is_enabled: boolean
    price: number | null
    pack_tiers?: PackTier[]
}

export interface EventGalleryFormData {
    title: string
    description: string
    event_date: string
    event_link: string
    event_category_id?: string
    parent_id?: string | null
    sort_order?: number
    product_types?: GalleryProductTypeConfig[]
}

export type DownloadStatus = 'none' | 'partial' | 'complete'

export interface AdminGallery {
    id: string
    title: string
    description?: string
    event_date?: string
    event_link?: string
    type: GalleryType
    client_id?: string
    client?: Client
    user?: Client
    assigned_email?: string
    access_token?: string
    share_code?: string
    event_category_id?: string
    event_category?: EventCategory
    parent_id?: string | null
    parent?: AdminGallery | null
    children?: AdminGallery[]
    children_count?: number
    sort_order?: number
    photos_count: number
    total_likes: number
    downloadable_count: number
    liked_photos_count: number
    views_count: number
    last_viewed_at?: string
    // Download tracking
    total_downloads_count: number
    downloaded_photos_count: number
    download_status: DownloadStatus
    photos?: AdminPhoto[]
    gallery_product_types?: GalleryProductTypeConfig[]
    created_at: string
    updated_at: string
}

export interface AdminPhoto {
    id: string
    gallery_id: string
    path: string
    file_path?: string
    display_url?: string
    preview_url?: string
    thumbnail_url?: string
    thumbnail_path?: string
    watermarked_path?: string
    original_filename?: string
    title?: string
    size?: number
    width?: number
    height?: number
    is_liked: boolean
    is_downloadable: boolean
    is_processed?: boolean
    downloads_count: number
    created_at: string
}

export interface GalleryFormData {
    title: string
    description: string
    client_id: string
    assigned_email: string
    product_types?: GalleryProductTypeConfig[]
}

// ============================================================================
// Booking Request (Public)
// ============================================================================

export interface BookingRequestData {
    name: string
    email: string
    phone?: string
    prestation_id: string
    date_preferences: string
    message?: string
}

// ============================================================================
// Gift Cards
// ============================================================================

export type GiftCardStatus = 'active' | 'used' | 'expired'

export interface AdminGiftCard {
    id: string
    code: string
    amount: number
    remaining_amount: number
    purchaser_email: string
    recipient_email?: string
    status: GiftCardStatus
    expires_at: string
    used_at?: string
    created_at: string
}

// ============================================================================
// Orders
// ============================================================================

export type OrderStatus = 'pending' | 'paid' | 'failed' | 'refunded' | 'expired'
export type DetailedOrderStatus =
    | 'checkout_initiated'
    | 'payment_in_progress'
    | 'payment_abandoned'
    | 'payment_failed'
    | 'paid'
    | 'to_ship'
    | 'shipped'
export type PrintStatus = 'pending' | 'shipped' | null
export type ProductType = 'digital' | 'print_10x15' | 'print_15x20'

export interface AdminOrderItem {
    id: string
    photo_id: string
    product_type: ProductType
    product_type_label: string
    is_print: boolean
    photo_title: string | null
    gallery_title: string | null
    price: number
    is_downloaded: boolean
    display_url?: string
    preview_url?: string
    thumbnail_url?: string
}

export interface AdminOrder {
    id: string
    order_number: string
    status: OrderStatus
    detailed_status: DetailedOrderStatus
    print_status: PrintStatus
    shipped_at: string | null
    subtotal: number
    total: number
    currency: string
    paid_at: string | null
    created_at: string
    items: AdminOrderItem[]
    has_prints: boolean
    customer_email: string
    customer_name: string
    user?: {
        id: string
        email: string
        name: string
    } | null
    payment?: {
        id: string
        provider: string
        status: string
        provider_payment_id: string
    } | null
}

// ============================================================================
// Notifications
// ============================================================================

export interface Notification {
    id: string
    type: string
    title: string
    message: string
    is_read: boolean
    data?: Record<string, unknown>
    created_at: string
}

// ============================================================================
// API Response Types
// ============================================================================

export interface AdminApiResponse<T> {
    data: T
    message?: string
    success: boolean
}

export interface AdminPaginatedResponse<T> {
    data: T[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
}

// ============================================================================
// Form / Table
// ============================================================================

export interface TableColumn<T = unknown> {
    key: keyof T | string
    label: string
    sortable?: boolean
    width?: string
    align?: 'left' | 'center' | 'right'
    render?: (value: unknown, row: T) => string
}

export interface SelectOption {
    value: string | number
    label: string
}
