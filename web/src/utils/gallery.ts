/**
 * Utilitaires pour la galerie
 * Fonctions extraites de MasonryGallery.vue pour réutilisation
 */

import type {GalleryItem} from '@/types'

/**
 * Extrait le numéro du fichier depuis l'URL
 * Support des formats: "1.jpg", "1-xxxx.jpg", "1_abc.jpg"
 */
export function getFileNumber(url: string): number {
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
export function shuffle<T>(arr: T[]): T[] {
    const result = [...arr]
    for (let i = result.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1))
        ;[result[i], result[j]] = [result[j], result[i]]
    }
    return result
}

/**
 * Réordonne les items pour un affichage en colonnes CSS
 * CSS columns remplit de haut en bas, cette fonction réordonne pour un affichage gauche-droite
 * Exemple: [1,2,3,4,5,6] -> [1,3,5,2,4,6] pour 2 colonnes
 */
export function reorderForColumns<T>(items: T[], cols: number): T[] {
    if (cols <= 1 || items.length <= cols) return items

    const totalItems = items.length
    const baseRows = Math.floor(totalItems / cols)
    const extraItems = totalItems % cols // Colonnes avec une ligne de plus

    const result: T[] = new Array(totalItems)

    for (let i = 0; i < totalItems; i++) {
        const row = Math.floor(i / cols)
        const col = i % cols

        // Calculer l'index de début de cette colonne
        // Les premières 'extraItems' colonnes ont (baseRows + 1) lignes
        let colStart = 0
        for (let c = 0; c < col; c++) {
            colStart += c < extraItems ? baseRows + 1 : baseRows
        }

        const newIndex = colStart + row
        if (newIndex < totalItems) {
            result[newIndex] = items[i]
        }
    }

    return result.filter(item => item !== undefined)
}

/**
 * Détermine le nombre de colonnes selon la largeur d'écran
 */
export function getColumnCount(width: number): number {
    if (width <= 640) return 1
    if (width <= 1024) return 2
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

/**
 * Crée un observateur d'intersection pour le lazy loading
 */
export function createImageObserver(
    onIntersect: (src: string) => void,
    options: IntersectionObserverInit = {}
): IntersectionObserver {
    const defaultOptions: IntersectionObserverInit = {
        root: null,
        rootMargin: '600px 0px',
        threshold: 0.01,
        ...options,
    }

    return new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target as HTMLImageElement
                const src = img.getAttribute('data-src')
                if (src) {
                    onIntersect(src)
                }
            }
        })
    }, defaultOptions)
}
