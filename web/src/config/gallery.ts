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

    return items
}

export function getCategories(): string[] {
    return ['Portraits', 'Sport', 'Animalier', 'Automobile', 'Entreprise']
}
