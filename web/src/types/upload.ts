/**
 * Types for chunked upload system
 */

export type UploadStatus = 'pending' | 'uploading' | 'processing' | 'completed' | 'failed'

export interface FileUploadState {
    id: string
    file: File
    originalFilename: string
    status: UploadStatus
    progress: number
    errorMessage?: string
    photoId?: string
    size?: number        // File size in bytes
    uploadedBytes?: number // Bytes uploaded so far
}

export interface BatchUploadStatus {
    batch_id: string
    found: boolean
    total?: number
    completed?: number
    failed?: number
    processing?: number
    progress?: number
    is_complete?: boolean
    uploads?: Array<{
        id: string
        original_filename: string
        status: UploadStatus
        error_message?: string
        photo_id?: string
    }>
}

export interface ChunkUploadResponse {
    success: boolean
    batch_id: string
    uploads: Array<{
        id: string
        original_filename: string
        status: UploadStatus
        error_message?: string
    }>
}

export interface UploadProgress {
    total: number
    uploaded: number
    completed: number
    failed: number
    processing: number
    percentage: number
    isComplete: boolean
    files: FileUploadState[]
}

export interface UploadCallbacks {
    onProgress?: (progress: UploadProgress) => void
    onFileComplete?: (file: FileUploadState) => void
    onFileError?: (file: FileUploadState, error: string) => void
    onBatchComplete?: (progress: UploadProgress) => void
    onError?: (error: Error) => void
}

export interface ChunkUploadOptions {
    chunkSize?: number
    maxChunkBytes?: number
    timeout?: number
    pollInterval?: number
    maxRetries?: number
    endpoint?: 'events' | 'galleries' // Which endpoint to use
}
