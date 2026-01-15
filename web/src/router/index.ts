/**
 * Configuration du routeur Vue Router
 * Utilise le lazy loading pour optimiser les performances
 */

import {createRouter, createWebHistory, type RouteRecordRaw} from 'vue-router'
import {useAuthStore} from '@/stores/auth'

// Home est chargé immédiatement car c'est la page d'accueil
import Home from '@/views/Home.vue'

// Lazy loading pour les autres pages
const Portfolio = () => import('@/views/Portfolio.vue')
const Prestations = () => import('@/views/Prestations.vue')
const BonsCadeaux = () => import('@/views/BonsCadeaux.vue')
const About = () => import('@/views/About.vue')
const Contact = () => import('@/views/Contact.vue')
const MentionsLegales = () => import('@/views/MentionsLegales.vue')

// Gallery access pages
const GalleryAccess = () => import('@/views/GalleryAccess.vue')
const ProtectedGallery = () => import('@/views/ProtectedGallery.vue')
const DownloadGallery = () => import('@/views/DownloadGallery.vue')

// Admin pages
const AdminLogin = () => import('@/views/admin/Login.vue')
const AdminDashboard = () => import('@/views/admin/Dashboard.vue')
const AdminGalleries = () => import('@/views/admin/Galleries.vue')
const AdminClients = () => import('@/views/admin/Clients.vue')
const AdminPrestations = () => import('@/views/admin/Prestations.vue')
const AdminReservations = () => import('@/views/admin/Reservations.vue')
const AdminGiftCards = () => import('@/views/admin/GiftCards.vue')

export const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'home',
        component: Home,
        meta: {
            title: 'Océane Torres | Photographe Saint-Étienne, Loire & Lyon',
            description: 'Océane Torres, photographe professionnelle à Saint-Étienne et Lyon. Portraits, sport, animaux, automobile, entreprise. Séances photo en Loire et Auvergne-Rhône-Alpes.'
        }
    },
    {
        path: '/portfolio',
        name: 'portfolio',
        component: Portfolio,
        meta: {
            title: 'Portfolio Photos',
            description: 'Découvrez le portfolio d\'Océane Torres : portraits, photographie sportive, animaux, automobile et entreprise. Photographe professionnelle Loire et Lyon.'
        }
    },
    {
        path: '/prestations',
        name: 'prestations',
        component: Prestations,
        meta: {
            title: 'Tarifs & Prestations',
            description: 'Tarifs et prestations photo : séances portrait, reportage sportif, shooting animalier, automobile et entreprise. Photographe Saint-Étienne, Loire, Lyon.'
        }
    },
    {
        path: '/bons',
        name: 'bons',
        component: BonsCadeaux,
        meta: {
            title: 'Bons Cadeaux',
            description: 'Offrez une séance photo avec Océane Torres. Bons cadeaux pour portraits, shooting couple, famille. Photographe Loire et région lyonnaise.'
        }
    },
    {
        path: '/a-propos',
        name: 'about',
        component: About,
        meta: {
            title: 'À propos - Océane Torres',
            description: 'Découvrez Océane Torres, photographe et vidéaste professionnelle basée en Loire. Passionnée par le portrait, le sport et l\'animalier.'
        }
    },
    {
        path: '/contact',
        name: 'contact',
        component: Contact,
        meta: {
            title: 'Contact',
            description: 'Contactez Océane Torres pour réserver votre séance photo à Saint-Étienne, Lyon ou dans toute l\'Auvergne-Rhône-Alpes. Devis gratuit.'
        }
    },
    {
        path: '/mentions-legales',
        name: 'mentions-legales',
        component: MentionsLegales,
        meta: {
            title: 'Mentions légales',
            description: 'Mentions légales du site Océane Torres Photographie.'
        }
    },
    // Gallery access routes (noindex pour éviter l'indexation de pages dynamiques)
    {
        path: '/gallery',
        name: 'gallery-access',
        component: GalleryAccess,
        meta: {title: 'Accès Galerie', robots: 'noindex, nofollow'}
    },
    {
        path: '/gallery/download/:token',
        name: 'download-gallery',
        component: DownloadGallery,
        meta: {title: 'Téléchargement', robots: 'noindex, nofollow'}
    },
    {
        path: '/gallery/:code',
        name: 'protected-gallery',
        component: ProtectedGallery,
        meta: {title: 'Galerie', robots: 'noindex, nofollow'}
    },
    // Admin routes
    {
        path: '/admin/login',
        name: 'admin-login',
        component: AdminLogin,
        meta: {title: 'Connexion Admin', layout: 'admin'}
    },
    {
        path: '/admin',
        name: 'admin-dashboard',
        component: AdminDashboard,
        meta: {title: 'Dashboard', layout: 'admin', requiresAuth: true}
    },
    {
        path: '/admin/galleries',
        name: 'admin-galleries',
        component: AdminGalleries,
        meta: {title: 'Galeries', layout: 'admin', requiresAuth: true}
    },
    {
        path: '/admin/clients',
        name: 'admin-clients',
        component: AdminClients,
        meta: {title: 'Clients', layout: 'admin', requiresAuth: true}
    },
    {
        path: '/admin/prestations',
        name: 'admin-prestations',
        component: AdminPrestations,
        meta: {title: 'Prestations', layout: 'admin', requiresAuth: true}
    },
    {
        path: '/admin/reservations',
        name: 'admin-reservations',
        component: AdminReservations,
        meta: {title: 'Réservations', layout: 'admin', requiresAuth: true}
    },
    {
        path: '/admin/gift-cards',
        name: 'admin-gift-cards',
        component: AdminGiftCards,
        meta: {title: 'Bons Cadeaux', layout: 'admin', requiresAuth: true}
    },
    // Catch-all redirect to home
    {
        path: '/:pathMatch(.*)*',
        redirect: '/'
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(_to, _from, savedPosition) {
        if (savedPosition) {
            return savedPosition
        }
        return {top: 0, behavior: 'smooth'}
    }
})

// Auth guard for protected routes
router.beforeEach(async (to, _from, next) => {
    const requiresAuth = to.meta.requiresAuth

    if (requiresAuth) {
        const authStore = useAuthStore()
        const isAuthenticated = await authStore.checkAuth()

        if (!isAuthenticated) {
            next({name: 'admin-login', query: {redirect: to.fullPath}})
            return
        }
    }

    next()
})

// Update document title and meta description on navigation
router.afterEach((to) => {
    const baseTitle = 'Océane Torres Photographie'
    const pageTitle = to.meta.title as string | undefined
    const pageDescription = to.meta.description as string | undefined

    // Update title
    document.title = pageTitle ? `${pageTitle} | ${baseTitle}` : baseTitle

    // Update meta description
    if (pageDescription) {
        let metaDescription = document.querySelector('meta[name="description"]')
        if (metaDescription) {
            metaDescription.setAttribute('content', pageDescription)
        }

        // Also update OG description
        let ogDescription = document.querySelector('meta[property="og:description"]')
        if (ogDescription) {
            ogDescription.setAttribute('content', pageDescription)
        }
    }

    // Update canonical URL
    const canonical = document.querySelector('link[rel="canonical"]')
    if (canonical) {
        canonical.setAttribute('href', `https://oceanetorresphotographie.fr${to.path}`)
    }

    // Update robots meta tag
    const robotsContent = to.meta.robots as string | undefined
    let robotsMeta = document.querySelector('meta[name="robots"]')
    if (robotsMeta) {
        robotsMeta.setAttribute('content', robotsContent || 'index, follow')
    }
})

export default router
