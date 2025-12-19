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

// Admin pages (lazy loading)
const AdminLogin = () => import('@/views/admin/Login.vue')
const AdminDashboard = () => import('@/views/admin/Dashboard.vue')
const AdminReservations = () => import('@/views/admin/Reservations.vue')
const AdminClients = () => import('@/views/admin/Clients.vue')
const AdminPrestations = () => import('@/views/admin/Prestations.vue')
const AdminGalleries = () => import('@/views/admin/Galleries.vue')
const AdminFactures = () => import('@/views/admin/Factures.vue')
const AdminGiftCards = () => import('@/views/admin/GiftCards.vue')

const routes: RouteRecordRaw[] = [
    // ============================================================================
    // Public routes
    // ============================================================================
    {
        path: '/',
        name: 'home',
        component: Home,
        meta: {title: 'Accueil', layout: 'public'}
    },
    {
        path: '/portfolio',
        name: 'portfolio',
        component: Portfolio,
        meta: {title: 'Portfolio', layout: 'public'}
    },
    {
        path: '/prestations',
        name: 'prestations',
        component: Prestations,
        meta: {title: 'Prestations', layout: 'public'}
    },
    {
        path: '/bons',
        name: 'bons',
        component: BonsCadeaux,
        meta: {title: 'Bons Cadeaux', layout: 'public'}
    },
    {
        path: '/a-propos',
        name: 'about',
        component: About,
        meta: {title: 'À propos', layout: 'public'}
    },
    {
        path: '/contact',
        name: 'contact',
        component: Contact,
        meta: {title: 'Contact', layout: 'public'}
    },
    {
        path: '/mentions-legales',
        name: 'mentions-legales',
        component: MentionsLegales,
        meta: {title: 'Mentions légales', layout: 'public'}
    },

    // ============================================================================
    // Admin routes
    // ============================================================================
    {
        path: '/admin/login',
        name: 'admin-login',
        component: AdminLogin,
        meta: {title: 'Connexion Admin', layout: 'none'}
    },
    {
        path: '/admin',
        name: 'admin-dashboard',
        component: AdminDashboard,
        meta: {title: 'Tableau de bord', layout: 'admin', requiresAuth: true}
    },
    {
        path: '/admin/reservations',
        name: 'admin-reservations',
        component: AdminReservations,
        meta: {title: 'Réservations', layout: 'admin', requiresAuth: true}
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
        path: '/admin/galleries',
        name: 'admin-galleries',
        component: AdminGalleries,
        meta: {title: 'Galeries', layout: 'admin', requiresAuth: true}
    },
    {
        path: '/admin/factures',
        name: 'admin-factures',
        component: AdminFactures,
        meta: {title: 'Factures', layout: 'admin', requiresAuth: true}
    },
    {
        path: '/admin/gift-cards',
        name: 'admin-gift-cards',
        component: AdminGiftCards,
        meta: {title: 'Bons Cadeaux', layout: 'admin', requiresAuth: true}
    },

    // ============================================================================
    // Catch-all redirect to home
    // ============================================================================
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

// Navigation guard for admin routes
router.beforeEach(async (to, _from, next) => {
    const requiresAuth = to.meta.requiresAuth

    if (requiresAuth) {
        const authStore = useAuthStore()

        // Check if user is authenticated
        if (!authStore.token) {
            return next({name: 'admin-login', query: {redirect: to.fullPath}})
        }

        // Verify token is still valid
        if (!authStore.user) {
            const isValid = await authStore.checkAuth()
            if (!isValid) {
                return next({name: 'admin-login', query: {redirect: to.fullPath}})
            }
        }

        // Check admin role
        if (!authStore.isAdmin) {
            return next({name: 'admin-login'})
        }
    }

    // Redirect to dashboard if already authenticated and trying to access login
    if (to.name === 'admin-login') {
        const authStore = useAuthStore()
        if (authStore.isAuthenticated && authStore.isAdmin) {
            return next({name: 'admin-dashboard'})
        }
    }

    next()
})

// Update document title on navigation
router.afterEach((to) => {
    const baseTitle = 'Océane Torres Photographie'
    const pageTitle = to.meta.title as string | undefined

    if (to.meta.layout === 'admin') {
        document.title = pageTitle ? `${pageTitle} | Admin` : 'Admin'
    } else {
        document.title = pageTitle ? `${pageTitle} | ${baseTitle}` : baseTitle
    }
})

export default router
