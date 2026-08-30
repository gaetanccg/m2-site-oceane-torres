/**
 * Le filet de rendu : trois parcours joués dans un vrai navigateur, contre l'API locale.
 *
 * Pourquoi il existe. Trois chantiers du plan sont bloqués faute de lui, et chacun le dit
 * explicitement :
 *   · §2.4 — l'enveloppe `{data, meta, message}` touche ~143 endpoints ; la migrer sans filet
 *     de rendu, c'est déplacer le risque dans les vues (galerie par code, tunnel d'achat,
 *     listes d'administration) ;
 *   · #13 — sortir le jeton de `localStorage` dépend de SESSION_DOMAIN, des domaines stateful
 *     Sanctum et du CORS ; une erreur casse toute l'administration ;
 *   · 3.6 — factoriser un composant « ressource administrable » toucherait treize écrans.
 *
 * Ce que le filet vérifie, et qui le distingue d'un simple appel d'API : que la page REND
 * quelque chose. Une coquille vide répond 200. Chaque parcours compte donc des éléments
 * réellement présents, et échoue si le contenu attendu n'apparaît pas.
 *
 * Usage : make check-render   (prépare les données, joue les parcours, les retire)
 */

import puppeteer from 'puppeteer'
import http from 'http'
import fs from 'fs/promises'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const distDir = path.resolve(__dirname, '../dist')

/**
 * 4173 n'est pas un port au hasard : c'est une origine que `CORS_ALLOWED_ORIGINS` autorise.
 * Sur un autre port, l'appel `/sanctum/csrf-cookie` échoue et la connexion n'a jamais lieu —
 * le parcours paraît alors cassé alors que c'est le harnais qui l'est.
 */
const PORT = 4173

/** Les images de test n'ont pas d'octets sur MinIO : leur 404 ne dit rien du rendu. */
const HOTE_DE_STOCKAGE = 's3.oceanetorresphotographie.fr'

const TYPES = {
    '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css',
    '.json': 'application/json', '.svg': 'image/svg+xml', '.webp': 'image/webp',
    '.avif': 'image/avif', '.png': 'image/png', '.jpg': 'image/jpeg',
    '.ico': 'image/x-icon', '.woff2': 'font/woff2', '.txt': 'text/plain',
}

async function servirDist() {
    const serveur = http.createServer(async (req, res) => {
        const url = new URL(req.url, `http://localhost:${PORT}`)
        let fichier = path.join(distDir, url.pathname)
        try {
            const infos = await fs.stat(fichier)
            if (infos.isDirectory()) fichier = path.join(fichier, 'index.html')
        } catch {
            fichier = path.join(distDir, 'index.html')   // repli SPA
        }
        try {
            const contenu = await fs.readFile(fichier)
            res.writeHead(200, { 'Content-Type': TYPES[path.extname(fichier)] || 'application/octet-stream' })
            res.end(contenu)
        } catch {
            res.writeHead(404)
            res.end('introuvable')
        }
    })
    await new Promise(r => serveur.listen(PORT, r))
    return serveur
}

