/**
 * Chunked Upload Service
 * Handles large batch uploads with progress tracking
 */

import { API_CONFIG, UPLOAD_CONFIG } from '@/config/constants'
import type {
    BatchUploadStatus,
    ChunkUploadResponse,
    FileUploadState,
    UploadProgress,
    UploadCallbacks,
    ChunkUploadOptions,
} from '@/types/upload'

export class ChunkedUploadService {
    private baseUrl: string
    private abortController: AbortController | null = null
    private pollingInterval: ReturnType<typeof setInterval> | null = null
    private isCancelled = false

    constructor() {
        this.baseUrl = API_CONFIG.baseUrl
    }

    private getToken(): string | null {
        return localStorage.getItem('auth_token')
    }

    private getXsrfToken(): string | null {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        return match ? decodeURIComponent(match[1]) : null
    }

    /**
     * Upload files in chunks with progress tracking
     */
    async uploadChunked(
        galleryId: string,
        files: File[],
        callbacks: UploadCallbacks = {},
        options: ChunkUploadOptions = {}
    ): Promise<UploadProgress> {
        const {
            chunkSize = UPLOAD_CONFIG.chunkSize,
            timeout = UPLOAD_CONFIG.timeout,
            pollInterval = UPLOAD_CONFIG.pollInterval,
        } = options

        this.isCancelled = false
        this.abortController = new AbortController()

        // Generate batch ID
        const batchId = this.generateBatchId()

        // Initialize file states
        const fileStates: Map<string, FileUploadState> = new Map()
        files.forEach((file, index) => {
            const id = `local_${index}`
            fileStates.set(id, {
                id,
                file,
                originalFilename: file.name,
                status: 'pending',
                progress: 0,
            })
        })

        // Split files into chunks
        const chunks: File[][] = []
        for (let i = 0; i < files.length; i += chunkSize) {
            chunks.push(files.slice(i, i + chunkSize))
        }

        const uploadedIds: Set<string> = new Set()

        try {
            // Upload chunks sequentially
            for (let chunkIndex = 0; chunkIndex < chunks.length; chunkIndex++) {
                if (this.isCancelled) {
                    break
                }

                const chunk = chunks[chunkIndex]

                // Mark chunk files as uploading
                chunk.forEach((file) => {
                    const state = this.findFileState(fileStates, file.name)
                    if (state) {
                        state.status = 'uploading'
                        state.progress = 10
                    }
                })

                this.notifyProgress(fileStates, callbacks)

                // Upload chunk
                const response = await this.uploadChunk(galleryId, chunk, batchId, timeout)

                // Update file states with server IDs
                response.uploads.forEach((upload) => {
                    const state = this.findFileState(fileStates, upload.original_filename)
                    if (state) {
                        state.id = upload.id
                        state.status = upload.status === 'failed' ? 'failed' : 'processing'
                        state.progress = upload.status === 'failed' ? 100 : 50
                        state.errorMessage = upload.error_message
                        uploadedIds.add(upload.id)
                    }
                })

                this.notifyProgress(fileStates, callbacks)
            }

            // Start polling for status updates
            if (!this.isCancelled && uploadedIds.size > 0) {
                await this.pollUntilComplete(batchId, fileStates, callbacks, pollInterval)
            }

            return this.buildProgress(fileStates)
        } catch (error) {
            if (this.isCancelled) {
                throw new Error('Upload cancelled')
            }
            callbacks.onError?.(error as Error)
            throw error
        } finally {
            this.cleanup()
        }
    }

    /**
     * Cancel ongoing upload
     */
    cancel(): void {
        this.isCancelled = true
        this.abortController?.abort()
        this.cleanup()
    }

