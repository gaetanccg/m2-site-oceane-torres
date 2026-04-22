import type {GalleryItem} from '@/types'
import manifest from './gallery-manifest.json'

/**
 * Configuration de la galerie
 *
 * La liste des images est auto-générée depuis public/optimized/
 * par `node optimize-images.js` (manifest: gallery-manifest.json).
 *
 * Pour ajouter des photos :
 *   1. Déposer les JPG dans public/images/<Catégorie>/ avec préfixe padded
 *      (ex: 010_description.jpg, 020_autre.jpg...)
 *   2. node optimize-images.js   (optimise + régénère le manifest)
 *   3. node scripts/generate-thumbs.js
 */

/**
 * Configuration des vidéos YouTube
 * Pour ajouter une vidéo :
 * 1. Récupère l'ID de la vidéo YouTube (la partie après v= dans l'URL)
 *    Exemple: https://www.youtube.com/watch?v=dQw4w9WgXcQ -> ID = dQw4w9WgXcQ
 * 2. Ajoute l'ID dans le tableau ci-dessous avec un titre descriptif
 */
interface YouTubeVideoConfig {
    youtubeId: string
    title: string
}

const youtubeVideos: YouTubeVideoConfig[] = [
    {youtubeId: 'pdEuOR5ckHQ', title: 'Vidéo hair transformation Vince Barb\'or'},
    {youtubeId: 'GArIbW4F5UI', title: 'Récap d\'une journée de CSO à Cluny'},
    {youtubeId: '0iPOlSZPzlI', title: 'Concert des Chœur du Sud de Rive de Gier avec Frank CASTELLANO et Ycare'},
    {youtubeId: 'FNUomXKX1hE', title: 'Céleste Joly en CSO à Saint-Georges Équitation'},
    {youtubeId: 'xSUVxBedOhc', title: 'Vidéo au drone d\'un motard'},
    {youtubeId: 'Sea2FkTh9Vg', title: 'Vidéo immersive lors de la Semaine Vichyssoise'},
    {youtubeId: 'PfvJwdAEZH8', title: 'Vidéo drone randonnée au Gouffre d\'Enfer'},
]

// Ordre d'affichage des catégories dans le portfolio
const CATEGORY_ORDER = ['Portraits', 'Sport', 'Animalier', 'Concert', 'Automobile', 'Entreprise']

// Alt texts SEO-friendly par catégorie
const categoryAltPrefix: Record<string, string> = {
    'Portraits': 'Photo portrait par Océane Torres photographe Loire Saint-Étienne',
    'Sport': 'Photo sportive par Océane Torres photographe Lyon Rhône',
    'Animalier': 'Photo animalière par Océane Torres photographe Auvergne-Rhône-Alpes',
    'Concert': 'Photo de concert par Océane Torres photographe Auvergne-Rhône-Alpes',
    'Automobile': 'Shooting automobile par Océane Torres photographe Saint-Étienne Loire',
    'Entreprise': 'Photo entreprise par Océane Torres photographe Lyon Saint-Étienne',
}

export function getGalleryItems(): GalleryItem[] {
    const items: GalleryItem[] = []

    // Images : ordre défini par CATEGORY_ORDER, ordre interne par le manifest
    // Grille : thumbnails 400px (AVIF primary, WebP fallback)
    // Lightbox : full-res (AVIF primary, WebP fallback)
    for (const category of CATEGORY_ORDER) {
        const images = (manifest.categories as Record<string, string[]>)[category] || []
        const basePath = `/optimized/${category}`
        const altPrefix = categoryAltPrefix[category] || category

        for (let i = 0; i < images.length; i++) {
            const image = images[i]
            const avifImage = image.replace('.webp', '.avif')
            items.push({
                thumbnailUrl: `${basePath}/thumbs/${avifImage}`,
                previewUrl: `${basePath}/${avifImage}`,
                url: `${basePath}/${image}`,
                alt: `${altPrefix} - ${i + 1}`,
                type: 'image',
                category
            })
        }
    }

    // Vidéos YouTube
    for (const video of youtubeVideos) {
        if (!video.youtubeId.startsWith('VIDEO_ID_')) {
            items.push({
                url: `https://img.youtube.com/vi/${video.youtubeId}/maxresdefault.jpg`,
                alt: video.title,
                type: 'youtube',
                category: 'Videos',
                youtubeId: video.youtubeId
            })
        }
    }

    return items
}

export function getCategories(): string[] {
    return [...CATEGORY_ORDER, 'Vidéos']
}
