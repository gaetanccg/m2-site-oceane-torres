/**
 * XHR-based chunk uploader with progress tracking and retry logic
 */

import type { ChunkUploadResponse } from '@/types/upload'

interface ChunkUploaderConfig {
    baseUrl: string
    getToken: () => string | null
    getXsrfToken: () => string | null
}

/**
 * Upload a single chunk of files via XHR with real progress tracking
 */
export function uploadChunkWithProgress(
    config: ChunkUploaderConfig,
    galleryId: string,
    files: File[],
    batchId: string,
    timeout: number,
    endpointPath: string,
    onProgress: (percent: number) => void,
    activeXHRs: XMLHttpRequest[]
): Promise<ChunkUploadResponse> {
    return new Promise((resolve, reject) => {
        const formData = new FormData()
        files.forEach((file) => formData.append('photos[]', file))
        formData.append('batch_id', batchId)

        const xhr = new XMLHttpRequest()
        activeXHRs.push(xhr)

        xhr.timeout = timeout

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                const percent = Math.round((event.loaded / event.total) * 100)
                onProgress(percent)
            }
        }

        const removeXHR = () => {
            const index = activeXHRs.indexOf(xhr)
            if (index > -1) activeXHRs.splice(index, 1)
        }

        xhr.onload = () => {
            removeXHR()
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    resolve(JSON.parse(xhr.responseText))
                } catch {
                    reject(new Error('Invalid JSON response'))
                }
            } else {
                reject(new Error(`Upload failed: ${xhr.status}`))
            }
        }

        xhr.onerror = () => { removeXHR(); reject(new Error('Network error')) }
        xhr.ontimeout = () => { removeXHR(); reject(new Error('Upload timeout')) }
        xhr.onabort = () => { removeXHR(); reject(new Error('Upload cancelled')) }

        xhr.open('POST', `${config.baseUrl}/admin/${endpointPath}/${galleryId}/photos/async`)

        xhr.setRequestHeader('Accept', 'application/json')
        const token = config.getToken()
        const xsrfToken = config.getXsrfToken()
        if (token) xhr.setRequestHeader('Authorization', `Bearer ${token}`)
        if (xsrfToken) xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken)
        xhr.withCredentials = true

        xhr.send(formData)
    })
}

/**
 * Upload a chunk with automatic retry on timeout/network errors
 */
export async function uploadChunkWithRetry(
    config: ChunkUploaderConfig,
    galleryId: string,
    files: File[],
    batchId: string,
    timeout: number,
    endpointPath: string,
    maxRetries: number,
    onProgress: (percent: number) => void,
    activeXHRs: XMLHttpRequest[],
    isCancelled: () => boolean
): Promise<ChunkUploadResponse> {
    let lastError: Error | null = null

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
        try {
            return await uploadChunkWithProgress(
                config, galleryId, files, batchId, timeout, endpointPath, onProgress, activeXHRs
            )
        } catch (error) {
            lastError = error as Error
            const isRetryable = lastError.message === 'Upload timeout'
                || lastError.message === 'Network error'

            if (!isRetryable || attempt >= maxRetries || isCancelled()) {
                throw lastError
            }

            onProgress(0)
        }
    }

    throw lastError!
}