    private async uploadChunk(
        galleryId: string,
        files: File[],
        batchId: string,
        timeout: number
    ): Promise<ChunkUploadResponse> {
        const formData = new FormData()
        files.forEach((file) => formData.append('photos[]', file))
        formData.append('batch_id', batchId)

        const token = this.getToken()
        const xsrfToken = this.getXsrfToken()

        const headers: Record<string, string> = {
            Accept: 'application/json',
        }
        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }
        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] = xsrfToken
        }

        const controller = new AbortController()
        const timeoutId = setTimeout(() => controller.abort(), timeout)

        try {
            const response = await fetch(
                `${this.baseUrl}/admin/events/${galleryId}/photos/async`,
                {
                    method: 'POST',
                    headers,
                    credentials: 'include',
                    body: formData,
                    signal: this.abortController?.signal || controller.signal,
                }
            )

            clearTimeout(timeoutId)

            if (!response.ok) {
                throw new Error(`Upload failed: ${response.status}`)
            }

            return await response.json()
        } catch (error) {
            clearTimeout(timeoutId)
            throw error
        }
    }

    private async pollUntilComplete(
        batchId: string,
        fileStates: Map<string, FileUploadState>,
        callbacks: UploadCallbacks,
        pollInterval: number
    ): Promise<void> {
        return new Promise((resolve, reject) => {
            const poll = async () => {
                if (this.isCancelled) {
                    resolve()
                    return
                }

                try {
                    const status = await this.fetchBatchStatus(batchId)

                    if (status.found && status.uploads) {
                        // Update file states from server
                        status.uploads.forEach((upload) => {
                            const state = Array.from(fileStates.values()).find(
                                (s) => s.id === upload.id
                            )
                            if (state) {
                                const previousStatus = state.status
                                state.status = upload.status
                                state.errorMessage = upload.error_message
                                state.photoId = upload.photo_id

                                if (upload.status === 'completed') {
                                    state.progress = 100
                                    if (previousStatus !== 'completed') {
                                        callbacks.onFileComplete?.(state)
                                    }
                                } else if (upload.status === 'failed') {
                                    state.progress = 100
                                    if (previousStatus !== 'failed') {
                                        callbacks.onFileError?.(state, upload.error_message || 'Unknown error')
                                    }
                                } else if (upload.status === 'processing') {
                                    state.progress = 75
                                }
                            }
                        })

                        this.notifyProgress(fileStates, callbacks)

                        if (status.is_complete) {
                            const progress = this.buildProgress(fileStates)
                            callbacks.onBatchComplete?.(progress)
                            resolve()
                            return
                        }
                    }

                    // Continue polling
                    this.pollingInterval = setTimeout(poll, pollInterval)
                } catch (error) {
                    if (!this.isCancelled) {
                        reject(error)
                    } else {
                        resolve()
                    }
                }
            }

            poll()
        })
    }

    private async fetchBatchStatus(batchId: string): Promise<BatchUploadStatus> {
        const token = this.getToken()
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        }
        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }

        const response = await fetch(
            `${this.baseUrl}/admin/upload-status?batch_id=${encodeURIComponent(batchId)}`,
            {
                headers,
                credentials: 'include',
            }
        )

        if (!response.ok) {
            throw new Error(`Status check failed: ${response.status}`)
        }

        return response.json()
    }

    private findFileState(
        fileStates: Map<string, FileUploadState>,
        filename: string
    ): FileUploadState | undefined {
        return Array.from(fileStates.values()).find(
            (state) => state.originalFilename === filename
        )
    }

    private buildProgress(fileStates: Map<string, FileUploadState>): UploadProgress {
        const files = Array.from(fileStates.values())
        const total = files.length
        const completed = files.filter((f) => f.status === 'completed').length
        const failed = files.filter((f) => f.status === 'failed').length
        const processing = files.filter((f) =>
            ['pending', 'uploading', 'processing'].includes(f.status)
        ).length

        return {
            total,
            uploaded: total - processing,
            completed,
            failed,
            processing,
            percentage: total > 0 ? Math.round(((completed + failed) / total) * 100) : 0,
            isComplete: processing === 0,
            files,
        }
    }

    private notifyProgress(
        fileStates: Map<string, FileUploadState>,
        callbacks: UploadCallbacks
    ): void {
        const progress = this.buildProgress(fileStates)
        callbacks.onProgress?.(progress)
    }

    private generateBatchId(): string {
        return `batch_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`
    }

    private cleanup(): void {
        if (this.pollingInterval) {
            clearTimeout(this.pollingInterval)
            this.pollingInterval = null
        }
        this.abortController = null
    }
}

export const uploadService = new ChunkedUploadService()
