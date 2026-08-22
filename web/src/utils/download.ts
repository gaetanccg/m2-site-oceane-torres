export function isInAppBrowser(): boolean {
    const ua = navigator.userAgent || ''
    return /FBAN|FBAV|Instagram/i.test(ua)
}

export function saveBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
}

/**
 * Sans la vérification de statut, MinIO renvoie son XML d'erreur avec un code 4xx
 * et le fichier est enregistré quand même, sous le nom attendu.
 */
export async function downloadFile(url: string, filename: string): Promise<void> {
    const response = await fetch(url)

    if (!response.ok) {
        throw new Error(`Le fichier n'a pas pu être récupéré (${response.status})`)
    }

    if (response.headers.get('content-type')?.includes('xml')) {
        throw new Error("Le fichier n'a pas pu être récupéré : lien expiré ou invalide")
    }

    saveBlob(await response.blob(), filename)
}
