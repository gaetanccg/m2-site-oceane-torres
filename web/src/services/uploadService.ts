/**
 * Chunked Upload Service
 * Orchestrates large batch uploads with progress tracking, retry, and polling.
 *
 * Internal modules:
 *   upload/uploadUtils.ts   — pure functions (buildChunks, buildProgress)
 *   upload/chunkUploader.ts — XHR upload with progress and retry
 */

import { API_CONFIG, UPLOAD_CONFIG } from '@/config/constants'
import { buildChunks, buildProgress, findFileState, generateBatchId } from './upload/uploadUtils'
import { uploadChunkWithRetry } from './upload/chunkUploader'
import type {
    BatchUploadStatus,
    FileUploadState,
    UploadProgress,
    UploadCallbacks,
    ChunkUploadOptions,
} from '@/types/upload'

export class ChunkedUploadService {
    private baseUrl: string
    private pollingInterval: ReturnType<typeof setInterval> | null = null
    private isCancelled = false
    private activeXHRs: XMLHttpRequest[] = []

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

    private get uploaderConfig() {
        return {
            baseUrl: this.baseUrl,
            getToken: () => this.getToken(),
            getXsrfToken: () => this.getXsrfToken(),
        }
    }

    /**
     * Upload files in chunks with real progress tracking and parallel uploads
     */
    async uploadChunked(
        galleryId: string,
        files: File[],
        callbacks: UploadCallbacks = {},
        options: ChunkUploadOptions = {}
    ): Promise<UploadProgress> {
        const {
            chunkSize = UPLOAD_CONFIG.chunkSize,
            maxChunkBytes = UPLOAD_CONFIG.maxChunkBytes,
            timeout = UPLOAD_CONFIG.timeout,
            pollInterval = UPLOAD_CONFIG.pollInterval,
            maxRetries = UPLOAD_CONFIG.maxRetries,
            endpoint = 'events',
        } = options
        const concurrentChunks = UPLOAD_CONFIG.concurrentChunks || 3
        const endpointPath = endpoint === 'galleries' ? 'galleries' : 'events'

        this.isCancelled = false
        this.activeXHRs = []

        const batchId = generateBatchId()

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
                size: file.size,
                uploadedBytes: 0,
            })
        })

        const chunks = buildChunks(files, chunkSize, maxChunkBytes)
        const uploadedIds: Set<string> = new Set()

        try {
            // Upload chunks in parallel batches
            for (let batchIndex = 0; batchIndex < chunks.length; batchIndex += concurrentChunks) {
                if (this.isCancelled) break

                const parallelChunks = chunks.slice(batchIndex, batchIndex + concurrentChunks)

                // Mark files as uploading
                parallelChunks.forEach((chunk) => {
                    chunk.forEach((file) => {
                        const state = findFileState(fileStates, file.name)
                        if (state) {
                            state.status = 'uploading'
                            state.progress = 0
                        }
                    })
                })
                this.notifyProgress(fileStates, callbacks)

                // Upload all chunks in parallel
                const uploadPromises = parallelChunks.map((chunk) =>
                    uploadChunkWithRetry(
                        this.uploaderConfig,
                        galleryId, chunk, batchId, timeout, endpointPath, maxRetries,
                        (chunkProgress) => {
                            chunk.forEach((file) => {
                                const state = findFileState(fileStates, file.name)
                                if (state && state.status === 'uploading') {
                                    state.progress = Math.round(chunkProgress * 0.5)
                                    state.uploadedBytes = Math.round((chunkProgress / 100) * file.size)
                                }
                            })
                            this.notifyProgress(fileStates, callbacks)
                        },
                        this.activeXHRs,
                        () => this.isCancelled
                    ).catch((error) => {
                        chunk.forEach((file) => {
                            const state = findFileState(fileStates, file.name)
                            if (state) {
                                state.status = 'failed'
                                state.progress = 100
                                state.errorMessage = error.message
                            }
                        })
                        this.notifyProgress(fileStates, callbacks)
                        return null
                    })
                )

                const responses = await Promise.all(uploadPromises)

                // Update file states with server IDs
                responses.forEach((response) => {
                    if (response) {
                        response.uploads.forEach((upload) => {
                            const state = findFileState(fileStates, upload.original_filename)
                            if (state) {
                                state.id = upload.id
                                state.status = upload.status === 'failed' ? 'failed' : 'processing'
                                state.progress = upload.status === 'failed' ? 100 : 50
                                state.errorMessage = upload.error_message
                                uploadedIds.add(upload.id)
                            }
                        })
                    }
                })
                this.notifyProgress(fileStates, callbacks)
            }

            // Poll for processing completion
            if (!this.isCancelled && uploadedIds.size > 0) {
                await this.pollUntilComplete(batchId, fileStates, callbacks, pollInterval)
            }

            return buildProgress(fileStates)
        } catch (error) {
            if (this.isCancelled) throw new Error('Upload cancelled')
            callbacks.onError?.(error as Error)
            throw error
        } finally {
            this.cleanup()
        }
    }

    cancel(): void {
        this.isCancelled = true
        this.activeXHRs.forEach(xhr => { try { xhr.abort() } catch { /* ignore */ } })
        this.cleanup()
    }

    private async pollUntilComplete(
        batchId: string,
        fileStates: Map<string, FileUploadState>,
        callbacks: UploadCallbacks,
        pollInterval: number
    ): Promise<void> {
        return new Promise((resolve, reject) => {
            const poll = async () => {
                if (this.isCancelled) { resolve(); return }

                try {
                    const status = await this.fetchBatchStatus(batchId)

                    if (status.found && status.uploads) {
                        status.uploads.forEach((upload) => {
                            const state = Array.from(fileStates.values()).find((s) => s.id === upload.id)
                            if (state) {
                                const previousStatus = state.status
                                state.status = upload.status
                                state.errorMessage = upload.error_message
                                state.photoId = upload.photo_id

                                if (upload.status === 'completed') {
                                    state.progress = 100
                                    if (previousStatus !== 'completed') callbacks.onFileComplete?.(state)
                                } else if (upload.status === 'failed') {
                                    state.progress = 100
                                    if (previousStatus !== 'failed') callbacks.onFileError?.(state, upload.error_message || 'Unknown error')
                                } else if (upload.status === 'processing') {
                                    if (state.progress < 90) state.progress = Math.min(90, state.progress + 5)
                                }
                            }
                        })

                        this.notifyProgress(fileStates, callbacks)

                        if (status.is_complete) {
                            callbacks.onBatchComplete?.(buildProgress(fileStates))
                            resolve()
                            return
                        }
                    }

                    this.pollingInterval = setTimeout(poll, pollInterval)
                } catch (error) {
                    if (!this.isCancelled) reject(error)
                    else resolve()
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
        if (token) headers['Authorization'] = `Bearer ${token}`

        const response = await fetch(
            `${this.baseUrl}/admin/upload-status?batch_id=${encodeURIComponent(batchId)}`,
            { headers, credentials: 'include' }
        )

        if (!response.ok) throw new Error(`Status check failed: ${response.status}`)
        return response.json()
    }

    private notifyProgress(fileStates: Map<string, FileUploadState>, callbacks: UploadCallbacks): void {
        callbacks.onProgress?.(buildProgress(fileStates))
    }

    private cleanup(): void {
        if (this.pollingInterval) {
            clearTimeout(this.pollingInterval)
            this.pollingInterval = null
        }
        this.activeXHRs = []
    }
}

export const uploadService = new ChunkedUploadService()
