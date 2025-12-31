#!/usr/bin/env node
/**
 * Script d'optimisation des images
 * - Redimensionne à 1200px max
 * - Convertit en WebP et AVIF (qualité 75%)
 * - Conserve les originaux
 */

import sharp from 'sharp';
import fs from 'fs';
import path from 'path';
import {fileURLToPath} from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const SOURCE_DIR = path.join(__dirname, 'public/images');
const OUTPUT_DIR = path.join(__dirname, 'public/optimized');
const MAX_WIDTH = 1200;
const QUALITY = 75;

// Extensions supportées
const SUPPORTED_EXTENSIONS = [
    '.jpg',
    '.jpeg',
    '.png',
    '.webp'
];

// Récupère tous les fichiers images récursivement
function getImageFiles(dir, files = []) {
    if (!fs.existsSync(dir)) return files;

    const entries = fs.readdirSync(dir, {withFileTypes: true});

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            getImageFiles(fullPath, files);
        } else if (SUPPORTED_EXTENSIONS.includes(path.extname(entry.name).toLowerCase())) {
            files.push(fullPath);
        }
    }

    return files;
}

// Optimise une image
async function optimizeImage(inputPath) {
    const relativePath = path.relative(SOURCE_DIR, inputPath);
    const parsedPath = path.parse(relativePath);
    const outputBase = path.join(OUTPUT_DIR, parsedPath.dir, parsedPath.name);

    // Créer le dossier de sortie
    const outputDir = path.dirname(outputBase);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, {recursive: true});
    }

    try {
        // .rotate() sans argument applique l'orientation EXIF automatiquement
        const image = sharp(inputPath).rotate();
        const metadata = await image.metadata();

        // Redimensionner si nécessaire
        const resizedImage = metadata.width > MAX_WIDTH ? image.resize(MAX_WIDTH, null, {withoutEnlargement: true}) : image;

        // WebP
        await resizedImage
            .clone()
            .webp({quality: QUALITY})
            .toFile(`${outputBase}.webp`);

        // AVIF
        await resizedImage
            .clone()
            .avif({quality: QUALITY})
            .toFile(`${outputBase}.avif`);

        console.log(`✓ ${relativePath}`);
    } catch (err) {
        console.error(`✗ ${relativePath}: ${err.message}`);
    }
}

// Main
async function main() {
    console.log('Optimisation des images...\n');

    // Nettoyer le dossier de sortie
    if (fs.existsSync(OUTPUT_DIR)) {
        fs.rmSync(OUTPUT_DIR, {recursive: true});
    }
    fs.mkdirSync(OUTPUT_DIR, {recursive: true});

    const files = getImageFiles(SOURCE_DIR);
    console.log(`${files.length} images trouvées\n`);

    // Traiter par batch de 5 pour éviter de surcharger
    const batchSize = 5;
    for (let i = 0 ; i < files.length ; i += batchSize) {
        const batch = files.slice(i, i + batchSize);
        await Promise.all(batch.map(optimizeImage));
    }

    console.log('\n✓ Optimisation terminée!');
    console.log(`  Images WebP et AVIF générées dans: ${OUTPUT_DIR}`);
}

main().catch(console.error);
