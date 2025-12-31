import type {GalleryItem} from '@/types'

/**
 * Configuration statique des images de la galerie
 * Images optimisées WebP depuis /public/optimized/
 */

interface CategoryConfig {
    name: string
    basePath: string
    images: string[]
}

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
    // === AJOUTE TES 8 LIENS YOUTUBE ICI ===
    // Remplace les IDs par les vrais IDs de tes vidéos
    // L'ID est la partie après "v=" dans l'URL YouTube
    // Exemple: https://www.youtube.com/watch?v=ABC123def -> youtubeId: 'ABC123def'

    {youtubeId: 'VIDEO_ID_1', title: 'Vidéo 1'},
    {youtubeId: 'VIDEO_ID_2', title: 'Vidéo 2'},
    {youtubeId: 'VIDEO_ID_3', title: 'Vidéo 3'},
    {youtubeId: 'VIDEO_ID_4', title: 'Vidéo 4'},
    {youtubeId: 'VIDEO_ID_5', title: 'Vidéo 5'},
    {youtubeId: 'VIDEO_ID_6', title: 'Vidéo 6'},
    {youtubeId: 'VIDEO_ID_7', title: 'Vidéo 7'},
    {youtubeId: 'VIDEO_ID_8', title: 'Vidéo 8'},
]

const categories: CategoryConfig[] = [
    {
        name: 'Portraits',
        basePath: '/optimized/Portraits',
        images: [
            '1.webp', '2.webp', '3.webp', '4.webp', '5.webp', '6.webp', '7.webp', '8.webp', '9.webp', '10.webp',
            '11.webp', '12.webp', '13.webp', '14.webp', '15.webp', '16.webp', '17.webp', '18.webp', '19.webp', '20.webp',
            '21.webp', '22.webp', '23.webp', '24.webp', '25.webp', '26.webp', '27.webp', '28.webp', '29.webp', '30.webp'
        ]
    },
    {
        name: 'Sport',
        basePath: '/optimized/Sport',
        images: [
            '1.webp', '2.webp', '3.webp', '4.webp', '5.webp', '6.webp', '7.webp', '8.webp', '9.webp', '10.webp',
            '11.webp', '12.webp', '13.webp', '14.webp', '15.webp', '16.webp', '17.webp', '18.webp', '19.webp', '20.webp',
            '21.webp', '22.webp', '23.webp', '24.webp', '25.webp', '26.webp', '27.webp', '28.webp', '29.webp', '30.webp',
            '31.webp', '32.webp', '33.webp', '34.webp', '35.webp', '36.webp', '37.webp', '38.webp', '39.webp', '40.webp',
            '41.webp', '42.webp'
        ]
    },
    {
        name: 'Animalier',
        basePath: '/optimized/Animalier',
        images: [
            '1.webp', '2.webp', '3.webp', '4.webp', '5.webp', '6.webp', '7.webp', '8.webp', '9.webp', '10.webp',
            '11.webp', '12.webp', '13.webp', '14.webp', '15.webp'
        ]
    },
    {
        name: 'Automobile',
        basePath: '/optimized/Automobile',
        images: [
            '1.webp', '2.webp', '3.webp', '4.webp', '5.webp', '6.webp', '7.webp', '8.webp', '9.webp', '10.webp',
            '11.webp', '12.webp', '13.webp', '14.webp', '15.webp', '16.webp', '17.webp', '18.webp', '19.webp', '20.webp',
            '21.webp', '22.webp', '23.webp', '24.webp'
        ]
    },
    {
        name: 'Entreprise',
        basePath: '/optimized/Entreprise',
        images: [
            '1.webp', '2.webp', '3.webp', '4.webp', '5.webp', '6.webp', '8.webp', '9.webp', '10.webp',
            '11.webp', '12.webp', '13.webp', '14.webp', '15.webp', '16.webp'
        ]
    }
]

export function getGalleryItems(): GalleryItem[] {
    const items: GalleryItem[] = []

    // Ajouter les images des catégories
    for (const category of categories) {
        for (const image of category.images) {
            items.push({
                url: `${category.basePath}/${image}`,
                alt: `${category.name} - ${image}`,
                type: 'image',
                category: category.name
            })
        }
    }

    // Ajouter les vidéos YouTube
    for (const video of youtubeVideos) {
        // Ignorer les placeholders non configurés
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
    return ['Portraits', 'Sport', 'Animalier', 'Automobile', 'Entreprise', 'Videos']
}
