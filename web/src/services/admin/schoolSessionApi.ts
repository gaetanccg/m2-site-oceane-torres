import { BaseAdminService } from './baseAdmin'
import type {
    AdminApiResponse,
    AdminPaginatedResponse,
    SchoolSession,
    SchoolSessionExport,
    SchoolSessionFormData,
    SchoolSessionGallery,
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

    async getSchoolSessionGalleries(id: string): Promise<AdminApiResponse<SchoolSessionGallery[]>> {
        return this.adminRequest<AdminApiResponse<SchoolSessionGallery[]>>(
            `/admin/school-sessions/${id}/galleries`,
        )
    }

    async uploadZipChunk(
        sessionId: string,
        chunk: Blob,
        chunkIndex: number,
        totalChunks: number,
        filename: string,
    ): Promise<{ success: boolean; chunk_index: number; received: number; total_chunks: number; upload_complete: boolean }> {
        const formData = new FormData()
        formData.append('chunk', chunk, 'chunk.zip')
        formData.append('chunk_index', String(chunkIndex))
        formData.append('total_chunks', String(totalChunks))
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

    async sendSchoolSessionEmails(
        sessionId: string,
        contacts: { gallery_id: string; email: string; recipient_name: string }[],
    ): Promise<{ success: boolean; sent: number; errors: string[]; message: string }> {
        return this.adminRequest(`/admin/school-sessions/${sessionId}/send-emails`, {
            method: 'POST',
            body: JSON.stringify({ contacts }),
        })
    }
}

export const schoolSessionApi = new SchoolSessionApiService()
