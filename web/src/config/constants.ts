/**
 * Constantes centralisées de l'application
 * Source unique de vérité pour les données statiques
 */

import type {NavLink, SocialLink, ContactInfo, CategoryDescriptions} from '@/types'

// ============================================================================
// Couleurs
// ============================================================================

export const COLORS = {
    gold: '#D4AF37',
    goldLight: 'rgba(212, 175, 55, 0.9)',
    black: '#0a0708',
    white: '#ffffff',
} as const

// ============================================================================
// Navigation
// ============================================================================

export const NAV_LINKS: NavLink[] = [
    {name: 'Portfolio', path: '/portfolio'},
    {name: 'Evenements', path: '/evenements'},
    {name: 'Prestations', path: '/prestations'},
    {name: 'Bons Cadeaux', path: '/bons'},
    {name: 'A propos', path: '/a-propos'},
    {name: 'Contact', path: '/contact'},
]

// ============================================================================
// Contact
// ============================================================================

export const CONTACT_INFO: ContactInfo = {
    email: 'oceanetorresphotographie@gmail.com',
    instagram: 'oceanetorresphotographie',
    phone: '06 11 01 44 77',
}

export const SOCIAL_LINKS: SocialLink[] = [
    {
        name: 'Instagram',
        url: 'https://instagram.com/oceanetorresphotographie',
        icon: 'instagram',
        ariaLabel: 'Suivez-nous sur Instagram',
    },
    {
        name: 'TikTok',
        url: 'https://www.tiktok.com/@oceanetorresphotographie',
        icon: 'tiktok',
        ariaLabel: 'Suivez-nous sur TikTok',
    },
    {
        name: 'LinkedIn',
        url: 'https://www.linkedin.com/in/océane-torres/',
        icon: 'linkedin',
        ariaLabel: 'Suivez-nous sur LinkedIn',
    }
]

// ============================================================================
// Catégories Portfolio
// ============================================================================

export const PORTFOLIO_CATEGORIES = [
    'Portraits',
    'Sport',
    'Animalier',
    'Automobile',
    'Entreprise',
    'Videos',
] as const

export const CATEGORY_DESCRIPTIONS: CategoryDescriptions = {
    Tous: "Sélectionnez une catégorie pour voir l'entièreté des photos.",
    Portraits: "Chaque portrait est une rencontre, un moment suspendu où je cherche à capturer bien plus qu'un visage. Que ce soit lors d'un concert, d'un défilé, en couple, en famille, pour une grossesse ou un portrait professionnel, je m'attache à faire ressortir l'histoire et les émotions de chacun. Mon objectif est que tu te sentes à l'aise, toi-même, pour que chaque photo reflète le meilleur de qui tu es et devienne un souvenir sincère et vivant.",
    Sport: "La photographie sportive, c'est l'art de figer l'instant où tout bascule : la puissance d'un coup, la vitesse d'une action, la grâce d'un mouvement. Que ce soit en équitation, sur un terrain de football, sur un ring de boxe, sur une scène de danse... Chaque discipline raconte une histoire faite de force, de dépassement et d'émotions brutes. Mon objectif est de transformer ces moments en images intenses et mémorables, qui transmettent toute l'énergie et la beauté du sport.",
    Vidéos: "La vidéo permet de raconter des histoires autrement : elle capture le mouvement, l'ambiance et les émotions d'un instant avec une intensité unique. Que ce soit des paysages, le rythme effréné d'une journée de concours équestre ou le suivi d'un athlète tout au long d'une compétition, chaque séquence est pensée pour immerger le spectateur et faire ressentir l'énergie et la beauté du moment. Mon objectif est de transformer ces instants en images vivantes, où chaque détail raconte sa propre histoire.",
    Animalier: "Photographier les animaux, c'est saisir leur spontanéité, leur regard et leur énergie unique. Chats, chiens, chevaux ou espèces plus sauvages, chaque rencontre raconte une histoire différente, faite de complicité, de liberté ou de puissance. Mon objectif est de capturer ces instants naturels, pour que chaque image transmette toute l'authenticité et le caractère de l'animal.",
    Automobile: "La brillance d'une carrosserie, la précision d'une ligne, l'allure unique d'une moto ou d'une voiture… L'automobile et la moto sont bien plus que de simples bolides : elles incarnent une passion et une identité. Mon objectif est de capturer cette puissance et ce caractère, que ce soit en mouvement ou en statique, pour créer des images qui mettent en valeur à la fois la performance et l'élégance de chaque véhicule.",
    Entreprise: "Les photos d'entreprise, c'est une manière directe et authentique de montrer ce que votre marque ou votre établissement a réellement à offrir. Que vous soyez un restaurant, une boutique, une marque ou un service, je cherche à capturer votre univers, votre identité et l'atmosphère qui vous distingue. Mon objectif est simple : créer des visuels qui servent votre communication, qui renforcent votre image et qui transmettent clairement ce que vous faites. Des photos pensées pour vos sites, vos réseaux, vos campagnes ou vos supports print…",
}

// ============================================================================
// API Configuration (pour Laravel)
// ============================================================================

export const API_CONFIG = {
    baseUrl: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
    timeout: 10000,
} as const

// ============================================================================
// Business Info
// ============================================================================

export const BUSINESS_INFO = {
    name: 'Océane Torres Photographie',
    siret: '993 948 587',
    location: 'Lorette',
    workingHours: {
        days: 'Du lundi au samedi',
        hours: '9h00 - 19h00',
    },
    travelZone: {
        free: 20,
        unit: 'min',
    },
} as const
