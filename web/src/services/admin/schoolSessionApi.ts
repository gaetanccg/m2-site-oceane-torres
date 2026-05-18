import { BaseAdminService } from './baseAdmin'
import type {
    AdminApiResponse,
    AdminOrder,
    AdminPaginatedResponse,
    SchoolSession,
    SchoolSessionExport,
    SchoolSessionFormData,
    SchoolSessionGallery,
    SchoolSessionUpdateData,
} from '@/types/admin'

class SchoolSessionApiService extends BaseAdminService {
    async getSchoolSessions(page = 1): Promise<AdminPaginatedResponse<SchoolSession>> {
        return this.adminRequest<AdminPaginatedResponse<SchoolSession>>(
            `/admin/school-sessions?page=${page}`,
        )
    }

    async getSchoolSession(id: string): Promise<AdminApiResponse<SchoolSession>> {
        return this.adminRequest<AdminApiResponse<SchoolSession>>(
            `/admin/school-sessions/${id}`,
        )
    }

    async createSchoolSession(data: SchoolSessionFormData): Promise<AdminApiResponse<SchoolSession>> {
        return this.adminRequest<AdminApiResponse<SchoolSession>>('/admin/school-sessions', {
            method: 'POST',
            body: JSON.stringify(data),
        })
    }

    async updateSchoolSession(id: string, data: SchoolSessionUpdateData): Promise<AdminApiResponse<SchoolSession>> {
        return this.adminRequest<AdminApiResponse<SchoolSession>>(`/admin/school-sessions/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    }

    async getSchoolSessionGalleries(id: string): Promise<AdminApiResponse<SchoolSessionGallery[]>> {
        return this.adminRequest<AdminApiResponse<SchoolSessionGallery[]>>(
            `/admin/school-sessions/${id}/galleries`,
        )
    }

    async getSchoolSessionOrders(id: string): Promise<{ success: boolean; orders: AdminOrder[] }> {
        return this.adminRequest<{ success: boolean; orders: AdminOrder[] }>(
            `/admin/school-sessions/${id}/orders`,
        )
    }

    async uploadZipChunk(
        sessionId: string,
        chunk: Blob,
        chunkIndex: number,
        totalChunks: number,
        filename: string,
        offset: number,
    ): Promise<{ success: boolean; chunk_index: number; received: number; total_chunks: number; upload_complete: boolean }> {
        const MAX_RETRIES = 3
        const BASE_DELAY_MS = 1000

        let lastError: Error | null = null

        for (let attempt = 0; attempt <= MAX_RETRIES; attempt++) {
            try {
                return await this.sendChunk(sessionId, chunk, chunkIndex, totalChunks, filename, offset)
            } catch (e) {
                lastError = e instanceof Error ? e : new Error(String(e))
                if (attempt < MAX_RETRIES) {
                    await new Promise(resolve => setTimeout(resolve, BASE_DELAY_MS * 2 ** attempt))
                }
            }
        }

        throw lastError ?? new Error(`Echec upload chunk ${chunkIndex}`)
    }

    private async sendChunk(
        sessionId: string,
        chunk: Blob,
        chunkIndex: number,
        totalChunks: number,
        filename: string,
        offset: number,
    ): Promise<{ success: boolean; chunk_index: number; received: number; total_chunks: number; upload_complete: boolean }> {
        const formData = new FormData()
        formData.append('chunk', chunk, 'chunk.zip')
        formData.append('chunk_index', String(chunkIndex))
        formData.append('total_chunks', String(totalChunks))
        formData.append('offset', String(offset))
        formData.append('filename', filename)

        const headers: Record<string, string> = {
            'Accept': 'application/json',
            ...this.authHeaders(),
        }

        const response = await fetch(`${this.baseUrl}/admin/school-sessions/${sessionId}/upload`, {
            method: 'PUT',
            headers,
            credentials: 'include',
            body: formData,
        })

        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: `HTTP ${response.status}` }))
            throw new Error(error.message || `Erreur upload chunk ${chunkIndex}`)
        }

        return response.json()
    }

    async processSchoolSession(id: string): Promise<AdminApiResponse<SchoolSession>> {
        return this.adminRequest<AdminApiResponse<SchoolSession>>(
            `/admin/school-sessions/${id}/process`,
            { method: 'POST' },
        )
    }

    async retryFailedPhotos(id: string): Promise<{
        success: boolean
        message: string
        redispatched: number
        skipped: number
    }> {
        return this.adminRequest(`/admin/school-sessions/${id}/retry-failed-photos`, {
            method: 'POST',
        })
    }

    async deleteSchoolSession(id: string): Promise<AdminApiResponse<null>> {
        return this.adminRequest<AdminApiResponse<null>>(
            `/admin/school-sessions/${id}`,
            { method: 'DELETE' },
        )
    }

    async closeSchoolSession(id: string): Promise<AdminApiResponse<SchoolSession>> {
        return this.adminRequest<AdminApiResponse<SchoolSession>>(
            `/admin/school-sessions/${id}/close`,
            { method: 'POST' },
        )
    }

    async reopenSchoolSession(id: string): Promise<AdminApiResponse<SchoolSession>> {
        return this.adminRequest<AdminApiResponse<SchoolSession>>(
            `/admin/school-sessions/${id}/reopen`,
            { method: 'POST' },
        )
    }

    async createSchoolSessionExport(
        sessionId: string,
        includeDigital: boolean,
    ): Promise<AdminApiResponse<SchoolSessionExport>> {
        return this.adminRequest<AdminApiResponse<SchoolSessionExport>>(
            `/admin/school-sessions/${sessionId}/exports`,
            {
                method: 'POST',
                body: JSON.stringify({ include_digital: includeDigital }),
            },
        )
    }

    async getLatestSchoolSessionExport(
        sessionId: string,
    ): Promise<AdminApiResponse<SchoolSessionExport | null>> {
        return this.adminRequest<AdminApiResponse<SchoolSessionExport | null>>(
            `/admin/school-sessions/${sessionId}/exports/latest`,
        )
    }

    getSchoolSessionExportDownloadUrl(exportId: string): string {
        return `${this.baseUrl}/admin/school-session-exports/${exportId}/download`
    }

    async sendSchoolSessionMessages(
        sessionId: string,
        channel: 'email' | 'sms',
        contacts: { gallery_id: string; recipient_name: string; email?: string; phone?: string }[],
    ): Promise<{ success: boolean; sent: number; errors: string[]; message: string }> {
        return this.adminRequest(`/admin/school-sessions/${sessionId}/send-messages`, {
            method: 'POST',
            body: JSON.stringify({ channel, contacts }),
        })
    }
}

export const schoolSessionApi = new SchoolSessionApiService()
