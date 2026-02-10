/**
 * Store de gestion du consentement cookies (RGPD)
 * Utilise Google Consent Mode v2 pour la conformité RGPD
 *
 * Le script GA4 se charge immédiatement mais en mode "denied"
 * jusqu'à ce que l'utilisateur donne son consentement explicite.
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface ConsentPreferences {
    essential: boolean      // Toujours true, non modifiable
    analytics: boolean      // Google Analytics
    marketing: boolean      // Pixels publicitaires, remarketing
    consentedAt: string | null
    consentVersion: string
}

const CONSENT_STORAGE_KEY = 'cookie_consent'
const CONSENT_VERSION = '1.0'
const GA4_ID = 'G-9BK5CTP2YR'

// Flag pour éviter de charger GA4 plusieurs fois
let ga4Loaded = false

/**
 * Fonction gtag standard — DOIT utiliser `arguments` (pas de rest params)
 * car gtag.js distingue les objets Arguments des Arrays lors du traitement
 * du dataLayer. Avec des rest params (...args), les commandes sont ignorées.
 */
function gtag(..._args: unknown[]) {
    // eslint-disable-next-line prefer-rest-params
    window.dataLayer.push(arguments)
}

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
    function loadFromStorage(): boolean {
        try {
            const stored = localStorage.getItem(CONSENT_STORAGE_KEY)
            if (stored) {
                const parsed = JSON.parse(stored) as ConsentPreferences
                if (parsed.consentVersion === CONSENT_VERSION && parsed.consentedAt) {
                    preferences.value = { ...parsed, essential: true }
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

    /**
     * Initialise GA4 avec Consent Mode v2
     * Charge le script immédiatement mais en mode "denied" par défaut
     */
    function initializeGA4() {
        if (ga4Loaded || typeof window === 'undefined') return
        ga4Loaded = true

        // Initialiser dataLayer
        window.dataLayer = window.dataLayer || []

        // Configurer le consent mode par défaut AVANT de charger le script
        gtag('consent', 'default', {
            'analytics_storage': 'denied',
            'ad_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied',
            'wait_for_update': 500
        })

        // Initialiser gtag
        gtag('js', new Date())
        gtag('config', GA4_ID, {
            'anonymize_ip': true,
            'send_page_view': false  // Géré manuellement après consentement et par le router
        })

        // Charger le script gtag.js
        const script = document.createElement('script')
        script.async = true
        script.src = `https://www.googletagmanager.com/gtag/js?id=${GA4_ID}`
        document.head.appendChild(script)
    }

    /**
     * Met à jour le consentement dans GA4
     */
    function updateGA4Consent() {
        if (typeof window === 'undefined') return

        gtag('consent', 'update', {
            'analytics_storage': preferences.value.analytics ? 'granted' : 'denied',
            'ad_storage': preferences.value.marketing ? 'granted' : 'denied',
            'ad_user_data': preferences.value.marketing ? 'granted' : 'denied',
            'ad_personalization': preferences.value.marketing ? 'granted' : 'denied'
        })
    }

    /**
     * Envoie un page_view immédiat après activation du consentement analytics.
     * Sans cela, GA4 ne crée jamais de session car le page_view initial
     * est envoyé avant consentement et donc ignoré.
     */
    function sendPageViewAfterConsent() {
        if (typeof window === 'undefined' || !preferences.value.analytics) return

        gtag('event', 'page_view', {
            page_title: document.title,
            page_location: window.location.href,
            page_path: window.location.pathname
        })
    }

    function initialize() {
        // Charger GA4 immédiatement (en mode denied par défaut)
        initializeGA4()

        // Charger les préférences stockées
        const hasStoredConsent = loadFromStorage()

        if (hasStoredConsent) {
            // Appliquer le consentement stocké et envoyer le premier page_view
            updateGA4Consent()
            sendPageViewAfterConsent()
        } else {
            // Afficher le bandeau si pas de consentement
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
        updateGA4Consent()
        sendPageViewAfterConsent()
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
        updateGA4Consent()
        removeTrackingCookies()
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
        updateGA4Consent()
        sendPageViewAfterConsent()

        // Supprimer les cookies si refusé
        if (!analytics) {
            removeTrackingCookies()
        }

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
        updateGA4Consent()
        removeTrackingCookies()
        showBanner.value = true
    }

    function removeTrackingCookies() {
        const cookiesToRemove = [
            '_ga', '_gid', '_gat', '_gtag',
            '__utma', '__utmb', '__utmc', '__utmz',
            '_gcl_au', '_fbp', '_fbc'
        ]

        const ga4Pattern = new RegExp(`^_ga_`)
        const allCookies = document.cookie.split(';')

        allCookies.forEach(cookie => {
            const cookieName = cookie.split('=')[0].trim()
            if (cookiesToRemove.includes(cookieName) || ga4Pattern.test(cookieName)) {
                const domains = [
                    window.location.hostname,
                    '.' + window.location.hostname,
                    '.oceanetorresphotographie.fr'
                ]

                domains.forEach(domain => {
                    document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=${domain}`
                })
            }
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
        dataLayer: IArguments[]
    }
}
