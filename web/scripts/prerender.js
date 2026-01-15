/**
 * Script de prerendering pour générer des pages HTML statiques
 * Utilisé pour améliorer le SEO des pages SPA
 */

import puppeteer from 'puppeteer'
import { spawn } from 'child_process'
import fs from 'fs/promises'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const distDir = path.resolve(__dirname, '../dist')

// Routes à prerendre
const routes = [
    '/',
    '/portfolio',
    '/prestations',
    '/bons',
    '/a-propos',
    '/contact',
    '/mentions-legales'
]

const PORT = 4567

async function startServer() {
    return new Promise((resolve, reject) => {
        const server = spawn('npx', ['serve', distDir, '-p', PORT, '-s'], {
            stdio: 'pipe',
            shell: true
        })

        server.stdout.on('data', (data) => {
            if (data.toString().includes('Accepting connections')) {
                resolve(server)
            }
        })

        server.stderr.on('data', (data) => {
            console.error(`Server error: ${data}`)
        })

        // Timeout au cas où le message ne s'affiche pas
        setTimeout(() => resolve(server), 2000)
    })
}

async function prerenderRoute(browser, route) {
    const page = await browser.newPage()
    const url = `http://localhost:${PORT}${route}`

    console.log(`  Prerendering: ${route}`)

    await page.goto(url, { waitUntil: 'networkidle0' })

    // Attendre que le contenu soit chargé
    await page.waitForSelector('#app', { timeout: 10000 })
    await new Promise(resolve => setTimeout(resolve, 500))

    const html = await page.content()

    // Déterminer le chemin du fichier
    let filePath
    if (route === '/') {
        filePath = path.join(distDir, 'index.html')
    } else {
        const dir = path.join(distDir, route)
        await fs.mkdir(dir, { recursive: true })
        filePath = path.join(dir, 'index.html')
    }

    await fs.writeFile(filePath, html)
    await page.close()
}

async function main() {
    console.log('Starting prerender process...\n')

    // Vérifier que dist/ existe
    try {
        await fs.access(distDir)
    } catch {
        console.error('Error: dist/ directory not found. Run "npm run build" first.')
        process.exit(1)
    }

    // Démarrer le serveur
    console.log('Starting local server...')
    const server = await startServer()

    // Lancer Puppeteer
    console.log('Launching browser...\n')
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    })

    try {
        for (const route of routes) {
            await prerenderRoute(browser, route)
        }
        console.log('\nPrerendering complete!')
    } catch (error) {
        console.error('Error during prerendering:', error)
        process.exit(1)
    } finally {
        await browser.close()
        server.kill()
    }
}

main()
