import type { App } from 'vue'
import type { Router } from 'vue-router'

function stripQuery(url: string): string {
    return url.split('?')[0].split('#')[0]
}

/**
 * Import dynamique conditionnel : sans VITE_SENTRY_DSN au build, le SDK est
 * absent du bundle. Les URL sont nettoyées de leur query string avant envoi
 * (jetons de galerie, URL signées MinIO). Cf. docs/supervision.md §5.3.
 */
export async function initErrorMonitoring(app: App, router: Router): Promise<void> {
    const dsn = import.meta.env.VITE_SENTRY_DSN

    if (!dsn) {
        return
    }

    try {
        const Sentry = await import('@sentry/vue')

        Sentry.init({
            app,
            dsn,
            release: __APP_VERSION__,
            environment: import.meta.env.MODE,

            tracesSampleRate: 0,
            sendDefaultPii: false,

            beforeSend(event) {
                delete event.user

                if (event.request?.url) {
                    event.request.url = stripQuery(event.request.url)
                }

                return event
            },

            beforeBreadcrumb(breadcrumb) {
                // Peuvent contenir des réponses d'API ou des emails clients.
                if (breadcrumb.category === 'console') {
                    return null
                }

                if (typeof breadcrumb.data?.url === 'string') {
                    breadcrumb.data.url = stripQuery(breadcrumb.data.url)
                }

                return breadcrumb
            },
        })

        router.afterEach((to) => {
            Sentry.setTag('route', String(to.name ?? to.path))
        })
    } catch (error) {
        // eslint-disable-next-line no-console
        console.warn('Monitoring désactivé :', error)
    }
}