function surveiller(page, incidents) {
    page.on('pageerror', e => incidents.push(`erreur JS : ${e.message}`))
    page.on('console', m => m.type() === 'error' && incidents.push(`console : ${m.text()}`))
    page.on('response', r => {
        if (r.status() < 400) return
        if (r.url().includes(HOTE_DE_STOCKAGE)) return
        incidents.push(`HTTP ${r.status()} ${r.request().method()} ${r.url().replace(`http://localhost:${PORT}`, '')}`)
    })
}

const attendre = ms => new Promise(r => setTimeout(r, ms))

async function compter(page, selecteur) {
    return page.evaluate(s => document.querySelectorAll(s).length, selecteur)
}

async function texte(page) {
    return page.evaluate(() => document.body.innerText.replace(/\s+/g, ' '))
}

/** Parcours 1 — une cliente ouvre sa galerie avec le code reçu par email. */
async function parcoursGalerie(page, fixtures) {
    await page.goto(`http://localhost:${PORT}/gallery/${fixtures.share_code}`, { waitUntil: 'networkidle2' })
    await page.waitForSelector('.photo-wrapper', { timeout: 15000 })

    const vignettes = await compter(page, '.photo-wrapper')
    if (vignettes !== fixtures.photos) {
        throw new Error(`${vignettes} vignette(s) rendue(s), ${fixtures.photos} attendue(s)`)
    }
    return `${vignettes} vignettes`
}

/** Parcours 2 — elle ajoute une photo, ouvre son panier, puis la page de paiement. */
async function parcoursAchat(page, fixtures) {
    await page.goto(`http://localhost:${PORT}/gallery/${fixtures.share_code}`, { waitUntil: 'networkidle2' })
    await page.waitForSelector('.photo-wrapper', { timeout: 15000 })

    const ajoute = await page.evaluate(() => {
        const bouton = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim().startsWith('Ajouter'))
        if (!bouton) return false
        bouton.click()
        return true
    })
    if (!ajoute) throw new Error('aucun bouton « Ajouter » dans la galerie')
    await attendre(1500)

    await page.goto(`http://localhost:${PORT}/panier`, { waitUntil: 'networkidle2' })
    await attendre(1500)

    const contenu = await texte(page)
    if (/panier est vide/i.test(contenu)) throw new Error('le panier est vide après un ajout')
    if (!/€/.test(contenu)) throw new Error('aucun montant affiché dans le panier')

    await page.goto(`http://localhost:${PORT}/checkout`, { waitUntil: 'networkidle2' })
    await attendre(1500)

    const champs = await compter(page, 'input, select, textarea')
    if (champs < 3) throw new Error(`page de paiement quasi vide : ${champs} champ(s)`)

    return `panier chiffré, ${champs} champs au paiement`
}

/** Parcours 3 — la photographe se connecte et ouvre deux listes d'administration. */
async function parcoursAdministration(page, fixtures) {
    await page.goto(`http://localhost:${PORT}/?login=true`, { waitUntil: 'networkidle2' })
    await page.waitForSelector('#login-email', { timeout: 15000 })
    await page.type('#login-email', fixtures.admin_email)
    await page.type('#login-password', fixtures.admin_password)

    const reponse = page.waitForResponse(r => r.url().includes('/auth/login') && r.request().method() === 'POST', { timeout: 20000 })
    await page.evaluate(() => document.querySelector('form').requestSubmit())
    const statut = (await reponse).status()
    if (statut !== 200) throw new Error(`connexion refusée (${statut})`)
    await attendre(1500)

    const jeton = await page.evaluate(() => localStorage.getItem('auth_token'))
    if (!jeton) throw new Error('aucun jeton après connexion')

    const vues = []
    for (const [chemin, libelle] of [['/admin/orders', 'Commandes'], ['/admin/clients', 'Clients']]) {
        await page.goto(`http://localhost:${PORT}${chemin}`, { waitUntil: 'networkidle2' })
        await attendre(2000)

        const contenu = await texte(page)
        if (/Page non trouvée/i.test(contenu)) throw new Error(`${chemin} rend une page 404`)
        if (!contenu.includes(libelle)) throw new Error(`${chemin} n'affiche pas « ${libelle} »`)

        const cellules = await compter(page, 'td, [role="cell"]')
        vues.push(`${libelle} (${cellules} cellules)`)
    }

    return vues.join(', ')
}

const PARCOURS = [
    ['Galerie par code', parcoursGalerie],
    ['Tunnel d’achat', parcoursAchat],
    ['Administration', parcoursAdministration],
]

async function main() {
    const brut = await fs.readFile(path.resolve(__dirname, '../../.render-fixtures.json'), 'utf8')
    const fixtures = JSON.parse(brut)

    const serveur = await servirDist()
    const navigateur = await puppeteer.launch({ args: ['--no-sandbox'] })
    let echecs = 0

    for (const [nom, jouer] of PARCOURS) {
        const page = await navigateur.newPage()
        const incidents = []
        surveiller(page, incidents)

        try {
            const resume = await jouer(page, fixtures)
            if (incidents.length) throw new Error(incidents.slice(0, 4).join(' · '))
            console.log(`  ✅ ${nom} — ${resume}`)
        } catch (e) {
            echecs++
            console.log(`  ❌ ${nom} — ${e.message}`)
            incidents.slice(0, 4).forEach(i => console.log(`       ${i}`))
        } finally {
            await page.close()
        }
    }

    await navigateur.close()
    serveur.close()

    console.log(echecs === 0 ? '\n  Filet de rendu : 3 parcours verts.' : `\n  Filet de rendu : ${echecs} parcours en échec.`)
    process.exit(echecs === 0 ? 0 : 1)
}

main().catch(e => { console.error(e); process.exit(1) })
