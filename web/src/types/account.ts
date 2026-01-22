/**
 * Types pour l'espace client (Mon compte)
 */

import type { User } from './admin'

// ============================================================================
// Registration
// ============================================================================

export interface RegisterData {
    first_name: string
    last_name: string
    email: string
    phone?: string
    password: string
    password_confirmation: string
}

// ============================================================================
// Account Gallery
// ============================================================================

export interface AccountPhoto {
    id: string
    display_url?: string
    file_path?: string
    thumbnail_path?: string
}

export interface AccountGallery {
    id: string
    title: string
    description?: string
    share_code: string
    access_token: string
    photos_count: number
    photos?: AccountPhoto[]
    created_at: string
}

// ============================================================================
// Account Reservation
// ============================================================================

export type AccountReservationStatus = 'pending' | 'confirmed' | 'cancelled' | 'completed'

export interface AccountPrestation {
    id: string
    title: string
    icon?: string
}

export interface AccountReservation {
    id: string
    prestation?: AccountPrestation
    date?: string
    status: AccountReservationStatus
    message?: string
    date_preferences?: string
    created_at: string
}

// ============================================================================
// Account Dashboard
// ============================================================================

export interface AccountDashboard {
    user: User
    galleries: AccountGallery[]
    reservations: AccountReservation[]
}

// ============================================================================
// Status Labels
// ============================================================================

export const RESERVATION_STATUS_LABELS: Record<AccountReservationStatus, string> = {
    pending: 'En attente',
    confirmed: 'Confirmee',
    cancelled: 'Annulee',
    completed: 'Terminee',
}

export const RESERVATION_STATUS_COLORS: Record<AccountReservationStatus, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    confirmed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    completed: 'bg-gray-100 text-gray-800',
}
