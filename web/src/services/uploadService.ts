/**
 * Chunked Upload Service
 * Handles large batch uploads with real progress tracking
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

        // Generate batch ID
        const batchId = this.generateBatchId()

        // Initialize file states with size info for progress tracking
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

        // Split files into chunks (respecting both file count and byte size limits)
        const chunks = this.buildChunks(files, chunkSize, maxChunkBytes)

        const uploadedIds: Set<string> = new Set()

        try {
            // Upload chunks in parallel batches
            for (let batchIndex = 0; batchIndex < chunks.length; batchIndex += concurrentChunks) {
                if (this.isCancelled) {
                    break
                }

                // Get the batch of chunks to upload in parallel
                const parallelChunks = chunks.slice(batchIndex, batchIndex + concurrentChunks)

                // Mark all files in parallel chunks as uploading
                parallelChunks.forEach((chunk) => {
                    chunk.forEach((file) => {
                        const state = this.findFileState(fileStates, file.name)
                        if (state) {
                            state.status = 'uploading'
                            state.progress = 0
                        }
                    })
                })

                this.notifyProgress(fileStates, callbacks)

                // Upload all chunks in parallel with progress tracking and retry
                const uploadPromises = parallelChunks.map((chunk, _chunkIndexInBatch) =>
                    this.uploadChunkWithRetry(
                        galleryId,
                        chunk,
                        batchId,
                        timeout,
                        endpointPath,
                        maxRetries,
                        (chunkProgress) => {
                            // Update individual file progress based on chunk upload progress
                            chunk.forEach((file) => {
                                const state = this.findFileState(fileStates, file.name)
                                if (state && state.status === 'uploading') {
                                    // Upload phase is 0-50%, processing is 50-100%
                                    state.progress = Math.round(chunkProgress * 0.5)
                                    state.uploadedBytes = Math.round((chunkProgress / 100) * file.size)
                                }
                            })
                            this.notifyProgress(fileStates, callbacks)
                        }
                    ).catch((error) => {
                        // Mark files as failed on chunk error (after all retries exhausted)
                        chunk.forEach((file) => {
                            const state = this.findFileState(fileStates, file.name)
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
                            const state = this.findFileState(fileStates, upload.original_filename)
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

            // Start polling for processing status updates
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
        // Abort all active XHR requests
        this.activeXHRs.forEach(xhr => {
            try {
                xhr.abort()
            } catch (_e) {
                // Ignore abort errors
            }
        })
        this.cleanup()
    }

    /**
     * Build chunks respecting both file count and byte size limits.
     * A single file that exceeds maxChunkBytes gets its own chunk.
     */
    private buildChunks(files: File[], maxFiles: number, maxBytes: number): File[][] {
        const chunks: File[][] = []
        let currentChunk: File[] = []
        let currentBytes = 0

        for (const file of files) {
            const wouldExceedBytes = currentBytes + file.size > maxBytes
            const wouldExceedCount = currentChunk.length >= maxFiles

            if (currentChunk.length > 0 && (wouldExceedBytes || wouldExceedCount)) {
                chunks.push(currentChunk)
                currentChunk = []
                currentBytes = 0
            }

            currentChunk.push(file)
            currentBytes += file.size
        }

        if (currentChunk.length > 0) {
            chunks.push(currentChunk)
        }

        return chunks
    }

    /**
     * Upload a chunk with retry on timeout/network errors
     */
    private async uploadChunkWithRetry(
        galleryId: string,
        files: File[],
        batchId: string,
        timeout: number,
        endpointPath: string,
        maxRetries: number,
        onProgress: (percent: number) => void
    ): Promise<ChunkUploadResponse> {
        let lastError: Error | null = null

        for (let attempt = 0; attempt <= maxRetries; attempt++) {
            try {
                return await this.uploadChunkWithProgress(
                    galleryId, files, batchId, timeout, endpointPath, onProgress
                )
            } catch (error) {
                lastError = error as Error
                const isRetryable = lastError.message === 'Upload timeout'
                    || lastError.message === 'Network error'

                if (!isRetryable || attempt >= maxRetries || this.isCancelled) {
                    throw lastError
                }

                // Reset progress before retry
                onProgress(0)
            }
        }

        throw lastError!
    }

    /**
     * Upload a chunk with real progress tracking using XMLHttpRequest
     */
    private uploadChunkWithProgress(
        galleryId: string,
        files: File[],
        batchId: string,
        timeout: number,
        endpointPath: string,
        onProgress: (percent: number) => void
    ): Promise<ChunkUploadResponse> {
        return new Promise((resolve, reject) => {
            const formData = new FormData()
            files.forEach((file) => formData.append('photos[]', file))
            formData.append('batch_id', batchId)

            const xhr = new XMLHttpRequest()
            this.activeXHRs.push(xhr)

            // Set timeout
            xhr.timeout = timeout

            // Track upload progress
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const percent = Math.round((event.loaded / event.total) * 100)
                    onProgress(percent)
                }
            }

            xhr.onload = () => {
                this.removeXHR(xhr)
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText)
                        resolve(response)
                    } catch (_e) {
                        reject(new Error('Invalid JSON response'))
                    }
                } else {
                    reject(new Error(`Upload failed: ${xhr.status}`))
                }
            }

            xhr.onerror = () => {
                this.removeXHR(xhr)
                reject(new Error('Network error'))
            }

            xhr.ontimeout = () => {
                this.removeXHR(xhr)
                reject(new Error('Upload timeout'))
            }

            xhr.onabort = () => {
                this.removeXHR(xhr)
                reject(new Error('Upload cancelled'))
            }

            // Open connection
            xhr.open('POST', `${this.baseUrl}/admin/${endpointPath}/${galleryId}/photos/async`)

            // Set headers
            const token = this.getToken()
            const xsrfToken = this.getXsrfToken()

            xhr.setRequestHeader('Accept', 'application/json')
            if (token) {
                xhr.setRequestHeader('Authorization', `Bearer ${token}`)
            }
            if (xsrfToken) {
                xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken)
            }
            xhr.withCredentials = true

            // Send
            xhr.send(formData)
        })
    }

    private removeXHR(xhr: XMLHttpRequest): void {
        const index = this.activeXHRs.indexOf(xhr)
        if (index > -1) {
            this.activeXHRs.splice(index, 1)
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
                                    // Processing phase: 50-90% (estimate progress over time)
                                    if (state.progress < 90) {
                                        state.progress = Math.min(90, state.progress + 5)
                                    }
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

    /**
     * Build progress with real percentage based on individual file progress
     */
    private buildProgress(fileStates: Map<string, FileUploadState>): UploadProgress {
        const files = Array.from(fileStates.values())
        const total = files.length
        const completed = files.filter((f) => f.status === 'completed').length
        const failed = files.filter((f) => f.status === 'failed').length
        const processing = files.filter((f) =>
            ['pending', 'uploading', 'processing'].includes(f.status)
        ).length

        // Calculate real percentage based on individual file progress
        const totalProgress = files.reduce((sum, file) => sum + (file.progress || 0), 0)
        const percentage = total > 0 ? Math.round(totalProgress / total) : 0

        return {
            total,
            uploaded: total - processing,
            completed,
            failed,
            processing,
            percentage,
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
        this.activeXHRs = []
    }
}

export const uploadService = new ChunkedUploadService()
