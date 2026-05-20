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
            for (let batchIndex = 0; batchIndex < chunks.length; batchIndex += concurrentChunks) {
                if (this.isCancelled) break

                const parallelChunks = chunks.slice(batchIndex, batchIndex + concurrentChunks)

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

                // Sync pivot : le serveur renvoie déjà `completed` → on fait confiance au statut
                // (sinon le compteur completed reste à 0 et casse l'ETA et la barre de progression).
                responses.forEach((response) => {
                    if (response) {
                        response.uploads.forEach((upload) => {
                            const state = findFileState(fileStates, upload.original_filename)
                            if (!state) return

                            const previousStatus = state.status
                            state.id = upload.id
                            state.status = upload.status
                            state.errorMessage = upload.error_message
                            state.photoId = upload.photo_id

                            if (upload.status === 'completed') {
                                state.progress = 100
                                if (previousStatus !== 'completed') callbacks.onFileComplete?.(state)
                            } else if (upload.status === 'failed') {
                                state.progress = 100
                                if (previousStatus !== 'failed') callbacks.onFileError?.(state, upload.error_message || 'Unknown error')
                            } else {
                                // pending/processing : le polling finalisera.
                                state.progress = 50
                            }

                            uploadedIds.add(upload.id)
                        })
                    }
                })
                this.notifyProgress(fileStates, callbacks)
            }

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
        // Gros batches : on skip la liste per-file pendant le polling (payload). Les compteurs
        // agrégés suffisent pour la barre de progression ; le détail per-file arrive en un seul
        // fetch final quand `is_complete`.
        const LARGE_BATCH_THRESHOLD = 50
        const useLightPolling = fileStates.size > LARGE_BATCH_THRESHOLD

        return new Promise((resolve, reject) => {
            const poll = async () => {
                if (this.isCancelled) { resolve(); return }

                try {
                    const status = await this.fetchBatchStatus(batchId, !useLightPolling)

                    if (status.found) {
                        if (status.uploads) {
                            this.applyUploadDetails(fileStates, status.uploads, callbacks)
                        } else if (useLightPolling) {
                            this.nudgeProcessingStates(fileStates)
                        }

                        this.notifyProgress(fileStates, callbacks)

                        if (status.is_complete) {
                            if (useLightPolling) {
                                try {
                                    const finalStatus = await this.fetchBatchStatus(batchId, true)
                                    if (finalStatus.uploads) {
                                        this.applyUploadDetails(fileStates, finalStatus.uploads, callbacks)
                                        this.notifyProgress(fileStates, callbacks)
                                    }
                                } catch {
                                    // ignore
                                }
                            }
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

    private applyUploadDetails(
        fileStates: Map<string, FileUploadState>,
        uploads: NonNullable<BatchUploadStatus['uploads']>,
        callbacks: UploadCallbacks
    ): void {
        uploads.forEach((upload) => {
            const state = Array.from(fileStates.values()).find((s) => s.id === upload.id)
            if (!state) return

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
        })
    }

    private nudgeProcessingStates(fileStates: Map<string, FileUploadState>): void {
        for (const state of fileStates.values()) {
            if (state.status === 'uploading' || state.status === 'processing') {
                state.status = 'processing'
                if (state.progress < 90) state.progress = Math.min(90, state.progress + 2)
            }
        }
    }

    private async fetchBatchStatus(batchId: string, includeUploads = true): Promise<BatchUploadStatus> {
        const token = this.getToken()
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        }
        if (token) headers['Authorization'] = `Bearer ${token}`

        // Laravel `boolean` rule accepte "0"/"1"/true/false mais pas "true"/"false".
        const params = new URLSearchParams({ batch_id: batchId })
        if (!includeUploads) params.set('include_uploads', '0')

        const response = await fetch(
            `${this.baseUrl}/admin/upload-status?${params.toString()}`,
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
