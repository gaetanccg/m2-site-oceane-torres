/**
 * Éprouve qu'aucune requête ne part vers un tiers AVANT consentement.
 *
 * Le RGPD n'interdit pas les vidéos YouTube : il interdit de solliciter Google avant que
 * le visiteur ait accepté. Or une vignette `img.youtube.com` est une requête à Google, qui
 * emporte l'adresse IP et les cookies Google déjà présents — même si l'iframe, elle, est
 * bien derrière un clic.
 *
 * Ce script charge les pages publiques avec un stockage local VIDE (donc aucun
 * consentement) et échoue si une requête sort vers un hôte tiers.
 *
 * Usage : npm run build && node scripts/check-privacy.js
 */

import puppeteer from 'puppeteer'
import http from 'http'
import fs from 'fs/promises'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const distDir = path.resolve(__dirname, '../dist')
const PORT = 4569

/** Les pages publiques susceptibles d'intégrer un tiers. */
const ROUTES = ['/', '/portfolio', '/prestations', '/contact', '/evenements', '/bons', '/panier']

/**
 * Charger la page ne suffit pas : le portfolio n'affiche PAS les vidéos tant qu'aucun
 * filtre n'est actif — elles n'apparaissent qu'après un clic sur la catégorie. Le contrôle
 * doit donc parcourir les filtres, sinon il passe à côté du seul endroit où une vignette
 * YouTube est demandée. Constaté : sans ce parcours, le contrôle validait une page qui
 * sollicitait bien Google.
 */
const PARCOURS_FILTRES = ['/portfolio']

/**
 * Tiers dont AUCUNE sollicitation n'est justifiable avant consentement.
 * Google Fonts y figure : les polices sont auto-hébergées depuis la phase 2, une requête
 * vers googleapis signalerait une régression.
 */
const INTERDITS = [
    { motif: /(^|\.)youtube\.com$/, qui: 'YouTube (Google)' },
    { motif: /(^|\.)youtube-nocookie\.com$/, qui: 'YouTube nocookie (Google)' },
    { motif: /(^|\.)ytimg\.com$/, qui: 'YouTube images (Google)' },
    { motif: /(^|\.)doubleclick\.net$/, qui: 'DoubleClick (Google)' },
    { motif: /(^|\.)fonts\.googleapis\.com$/, qui: 'Google Fonts' },
    { motif: /(^|\.)fonts\.gstatic\.com$/, qui: 'Google Fonts (fichiers)' },
    { motif: /(^|\.)facebook\.(com|net)$/, qui: 'Meta' },
    { motif: /(^|\.)sumup\.com$/, qui: 'SumUp' },
]

/**
 * Toléré, et c'est un CHOIX assumé, pas un oubli : gtag.js est chargé en Consent Mode v2
 * avec tout en « denied » posé AVANT le script (cf. stores/consent.ts). Google envoie alors
 * un ping sans cookie, ce qu'il présente comme conforme.
 *
 * ⚠️ La CNIL est plus stricte : même sans cookie, ce ping transmet l'adresse IP à Google.
 * Trancher relève d'Océane, pas du code — ne loger aucun ping avant consentement ferait
 * perdre la modélisation de Consent Mode. La décision est consignée dans le plan.
 *
 * Ce que ce script vérifie donc ici, et qui n'est PAS contestable : aucun cookie
 * d'analytique ne doit être posé avant consentement.
 */
const TOLERES = [
    { motif: /(^|\.)google-analytics\.com$/, qui: 'Google Analytics (Consent Mode v2)' },
    { motif: /(^|\.)googletagmanager\.com$/, qui: 'Google Tag Manager (Consent Mode v2)' },
]

const COOKIES_INTERDITS = /^(_ga|_gid|_gac|_gcl|IDE|test_cookie)/

const MIME = {
    '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css',
    '.json': 'application/json', '.svg': 'image/svg+xml', '.png': 'image/png',
    '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.avif': 'image/avif',
    '.ico': 'image/x-icon', '.woff2': 'font/woff2', '.txt': 'text/plain',
}

