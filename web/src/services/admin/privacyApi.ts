import { BaseAdminService } from './baseAdmin'
import type { PrivacyAuditEntry, PrivacyErasurePreview, PrivacyExportInfo, PrivacySearchResult } from '@/types/admin'

class PrivacyApiService extends BaseAdminService {
    /** Recherche « personne concernée » par email / téléphone / n° de commande. */
    async search(type: string, value: string): Promise<PrivacySearchResult> {
        const params = new URLSearchParams({ type, value })
        return this.adminRequest(`/admin/privacy/search?${params.toString()}`)
    }

    /** Lance un export global (toutes les données + PDF factures) en asynchrone. */
    async exportAll(): Promise<{ success: boolean; export: PrivacyExportInfo }> {
        return this.adminRequest('/admin/privacy/export-all', { method: 'POST' })
    }

    /** Lance un export ciblé sur une personne (email / téléphone / n° de commande). */
    async exportSubject(type: string, value: string): Promise<{ success: boolean; export: PrivacyExportInfo }> {
        return this.adminRequest('/admin/privacy/export-subject', {
            method: 'POST',
            body: JSON.stringify({ type, value }),
        })
    }

    /** Statut d'un export (polling). */
    async getExport(id: string): Promise<{ success: boolean; export: PrivacyExportInfo }> {
        return this.adminRequest(`/admin/privacy/exports/${id}`)
    }

    /** URL de téléchargement du ZIP (navigation directe, auth par cookie de session). */
    getExportDownloadUrl(id: string): string {
        return `${this.baseUrl}/admin/privacy/exports/${id}/download`
    }

    /** Aperçu d'effacement : ce qui sera anonymisé / supprimé / conservé. */
    async erasurePreview(type: string, value: string): Promise<PrivacyErasurePreview> {
        const params = new URLSearchParams({ type, value })
        return this.adminRequest(`/admin/privacy/erasure/preview?${params.toString()}`)
    }

    /** Exécute l'effacement (confirmation tapée = valeur ciblée). */
    async erase(type: string, value: string, confirm: string): Promise<{ success: boolean; result: Record<string, unknown> }> {
        return this.adminRequest('/admin/privacy/erasure', {
            method: 'POST',
            body: JSON.stringify({ type, value, confirm }),
        })
    }

    /** Journal d'audit RGPD (paginé). */
    async getAudit(page = 1, perPage = 20): Promise<{
        success: boolean
        logs: PrivacyAuditEntry[]
        pagination: { current_page: number; last_page: number; per_page: number; total: number }
    }> {
        const params = new URLSearchParams({ page: page.toString(), per_page: perPage.toString() })
        return this.adminRequest(`/admin/privacy/audit?${params.toString()}`)
    }
}

export const privacyApi = new PrivacyApiService()
