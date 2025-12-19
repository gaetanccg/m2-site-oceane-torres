/**
 * Types pour l'interface d'administration
 */

// ============================================================================
// User / Auth
// ============================================================================

export interface User {
    id: string
    email: string
    name: string
    phone?: string
    role: 'admin' | 'client'
    created_at: string
    updated_at: string
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
    name: string
    email: string
    phone?: string
    reservations_count?: number
    galleries_count?: number
    total_spent?: number
    created_at: string
    updated_at: string
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
    expires_at?: string
    cover_image?: string
    photos_count: number
    is_active: boolean
    created_at: string
    updated_at: string
}

export interface AdminPhoto {
    id: string
    gallery_id: string
    path: string
    thumbnail_path?: string
    watermarked_path?: string
    original_filename: string
    size: number
    width?: number
    height?: number
    likes_count: number
    created_at: string
}

export interface GalleryFormData {
    title: string
    description: string
    type: GalleryType
    client_id: string
    expires_at: string
    is_active: boolean
}

// ============================================================================
// Factures
// ============================================================================

export type FactureStatus = 'draft' | 'sent' | 'paid' | 'cancelled'

export interface Facture {
    id: string
    number: string
    client: Client
    reservation?: Reservation
    amount: number
    tax_amount: number
    total_amount: number
    status: FactureStatus
    due_date: string
    paid_at?: string
    pdf_path?: string
    created_at: string
    updated_at: string
}

export interface FactureFormData {
    client_id: string
    reservation_id: string
    amount: number
    tax_rate: number
    due_date: string
}

// ============================================================================
// Payments
// ============================================================================

export type PaymentStatus = 'pending' | 'completed' | 'failed' | 'refunded'
export type PaymentMethod = 'stripe' | 'paypal' | 'cash' | 'transfer'

export interface Payment {
    id: string
    client: Client
    reservation_id?: string
    facture_id?: string
    amount: number
    method: PaymentMethod
    status: PaymentStatus
    transaction_id?: string
    metadata?: Record<string, unknown>
    created_at: string
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
