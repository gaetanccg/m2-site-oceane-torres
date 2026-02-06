/**
 * Composable for chunked file uploads
 */

import { ref, type Ref } from 'vue'
import { ChunkedUploadService } from '@/services/uploadService'
import { UPLOAD_CONFIG } from '@/config/constants'
import type {
    FileUploadState,
    UploadProgress,
    ChunkUploadOptions,
} from '@/types/upload'

export interface UseChunkedUploadReturn {
    files: Ref<FileUploadState[]>
    isUploading: Ref<boolean>
    progress: Ref<UploadProgress | null>
    completedPhotos: Ref<string[]>
    upload: (galleryId: string, fileList: FileList | File[], options?: ChunkUploadOptions) => Promise<UploadProgress>
    cancel: () => void
    reset: () => void
}

export function useChunkedUpload(): UseChunkedUploadReturn {
    const uploadService = new ChunkedUploadService()

    const files = ref<FileUploadState[]>([])
    const isUploading = ref(false)
    const progress = ref<UploadProgress | null>(null)
    const completedPhotos = ref<string[]>([])

    const upload = async (
        galleryId: string,
        fileList: FileList | File[],
        options: ChunkUploadOptions = {}
    ): Promise<UploadProgress> => {
        const fileArray = Array.from(fileList)

        if (fileArray.length === 0) {
            throw new Error('No files to upload')
        }

        // Validate file sizes
        const oversizedFiles = fileArray.filter(
            (file) => file.size > UPLOAD_CONFIG.maxFileSize
        )
        if (oversizedFiles.length > 0) {
            throw new Error(
                `Les fichiers suivants sont trop volumineux (max ${UPLOAD_CONFIG.maxFileSize / 1024 / 1024}MB): ${oversizedFiles.map((f) => f.name).join(', ')}`
            )
        }

        isUploading.value = true
        completedPhotos.value = []

        // Initialize files state
        files.value = fileArray.map((file, index) => ({
            id: `local_${index}`,
            file,
            originalFilename: file.name,
            status: 'pending',
            progress: 0,
        }))

        progress.value = {
            total: fileArray.length,
            uploaded: 0,
            completed: 0,
            failed: 0,
            processing: fileArray.length,
            percentage: 0,
            isComplete: false,
            files: files.value,
        }

        try {
            const result = await uploadService.uploadChunked(
                galleryId,
                fileArray,
                {
                    onProgress: (p) => {
                        progress.value = p
                        files.value = [...p.files]
                    },
                    onFileComplete: (file) => {
                        if (file.photoId) {
                            completedPhotos.value.push(file.photoId)
                        }
                    },
                    onFileError: () => {
                        // Error handled by progress state
                    },
                    onBatchComplete: (p) => {
                        progress.value = p
                        files.value = [...p.files]
                    },
                    onError: () => {
                        // Error handled by progress state
                    },
                },
                options
            )

            return result
        } finally {
            isUploading.value = false
        }
    }

    const cancel = (): void => {
        uploadService.cancel()
        isUploading.value = false
    }

    const reset = (): void => {
        files.value = []
        progress.value = null
        completedPhotos.value = []
        isUploading.value = false
    }

    return {
        files,
        isUploading,
        progress,
        completedPhotos,
        upload,
        cancel,
        reset,
    }
}
