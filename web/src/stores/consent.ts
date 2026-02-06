/**
 * Store de gestion du consentement cookies (RGPD)
 * Gère le stockage local et le chargement conditionnel des scripts tiers
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface ConsentPreferences {
    essential: boolean      // Toujours true, non modifiable
    analytics: boolean      // Google Analytics / GTM analytics
    marketing: boolean      // Pixels publicitaires, remarketing
    consentedAt: string | null
    consentVersion: string
}

const CONSENT_STORAGE_KEY = 'cookie_consent'
const CONSENT_VERSION = '1.0'
const GA4_ID = 'G-9BK5CTP2YR'

export const useConsentStore = defineStore('consent', () => {
    // State
    const preferences = ref<ConsentPreferences>({
        essential: true,
        analytics: false,
        marketing: false,
        consentedAt: null,
        consentVersion: CONSENT_VERSION
    })
    const showBanner = ref(false)
    const showSettings = ref(false)

    // Getters
    const hasConsented = computed(() => preferences.value.consentedAt !== null)
    const analyticsEnabled = computed(() => preferences.value.analytics)
    const marketingEnabled = computed(() => preferences.value.marketing)

    // Actions
    function loadFromStorage() {
        try {
            const stored = localStorage.getItem(CONSENT_STORAGE_KEY)
            if (stored) {
                const parsed = JSON.parse(stored) as ConsentPreferences
                // Vérifier si la version du consentement est à jour
                if (parsed.consentVersion === CONSENT_VERSION && parsed.consentedAt) {
                    preferences.value = { ...parsed, essential: true }
                    applyConsent()
                    return true
                }
            }
        } catch {
            // Ignorer les erreurs de parsing
        }
        return false
    }

    function saveToStorage() {
        try {
            localStorage.setItem(CONSENT_STORAGE_KEY, JSON.stringify(preferences.value))
        } catch {
            // Ignorer les erreurs de stockage
        }
    }

    function initialize() {
        const hasStoredConsent = loadFromStorage()
        if (!hasStoredConsent) {
            showBanner.value = true
        }
    }

    function acceptAll() {
        preferences.value = {
            essential: true,
            analytics: true,
            marketing: true,
            consentedAt: new Date().toISOString(),
            consentVersion: CONSENT_VERSION
        }
        saveToStorage()
        applyConsent()
        showBanner.value = false
        showSettings.value = false
    }

    function rejectAll() {
        preferences.value = {
            essential: true,
            analytics: false,
            marketing: false,
            consentedAt: new Date().toISOString(),
            consentVersion: CONSENT_VERSION
        }
        saveToStorage()
        applyConsent()
        showBanner.value = false
        showSettings.value = false
    }

    function savePreferences(analytics: boolean, marketing: boolean) {
        preferences.value = {
            essential: true,
            analytics,
            marketing,
            consentedAt: new Date().toISOString(),
            consentVersion: CONSENT_VERSION
        }
        saveToStorage()
        applyConsent()
        showBanner.value = false
        showSettings.value = false
    }

    function openSettings() {
        showSettings.value = true
        showBanner.value = false
    }

    function closeSettings() {
        showSettings.value = false
        if (!hasConsented.value) {
            showBanner.value = true
        }
    }

    function revokeConsent() {
        localStorage.removeItem(CONSENT_STORAGE_KEY)
        preferences.value = {
            essential: true,
            analytics: false,
            marketing: false,
            consentedAt: null,
            consentVersion: CONSENT_VERSION
        }
        // Supprimer les cookies GTM/Analytics
        removeTrackingCookies()
        showBanner.value = true
    }

    function applyConsent() {
        if (preferences.value.analytics) {
            loadGA4()
        } else {
            // S'assurer que GA4 n'envoie pas de données
            disableGA4()
        }
    }

    function loadGA4() {
        // Vérifier si gtag.js est déjà chargé
        if (document.querySelector(`script[src*="googletagmanager.com/gtag/js"]`)) {
            // GA4 déjà chargé, mettre à jour le consentement
            if (window.gtag) {
                window.gtag('consent', 'update', {
                    'analytics_storage': 'granted',
                    'ad_storage': preferences.value.marketing ? 'granted' : 'denied'
                })
            }
            return
        }

        // Initialiser dataLayer
        window.dataLayer = window.dataLayer || []

        // Définir gtag function
        window.gtag = function(...args: unknown[]) {
            window.dataLayer.push(args)
        }

        // Configurer le consent mode par défaut AVANT de charger le script
        window.gtag('consent', 'default', {
            'analytics_storage': 'granted',
            'ad_storage': preferences.value.marketing ? 'granted' : 'denied'
        })

        // Initialiser gtag
        window.gtag('js', new Date())
        window.gtag('config', GA4_ID, {
            'anonymize_ip': true,
            'send_page_view': true
        })

        // Charger le script gtag.js
        const script = document.createElement('script')
        script.async = true
        script.src = `https://www.googletagmanager.com/gtag/js?id=${GA4_ID}`
        document.head.appendChild(script)
    }

    function disableGA4() {
        if (window.gtag) {
            window.gtag('consent', 'update', {
                'analytics_storage': 'denied',
                'ad_storage': 'denied'
            })
        }
    }

    function removeTrackingCookies() {
        // Liste des cookies Google à supprimer
        const cookiesToRemove = [
            '_ga', '_gid', '_gat', '_gtag',
            '__utma', '__utmb', '__utmc', '__utmz',
            '_gcl_au', '_fbp', '_fbc'
        ]

        cookiesToRemove.forEach(cookieName => {
            // Supprimer pour le domaine actuel et ses sous-domaines
            const domains = [
                window.location.hostname,
                '.' + window.location.hostname,
                '.oceanetorresphotographie.fr'
            ]

            domains.forEach(domain => {
                document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=${domain}`
            })
        })
    }

    return {
        // State
        preferences,
        showBanner,
        showSettings,
        // Getters
        hasConsented,
        analyticsEnabled,
        marketingEnabled,
        // Actions
        initialize,
        acceptAll,
        rejectAll,
        savePreferences,
        openSettings,
        closeSettings,
        revokeConsent
    }
})

// Types pour window
declare global {
    interface Window {
        dataLayer: unknown[]
        gtag: (...args: unknown[]) => void
    }
}
