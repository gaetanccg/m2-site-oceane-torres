/**
 * Configuration du routeur Vue Router
 * Utilise le lazy loading pour optimiser les performances
 */

import {createRouter, createWebHistory, type RouteRecordRaw} from 'vue-router'

// Home est chargé immédiatement car c'est la page d'accueil
import Home from '@/views/Home.vue'

// Lazy loading pour les autres pages
const Portfolio = () => import('@/views/Portfolio.vue')
const Prestations = () => import('@/views/Prestations.vue')
const BonsCadeaux = () => import('@/views/BonsCadeaux.vue')
const About = () => import('@/views/About.vue')
const Contact = () => import('@/views/Contact.vue')
const MentionsLegales = () => import('@/views/MentionsLegales.vue')

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'home',
        component: Home,
        meta: {title: 'Accueil'}
    },
    {
        path: '/portfolio',
        name: 'portfolio',
        component: Portfolio,
        meta: {title: 'Portfolio'}
    },
    {
        path: '/prestations',
        name: 'prestations',
        component: Prestations,
        meta: {title: 'Prestations'}
    },
    {
        path: '/bons',
        name: 'bons',
        component: BonsCadeaux,
        meta: {title: 'Bons Cadeaux'}
    },
    {
        path: '/a-propos',
        name: 'about',
        component: About,
        meta: {title: 'À propos'}
    },
    {
        path: '/contact',
        name: 'contact',
        component: Contact,
        meta: {title: 'Contact'}
    },
    {
        path: '/mentions-legales',
        name: 'mentions-legales',
        component: MentionsLegales,
        meta: {title: 'Mentions légales'}
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

// Update document title on navigation
router.afterEach((to) => {
    const baseTitle = 'Océane Torres Photographie'
    const pageTitle = to.meta.title as string | undefined
    document.title = pageTitle ? `${pageTitle} | ${baseTitle}` : baseTitle
})

export default router
