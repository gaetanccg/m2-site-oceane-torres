import { BaseAdminService } from './baseAdmin'

class LogApiService extends BaseAdminService {
    /** Tail des logs applicatifs, filtrable par niveau et recherche texte. */
    async getLogs(level?: string, search?: string, limit = 300): Promise<{
        success: boolean
        lines: string[]
        size: number
        truncated: boolean
    }> {
        const params = new URLSearchParams({ limit: limit.toString() })
        if (level) params.append('level', level)
        if (search) params.append('search', search)
        return this.adminRequest(`/admin/logs?${params.toString()}`)
    }

    /** URL de téléchargement du fichier de log brut (navigation directe). */
    getDownloadUrl(): string {
        return `${this.baseUrl}/admin/logs/download`
    }

    /** Vide le fichier de log applicatif (laravel.log). */
    async clearLogs(): Promise<{ success: boolean; message: string }> {
        return this.adminRequest('/admin/logs', { method: 'DELETE' })
    }
}

export const logApi = new LogApiService()
