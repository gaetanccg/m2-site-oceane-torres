/**
 * Generate thumbnail versions of portfolio images for the masonry grid.
 *
 * Source: public/optimized/{Category}/{n}.{avif,webp}
 * Output: public/optimized/thumbs/{Category}/{n}.{avif,webp}
 *
 * Thumbnails are ~400px wide — visually identical in a grid,
 * but 5-10x smaller than full-res (~15-30 Ko vs ~100-250 Ko).
 *
 * Usage: node scripts/generate-thumbs.js
 */

import sharp from 'sharp'
import { readdir, mkdir, stat } from 'fs/promises'
import { join, extname } from 'path'

const OPTIMIZED_DIR = new URL('../public/optimized', import.meta.url).pathname
const THUMBS_DIR = join(OPTIMIZED_DIR, 'thumbs')
const THUMB_WIDTH = 400
const CATEGORIES = ['Portraits', 'Sport', 'Animalier', 'Automobile', 'Entreprise']

async function generateThumbs() {
    let totalGenerated = 0
    let totalSkipped = 0
    let totalSavedBytes = 0

    for (const category of CATEGORIES) {
        const srcDir = join(OPTIMIZED_DIR, category)
        const outDir = join(THUMBS_DIR, category)

        await mkdir(outDir, { recursive: true })

        const files = await readdir(srcDir)
        const imageFiles = files.filter(f => ['.avif', '.webp'].includes(extname(f).toLowerCase()))

        for (const file of imageFiles) {
            const srcPath = join(srcDir, file)
            const outPath = join(outDir, file)
            const ext = extname(file).toLowerCase()

            // Skip if thumb already exists and is newer than source
            try {
                const srcStat = await stat(srcPath)
                const outStat = await stat(outPath)
                if (outStat.mtimeMs >= srcStat.mtimeMs) {
                    totalSkipped++
                    continue
                }
            } catch {
                // Output doesn't exist, generate it
            }

            try {
                const pipeline = sharp(srcPath).resize(THUMB_WIDTH, null, {
                    withoutEnlargement: true,
                    fit: 'inside',
                })

                if (ext === '.avif') {
                    await pipeline.avif({ quality: 65 }).toFile(outPath)
                } else {
                    await pipeline.webp({ quality: 72 }).toFile(outPath)
                }

                const srcSize = (await stat(srcPath)).size
                const outSize = (await stat(outPath)).size
                totalSavedBytes += srcSize - outSize
                totalGenerated++

                const ratio = ((1 - outSize / srcSize) * 100).toFixed(0)
                console.log(`  ✓ ${category}/${file}  ${(srcSize / 1024).toFixed(0)}K → ${(outSize / 1024).toFixed(0)}K  (-${ratio}%)`)
            } catch (err) {
                console.error(`  ✗ ${category}/${file}: ${err.message}`)
            }
        }
    }

    console.log('')
    console.log(`Done: ${totalGenerated} generated, ${totalSkipped} skipped`)
    console.log(`Total saved: ${(totalSavedBytes / 1024 / 1024).toFixed(1)} MB`)
}

generateThumbs().catch(console.error)
