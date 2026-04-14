/**
 * Configuration du routeur Vue Router
 * Utilise le lazy loading pour optimiser les performances
 */

import {createRouter, createWebHistory, type RouteRecordRaw} from 'vue-router'
import {useAuthStore} from '@/stores/auth'
import {useConsentStore} from '@/stores/consent'

// Fonction gtag standard — DOIT utiliser `arguments` pour la compatibilité gtag.js
function gtag(..._args: unknown[]) {
    // eslint-disable-next-line prefer-rest-params
    window.dataLayer?.push(arguments)
}

// Home est chargé immédiatement car c'est la page d'accueil
import Home from '@/views/Home.vue'

// Lazy loading pour les autres pages
const Portfolio = () => import('@/views/Portfolio.vue')
const Prestations = () => import('@/views/Prestations.vue')
const BonsCadeaux = () => import('@/views/BonsCadeaux.vue')
const About = () => import('@/views/About.vue')
const Contact = () => import('@/views/Contact.vue')
const MentionsLegales = () => import('@/views/MentionsLegales.vue')
const CGV = () => import('@/views/CGV.vue')
const PolitiqueConfidentialite = () => import('@/views/PolitiqueConfidentialite.vue')
const NotFound = () => import('@/views/NotFound.vue')

// Gallery access pages
const GalleryAccess = () => import('@/views/GalleryAccess.vue')
const ProtectedGallery = () => import('@/views/ProtectedGallery.vue')
const DownloadGallery = () => import('@/views/DownloadGallery.vue')

// Event galleries (public)
const Events = () => import('@/views/Events.vue')
const EventGallery = () => import('@/views/EventGallery.vue')

// Cart & Checkout
const Cart = () => import('@/views/Cart.vue')
const Checkout = () => import('@/views/Checkout.vue')
const OrderConfirmation = () => import('@/views/OrderConfirmation.vue')


// Account pages (client)
const AccountDashboard = () => import('@/views/account/Dashboard.vue')
const AccountOrderDetail = () => import('@/views/account/OrderDetail.vue')

// Admin pages
const AdminRoot = () => import('@/views/admin/AdminRoot.vue')
const AdminDashboard = () => import('@/views/admin/Dashboard.vue')
const AdminGalleries = () => import('@/views/admin/Galleries.vue')
const AdminClients = () => import('@/views/admin/Clients.vue')
const AdminPrestations = () => import('@/views/admin/Prestations.vue')
const AdminReservations = () => import('@/views/admin/Reservations.vue')
const AdminGiftCards = () => import('@/views/admin/GiftCards.vue')
const AdminEventGalleries = () => import('@/views/admin/EventGalleries.vue')
const AdminOrders = () => import('@/views/admin/Orders.vue')

