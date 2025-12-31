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
    client: Client
    prestation: AdminPrestation
    date: string
    time: string
    status: ReservationStatus
    notes?: string
    message?: string
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

export interface Client {
    id: string
    first_name: string
    last_name: string
    email: string
    phone?: string
    reservations_count?: number
    galleries_count?: number
    total_spent?: number
    created_at: string
    updated_at: string
}

// Helper pour obtenir le nom complet du client
export function getClientFullName(client: Client): string {
    return `${client.first_name} ${client.last_name}`
}

// ============================================================================
// Prestations
// ============================================================================

export interface AdminPrestation {
    id: string
    title: string
    description: string
    price: number
    duration: number // en minutes
    category: string
    is_active: boolean
    sort_order: number
    created_at: string
    updated_at: string
}

export interface PrestationFormData {
    title: string
    description: string
    price: number
    duration: number
    category: string
    is_active: boolean
}

// ============================================================================
// Galleries
// ============================================================================

export type GalleryType = 'public' | 'private'

export interface AdminGallery {
    id: string
    title: string
    description?: string
    type: GalleryType
    client_id?: string
    client?: Client
    access_token?: string
    share_code?: string
    expires_at?: string
    cover_image?: string
    photos_count: number
    total_likes: number
    downloadable_count: number
    liked_photos_count: number
    views_count: number
    last_viewed_at?: string
    is_active: boolean
    photos?: AdminPhoto[]
    created_at: string
    updated_at: string
}

export interface AdminPhoto {
    id: string
    gallery_id: string
    path: string
    file_path?: string
    thumbnail_path?: string
    watermarked_path?: string
    original_filename?: string
    title?: string
    size?: number
    width?: number
    height?: number
    likes_count: number
    is_downloadable: boolean
    created_at: string
}

export interface GalleryFormData {
    title: string
    description: string
    client_id: string
    expires_at: string
    is_active: boolean
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
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
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