function serve() {
    const server = http.createServer(async (req, res) => {
        const url = new URL(req.url, `http://localhost:${PORT}`)
        let filePath = path.join(distDir, url.pathname)

        try {
            const stat = await fs.stat(filePath).catch(() => null)
            if (!stat || stat.isDirectory()) filePath = path.join(distDir, 'index.html')

            const body = await fs.readFile(filePath)
            res.setHeader('Content-Type', MIME[path.extname(filePath)] ?? 'application/octet-stream')
            res.writeHead(200)
            res.end(body)
        } catch {
            res.writeHead(404)
            res.end('not found')
        }
    })

    return new Promise((resolve) => server.listen(PORT, () => resolve(server)))
}

function classer(url) {
    let hote
    try {
        hote = new URL(url).hostname
    } catch {
        return null
    }

    const interdit = INTERDITS.find((t) => t.motif.test(hote))
    if (interdit) return { qui: interdit.qui, tolere: false }

    const tolere = TOLERES.find((t) => t.motif.test(hote))
    if (tolere) return { qui: tolere.qui, tolere: true }

    return null
}

async function main() {
    try {
        await fs.access(distDir)
    } catch {
        console.error("dist/ absent. Lancer « npm run build » d'abord.")
        process.exit(1)
    }

    const server = await serve()
    const browser = await puppeteer.launch({ args: ['--no-sandbox'] })
    const fuites = []
    const cookiesPoses = []
    const toleres = new Set()

    for (const route of ROUTES) {
        // Contexte neuf : aucun consentement stocke, aucun cookie herite.
        const contexte = await browser.createBrowserContext()
        const page = await contexte.newPage()

        page.on('request', (requete) => {
            const verdict = classer(requete.url())
            if (!verdict) return
            if (verdict.tolere) {
                toleres.add(verdict.qui)

                return
            }
            fuites.push({ route, qui: verdict.qui, url: requete.url().slice(0, 100) })
        })

        await page.goto(`http://localhost:${PORT}${route}`, { waitUntil: 'networkidle2', timeout: 30000 })
        // Laisse partir les images differees et les scripts asynchrones.
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
        await new Promise((r) => setTimeout(r, 800))

        if (PARCOURS_FILTRES.includes(route)) {
            const filtres = await page.$$('button')
            for (const filtre of filtres) {
                const libelle = (await filtre.evaluate((el) => el.textContent ?? '')).trim()
                if (libelle === '' || libelle.length > 24) continue
                await filtre.click().catch(() => {})
                await new Promise((r) => setTimeout(r, 400))
            }
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
            await new Promise((r) => setTimeout(r, 600))
        }

        for (const cookie of await page.cookies()) {
            if (COOKIES_INTERDITS.test(cookie.name)) {
                cookiesPoses.push({ route, nom: cookie.name, domaine: cookie.domain })
            }
        }

        const surCetteRoute = fuites.filter((f) => f.route === route).length
            + cookiesPoses.filter((c) => c.route === route).length
        console.log(`  ${surCetteRoute === 0 ? '✓' : '✗'} ${route}${surCetteRoute ? ` — ${surCetteRoute} manquement(s)` : ''}`)
        await page.close()
        await contexte.close()
    }

    await browser.close()
    server.close()
    console.log()

    if (toleres.size > 0) {
        console.log('Toléré (choix assumé, cf. plan-refonte.md) :')
        toleres.forEach((qui) => console.log(`  · ${qui}`))
        console.log()
    }

    if (fuites.length === 0 && cookiesPoses.length === 0) {
        console.log('✅ Aucun tiers interdit sollicité, aucun cookie d\'analytique posé avant consentement.')
        process.exit(0)
    }

    if (fuites.length > 0) {
        console.error(`✖ ${fuites.length} requête(s) vers un tiers interdit, sans consentement :`)
        const parQui = {}
        fuites.forEach((f) => {
            parQui[f.qui] = parQui[f.qui] ?? { routes: new Set(), exemple: f.url }
            parQui[f.qui].routes.add(f.route)
        })
        Object.entries(parQui).forEach(([qui, d]) => {
            console.error(`  ${qui}`)
            console.error(`    sur ${[...d.routes].join(', ')}`)
            console.error(`    ex. ${d.exemple}`)
        })
    }

    if (cookiesPoses.length > 0) {
        console.error(`✖ ${cookiesPoses.length} cookie(s) d'analytique posé(s) avant consentement :`)
        cookiesPoses.forEach((c) => console.error(`  ${c.nom} (${c.domaine}) sur ${c.route}`))
    }

    process.exit(1)
}

main().catch((e) => {
    console.error(e)
    process.exit(1)
})
