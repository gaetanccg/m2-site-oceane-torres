/**
 * Pure utility functions for chunked uploads
 */

import type { FileUploadState, UploadProgress } from '@/types/upload'

/**
 * Split files into chunks respecting both file count and byte size limits.
 * A single file that exceeds maxChunkBytes gets its own chunk.
 */
export function buildChunks(files: File[], maxFiles: number, maxBytes: number): File[][] {
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
 * Build progress summary from file states
 */
export function buildProgress(fileStates: Map<string, FileUploadState>): UploadProgress {
    const files = Array.from(fileStates.values())
    const total = files.length
    const completed = files.filter((f) => f.status === 'completed').length
    const failed = files.filter((f) => f.status === 'failed').length
    const processing = files.filter((f) =>
        ['pending', 'uploading', 'processing'].includes(f.status)
    ).length

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

/**
 * Find a file state by original filename
 */
export function findFileState(
    fileStates: Map<string, FileUploadState>,
    filename: string
): FileUploadState | undefined {
    return Array.from(fileStates.values()).find(
        (state) => state.originalFilename === filename
    )
}

/**
 * Generate a unique batch ID
 */
export function generateBatchId(): string {
    return `batch_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`
}
