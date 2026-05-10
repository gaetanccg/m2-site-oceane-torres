#!/usr/bin/env node
/**
 * One-shot migration: renomme les photos du portfolio en préfixe padded.
 *
 *   1.jpg  → 010.jpg
 *   2.jpg  → 020.jpg
 *   ...
 *   33.jpg → 330.jpg
 *
 * Par défaut en dry-run. Flag `--apply` pour exécuter.
 * Opère sur public/images, public/optimized, public/optimized/thumbs
 * en même temps pour éviter de re-lancer sharp.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const WEB_DIR = path.resolve(__dirname, '..')

const CATEGORIES = ['Portraits', 'Sport', 'Animalier', 'Concert', 'Automobile', 'Entreprise']

const ROOTS = [
    { dir: 'public/images', exts: ['.jpg', '.jpeg', '.png', '.webp'] },
    { dir: 'public/optimized', exts: ['.webp', '.avif'] },
    { dir: 'public/optimized/thumbs', exts: ['.webp', '.avif'] },
]

const apply = process.argv.includes('--apply')

function pad(n) {
    return String(n * 10).padStart(3, '0')
}

function listNumericBases(dir, allowedExts) {
    if (!fs.existsSync(dir)) return new Set()
    const bases = new Set()
    for (const entry of fs.readdirSync(dir)) {
        const ext = path.extname(entry).toLowerCase()
        if (!allowedExts.includes(ext)) continue
        const base = path.basename(entry, path.extname(entry))
        const n = Number(base)
        if (Number.isInteger(n) && n > 0) bases.add(n)
    }
    return bases
}

// Détecte si un dossier est déjà au format padded (3 chiffres, multiples de 10)
function isAlreadyPadded(dir, allowedExts) {
    if (!fs.existsSync(dir)) return false
    let hasAny = false
    for (const entry of fs.readdirSync(dir)) {
        const ext = path.extname(entry).toLowerCase()
        if (!allowedExts.includes(ext)) continue
        const base = path.basename(entry, path.extname(entry))
        const n = Number(base)
        if (!Number.isInteger(n) || n <= 0) continue
        hasAny = true
        if (base.length !== 3 || n % 10 !== 0) return false
    }
    return hasAny
}

function planOperations() {
    const renames = []
    const deletions = []
    const skipped = []

    for (const category of CATEGORIES) {
        // Référence : les numéros présents dans public/images (source de vérité)
        const sourceDir = path.join(WEB_DIR, 'public/images', category)
        const sourceBases = listNumericBases(sourceDir, ROOTS[0].exts)

        // Idempotence : si déjà padded, on skip la catégorie entière
        if (isAlreadyPadded(sourceDir, ROOTS[0].exts)) {
            skipped.push(`(déjà padded) ${category}`)
            continue
        }

        for (const root of ROOTS) {
            const dir = path.join(WEB_DIR, root.dir, category)
            if (!fs.existsSync(dir)) {
                skipped.push(`(dir absent) ${root.dir}/${category}`)
                continue
            }

            const entries = fs.readdirSync(dir)
            for (const entry of entries) {
                const ext = path.extname(entry).toLowerCase()
                if (!root.exts.includes(ext)) continue

                const base = path.basename(entry, path.extname(entry))
                const n = Number(base)
                if (!Number.isInteger(n) || n <= 0) {
                    skipped.push(`${root.dir}/${category}/${entry} (non numérique)`)
                    continue
                }

                // Orphelin : présent dans optimized/ mais absent des sources → à supprimer
                const isOptimizedDir = root.dir !== 'public/images'
                if (isOptimizedDir && !sourceBases.has(n)) {
                    deletions.push({
                        path: path.join(dir, entry),
                        rel: `${root.dir}/${category}/${entry}`,
                    })
                    continue
                }

                const newName = pad(n) + path.extname(entry)
                if (newName === entry) continue

                renames.push({
                    from: path.join(dir, entry),
                    to: path.join(dir, newName),
                    relFrom: `${root.dir}/${category}/${entry}`,
                    relTo: `${root.dir}/${category}/${newName}`,
                })
            }
        }
    }

    return { renames, deletions, skipped }
}

function main() {
    const { renames, deletions, skipped } = planOperations()

    console.log(`Mode : ${apply ? 'APPLY' : 'DRY-RUN (aucune modification)'}\n`)

    if (deletions.length) {
        console.log(`${deletions.length} orphelins à supprimer (présents dans optimized/ sans source) :`)
        for (const d of deletions) console.log(`  ${d.rel}`)
        console.log('')
    }

    console.log(`${renames.length} renommages prévus :`)
    for (const r of renames) {
        console.log(`  ${r.relFrom}  →  ${r.relTo}`)
    }

    if (skipped.length) {
        console.log(`\n${skipped.length} entrées ignorées :`)
        for (const s of skipped) console.log(`  ${s}`)
    }

    if (!apply) {
        console.log('\nRelance avec --apply pour exécuter.')
        return
    }

    // Collisions réelles : cible déjà présente ET cette cible n'est pas elle-même
    // en cours de renommage (auquel cas le two-pass ci-dessous la libèrera)
    const sources = new Set(renames.map(r => r.from))
    const collisions = renames.filter(r =>
        fs.existsSync(r.to) && r.from !== r.to && !sources.has(r.to)
    )
    if (collisions.length) {
        console.error('\nCollisions détectées (cibles déjà présentes) :')
        for (const c of collisions) console.error(`  ${c.relTo}`)
        console.error('Abandon. Nettoyer les cibles avant de relancer.')
        process.exit(1)
    }

    let deleted = 0
    for (const d of deletions) {
        fs.unlinkSync(d.path)
        deleted++
    }

    // Two-pass rename pour éviter les collisions entre source et cible
    // (ex: 01.jpg → 010.jpg alors que 010.jpg existe déjà et doit devenir 100.jpg)
    const tmpSuffix = '.__migrate_tmp__'
    for (const r of renames) {
        fs.renameSync(r.from, r.to + tmpSuffix)
    }
    for (const r of renames) {
        fs.renameSync(r.to + tmpSuffix, r.to)
    }

    console.log(`\n${deleted} orphelins supprimés, ${renames.length} fichiers renommés.`)
}

main()