export const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'home',
        component: Home,
        meta: {
            title: 'Océane Torres | Photographe Saint-Étienne, Loire & Lyon',
            description: 'Océane Torres, photographe professionnelle à Saint-Étienne, Lyon, Rive-de-Gier, Givors et Saint-Chamond. Portraits, sport, animaux, automobile, entreprise. Séances photo en Loire, Rhône et Auvergne-Rhône-Alpes.'
        }
    },
    {
        path: '/portfolio',
        name: 'portfolio',
        component: Portfolio,
        meta: {
            title: 'Portfolio Photos',
            description: 'Portfolio d\'Océane Torres photographe : portraits, sport, animaux, automobile, entreprise. Photos réalisées à Saint-Étienne, Lyon, Rive-de-Gier, Givors en Loire et Rhône.'
        }
    },
    {
        path: '/prestations',
        name: 'prestations',
        component: Prestations,
        meta: {
            title: 'Tarifs & Prestations',
            description: 'Tarifs et prestations photo : portraits, sport, animaux, automobile, entreprise. Photographe à Saint-Étienne, Lyon, Rive-de-Gier, Saint-Chamond, Givors. Devis gratuit.'
        }
    },
    {
        path: '/bons',
        name: 'bons',
        component: BonsCadeaux,
        meta: {
            title: 'Bons Cadeaux',
            description: 'Offrez une séance photo avec Océane Torres. Bons cadeaux pour portraits, shooting couple, famille. Photographe Saint-Étienne, Lyon, Rive-de-Gier, Loire et Rhône.'
        }
    },
    {
        path: '/a-propos',
        name: 'about',
        component: About,
        meta: {
            title: 'À propos - Océane Torres',
            description: 'Océane Torres, photographe et vidéaste professionnelle basée à Lorette (Loire). Passionnée par le portrait, le sport et l\'animalier. Interventions Saint-Étienne, Lyon, Rhône.'
        }
    },
    {
        path: '/contact',
        name: 'contact',
        component: Contact,
        meta: {
            title: 'Contact',
            description: 'Contactez Océane Torres photographe pour réserver votre séance photo à Saint-Étienne, Lyon, Rive-de-Gier, Givors, Saint-Chamond. Intervention Loire, Rhône, Auvergne-Rhône-Alpes.'
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
    {
        path: '/cgv',
        name: 'cgv',
        component: CGV,
        meta: {
            title: 'Conditions Générales de Vente',
            description: 'Conditions Générales de Vente du site Océane Torres Photographie.'
        }
    },
    {
        path: '/politique-confidentialite',
        name: 'politique-confidentialite',
        component: PolitiqueConfidentialite,
        meta: {
            title: 'Politique de Confidentialité',
            description: 'Politique de confidentialité et protection des données personnelles - Océane Torres Photographie.'
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
    // Event galleries (public)
    {
        path: '/evenements',
        name: 'events',
        component: Events,
        meta: {
            title: 'Galeries d\'événements',
            description: 'Découvrez les galeries photos des événements capturés par Océane Torres. Mariages, baptêmes, anniversaires et célébrations en Loire et Lyon.'
        }
    },
    {
        path: '/evenements/:id',
        name: 'event-gallery',
        component: EventGallery,
        meta: {
            title: 'Galerie Événement',
            description: 'Galerie photos d\'événement par Océane Torres Photographie.'
        }
    },
    // Cart & Checkout
    {
        path: '/panier',
        name: 'cart',
        component: Cart,
        meta: {
            title: 'Mon Panier',
            robots: 'noindex, nofollow'
        }
    },
    {
        path: '/checkout',
        name: 'checkout',
        component: Checkout,
        meta: {
            title: 'Finaliser la commande',
            robots: 'noindex, nofollow'
        }
    },
    {
        path: '/commande/confirmation',
        name: 'order-confirmation-legacy',
        redirect: to => {
            // Redirect SumUp callback to proper order page
            const orderId = to.query.order as string
            if (orderId) {
                return { name: 'order-confirmation', params: { id: orderId }, query: to.query }
            }
            return { name: 'home' }
        }
    },
    {
        path: '/commande/:id',
        name: 'order-confirmation',
        component: OrderConfirmation,
        meta: {
            title: 'Confirmation de commande',
            robots: 'noindex, nofollow'
        }
    },
    // Account routes (client)
    {
        path: '/mon-compte',
        name: 'account',
        component: AccountDashboard,
        meta: {
            title: 'Mon compte',
            requiresClientAuth: true,
            robots: 'noindex, nofollow'
        }
    },
    {
        path: '/mon-compte/commande/:id',
        name: 'account-order',
        component: AccountOrderDetail,
        meta: {
            title: 'Détail commande',
            requiresClientAuth: true,
            robots: 'noindex, nofollow'
        }
    },
    // Admin routes - section isolée avec layout dédié
    {
        path: '/admin',
        component: AdminRoot,
        meta: {requiresAdmin: true},
        children: [
            {
                path: '',
                name: 'admin-dashboard',
                component: AdminDashboard,
                meta: {title: 'Dashboard'}
            },
            {
                path: 'galleries',
                name: 'admin-galleries',
                component: AdminGalleries,
                meta: {title: 'Galeries'}
            },
            {
                path: 'clients',
                name: 'admin-clients',
                component: AdminClients,
                meta: {title: 'Clients'}
            },
            {
                path: 'prestations',
                name: 'admin-prestations',
                component: AdminPrestations,
                meta: {title: 'Prestations'}
            },
            {
                path: 'reservations',
                name: 'admin-reservations',
                component: AdminReservations,
                meta: {title: 'Réservations'}
            },
            {
                path: 'gift-cards',
                name: 'admin-gift-cards',
                component: AdminGiftCards,
                meta: {title: 'Bons Cadeaux'}
            },
            {
                path: 'events',
                name: 'admin-events',
                component: AdminEventGalleries,
                meta: {title: 'Galeries Événements'}
            },
            {
                path: 'orders',
                name: 'admin-orders',
                component: AdminOrders,
                meta: {title: 'Commandes'}
            },
        ]
    },
    // 404 - Page non trouvée
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFound,
        meta: {
            title: 'Page non trouvée',
            robots: 'noindex, nofollow'
        }
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

// Auth guard for protected routes only
router.beforeEach(async (to, _from, next) => {
    // Check meta on matched routes (handles nested routes inheritance)
    const requiresAdmin = to.matched.some(record => record.meta.requiresAdmin)
    const requiresClientAuth = to.matched.some(record => record.meta.requiresClientAuth)

    // Public routes - no auth check needed, proceed immediately
    if (!requiresAdmin && !requiresClientAuth) {
        next()
        return
    }

    // Protected routes - wait for auth initialization
    const authStore = useAuthStore()
    if (!authStore.isInitialized) {
        await authStore.initialize()
    }

    // Admin auth guard - requires authentication AND admin role
    if (requiresAdmin) {
        if (!authStore.isAuthenticated) {
            next({name: 'home', query: {login: 'true', redirect: to.fullPath}})
            return
        }

        // User is authenticated but not admin - redirect to account
        if (!authStore.isAdmin) {
            next({name: 'account'})
            return
        }
    }

    // Client auth guard
    if (requiresClientAuth) {
        if (!authStore.isAuthenticated) {
            next({name: 'home', query: {login: 'true', redirect: to.fullPath}})
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

    // Track page view in Google Analytics (uniquement si consentement analytics donné)
    const consentStore = useConsentStore()
    if (typeof window !== 'undefined' && window.dataLayer && consentStore.analyticsEnabled) {
        gtag('event', 'page_view', {
            page_title: document.title,
            page_location: window.location.href,
            page_path: to.path
        })
    }

    // Update meta description
    if (pageDescription) {
        const metaDescription = document.querySelector('meta[name="description"]')
        if (metaDescription) {
            metaDescription.setAttribute('content', pageDescription)
        }

        // Also update OG description
        const ogDescription = document.querySelector('meta[property="og:description"]')
        if (ogDescription) {
            ogDescription.setAttribute('content', pageDescription)
        }
    }

    // Update canonical URL
    const canonical = document.querySelector('link[rel="canonical"]')
    if (canonical) {
        canonical.setAttribute('href', `https://oceanetorresphotographie.fr${to.path}`)
    }

    // Update OG and Twitter meta tags
    const updateMeta = (selector: string, attr: string, content: string) => {
        const el = document.querySelector(selector)
        if (el) el.setAttribute(attr, content)
    }
    updateMeta('meta[property="og:title"]', 'content', pageTitle || 'Océane Torres Photographe')
    updateMeta('meta[property="og:url"]', 'content', `https://oceanetorresphotographie.fr${to.path}`)
    updateMeta('meta[name="twitter:title"]', 'content', pageTitle || 'Océane Torres Photographe')
    updateMeta('meta[name="twitter:description"]', 'content', pageDescription || '')

    // Update robots meta tag
    const robotsContent = to.meta.robots as string | undefined
    const robotsMeta = document.querySelector('meta[name="robots"]')
    if (robotsMeta) {
        robotsMeta.setAttribute('content', robotsContent || 'index, follow')
    }
})

export default router
