/**
 * Éprouve la Content-Security-Policy de render.yaml contre le site réellement construit.
 *
 * Une CSP en Report-Only ne protège rien : il faut l'activer. Mais une directive trop
 * stricte casse le site sans laisser d'erreur côté serveur. Ce script sert dist/ avec la
 * politique appliquée pour de vrai, parcourt les pages, et remonte chaque violation.
 *
 * Ce qu'il NE couvre pas : SumUp, Google Analytics et Sentry ne sont pas sollicités en
 * local (pas de clés, pas de paiement). Leurs hôtes restent donc à vérifier dans un
 * navigateur sur la préproduction.
 *
 * Usage : npm run build && node scripts/check-csp.js
 */

import puppeteer from 'puppeteer'
import http from 'http'
import fs from 'fs/promises'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const distDir = path.resolve(__dirname, '../dist')
const renderConfig = path.resolve(__dirname, '../../render.yaml')
const PORT = 4568

const ROUTES = [
    '/',
    '/portfolio',
    '/prestations',
    '/bons',
    '/contact',
    '/evenements',
    '/gallery',
    '/mot-de-passe-oublie',
    '/reinitialiser-mot-de-passe?token=factice&email=test%40exemple.fr',
    '/panier',
]

const MIME = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.webp': 'image/webp',
    '.ico': 'image/x-icon',
    '.woff2': 'font/woff2',
    '.txt': 'text/plain',
}

/** La politique est lue depuis render.yaml : le script ne peut pas en tester une autre. */
async function readPolicy() {
    const raw = await fs.readFile(renderConfig, 'utf-8')
    const lines = raw.split('\n')
    const start = lines.findIndex((l) => l.includes('Content-Security-Policy-Report-Only'))

    if (start === -1) {
        throw new Error('Aucun en-tête Content-Security-Policy-Report-Only dans render.yaml.')
    }

    const valueLine = lines.findIndex((l, i) => i > start && l.trim().startsWith('value:'))
    const collected = []

    for (let i = valueLine + 1; i < lines.length; i++) {
        const line = lines[i]
        if (line.trim() === '' || /^\s{0,6}\S/.test(line)) break
        collected.push(line.trim())
    }

    return collected.join(' ').replace(/\s+/g, ' ').trim()
}

async function localApiOrigin() {
    for (const fichier of ['../.env', '../.env.example']) {
        try {
            const raw = await fs.readFile(path.resolve(__dirname, fichier), 'utf-8')
            const ligne = raw.split('\n').find((l) => l.startsWith('VITE_API_URL='))
            if (ligne) return new URL(ligne.slice('VITE_API_URL='.length).trim()).origin
        } catch {
            // fichier absent : on continue
        }
    }

    return null
}

function serve(policy) {
    const server = http.createServer(async (req, res) => {
        const url = new URL(req.url, `http://localhost:${PORT}`)
        let filePath = path.join(distDir, url.pathname)

        try {
            const stat = await fs.stat(filePath).catch(() => null)
            if (!stat || stat.isDirectory()) {
                filePath = path.join(distDir, 'index.html')
            }

            const body = await fs.readFile(filePath)
            const ext = path.extname(filePath)

            res.setHeader('Content-Type', MIME[ext] ?? 'application/octet-stream')
            // Appliquée, pas en observation : c'est tout l'objet du script.
            res.setHeader('Content-Security-Policy', policy)
            res.writeHead(200)
            res.end(body)
        } catch {
            res.writeHead(404)
            res.end('not found')
        }
    })

    return new Promise((resolve) => server.listen(PORT, () => resolve(server)))
}

async function main() {
    try {
        await fs.access(distDir)
    } catch {
        console.error('dist/ absent. Lancer « npm run build » d\'abord.')
        process.exit(1)
    }

    let policy = await readPolicy()

    // Le build local pointe VITE_API_URL sur localhost, la politique sur le domaine de
    // production. On substitue pour eprouver la STRUCTURE de la politique et non les URL
    // de cet environnement.
    const apiOrigin = await localApiOrigin()
    if (apiOrigin && !policy.includes(apiOrigin)) {
        policy = policy.replace('connect-src ', `connect-src ${apiOrigin} `)
        console.log(`Origine d'API locale ajoutée pour l'épreuve : ${apiOrigin}\n`)
    }

    console.log('Politique éprouvée :\n  ' + policy.replace(/; /g, ';\n  ') + '\n')

    const server = await serve(policy)
    const browser = await puppeteer.launch({ args: ['--no-sandbox'] })
    const violations = []

    for (const route of ROUTES) {
        const page = await browser.newPage()

        await page.evaluateOnNewDocument(() => {
            window.__cspViolations = []
            document.addEventListener('securitypolicyviolation', (e) => {
                window.__cspViolations.push({
                    directive: e.effectiveDirective,
                    blocked: e.blockedURI,
                })
            })
        })

        page.on('pageerror', (e) => violations.push({ route, directive: 'pageerror', blocked: e.message.slice(0, 120) }))

        await page.goto(`http://localhost:${PORT}${route}`, { waitUntil: 'networkidle2', timeout: 30000 })
        await new Promise((r) => setTimeout(r, 500))

        const found = await page.evaluate(() => window.__cspViolations ?? [])
        found.forEach((v) => violations.push({ route, ...v }))

        console.log(`  ${found.length === 0 ? '✓' : '✗'} ${route}${found.length ? ` — ${found.length} violation(s)` : ''}`)
        await page.close()
    }

    await browser.close()
    server.close()

    console.log()

    if (violations.length === 0) {
        console.log('✅ Aucune violation sur les routes parcourues.')
        console.log('   SumUp, Google Analytics et Sentry restent à vérifier sur la préproduction.')
        process.exit(0)
    }

    console.error(`✖ ${violations.length} violation(s) :`)
    const parDirective = {}
    violations.forEach((v) => {
        const cle = `${v.directive} ← ${v.blocked}`
        parDirective[cle] = (parDirective[cle] ?? []).concat(v.route)
    })
    Object.entries(parDirective).forEach(([cle, routes]) => {
        console.error(`  ${cle}`)
        console.error(`    sur ${[...new Set(routes)].join(', ')}`)
    })
    process.exit(1)
}

main().catch((e) => {
    console.error(e)
    process.exit(1)
})
