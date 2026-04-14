/**
 * Utilitaires pour la galerie
 * Fonctions extraites de MasonryGallery.vue pour réutilisation
 */

import type {GalleryItem} from '@/types'

/**
 * Extrait le numéro du fichier depuis l'URL
 * Support des formats: "1.jpg", "1-xxxx.jpg", "1_abc.jpg"
 */
function getFileNumber(url: string): number {
    const filename = url.split('/').pop() ?? ''
    const match = filename.match(/^(\d+)/)
    return match ? parseInt(match[1], 10) : Infinity
}

/**
 * Trie les items par numéro de fichier
 */
export function sortByFilename(items: GalleryItem[]): GalleryItem[] {
    return [...items].sort((a, b) => getFileNumber(a.url) - getFileNumber(b.url))
}

/**
 * Fisher-Yates shuffle - mélange aléatoire d'un tableau
 */
function shuffle<T>(arr: T[]): T[] {
    const result = [...arr]
    for (let i = result.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1))
        ;[result[i], result[j]] = [result[j], result[i]]
    }
    return result
}

/**
 * Détermine le nombre de colonnes selon la largeur d'écran
 */
export function getColumnCount(width: number): number {
    if (width <= 640) return 1
    if (width <= 1024) return 2
    // if (width <= 1440) return 3
    // if (width <= 1920) return 5
    return 3
}

/**
 * Vérifie si un filtre correspond aux vidéos
 */
export function isVideoFilter(filter: string): boolean {
    return /vid(eo|é)s?/i.test(filter)
}

/**
 * Vérifie si un item est une vidéo (locale ou YouTube)
 */
export function isVideoItem(type: string): boolean {
    return type === 'video' || type === 'youtube'
}

/**
 * Filtre et prépare les items pour l'affichage "Tous"
 * Prend 4 images aléatoires par catégorie et les mélange
 */
export function getShuffledAllItems(
    items: GalleryItem[],
    categories: string[],
    imagesPerCategory = 4
): GalleryItem[] {
    const result: GalleryItem[] = []

    for (const category of categories) {
        if (isVideoFilter(category)) continue

        const categoryItems = items.filter(
            item => item.category === category && !isVideoItem(item.type)
        )
        result.push(...shuffle(categoryItems).slice(0, imagesPerCategory))
    }

    return shuffle(result)
}

