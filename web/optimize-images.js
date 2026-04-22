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
const MANIFEST_PATH = path.join(__dirname, 'src/config/gallery-manifest.json');
const MAX_WIDTH = 1200;
const QUALITY = 75;

// Catégories du portfolio (détermine les dossiers scannés pour le manifest)
const CATEGORIES = ['Portraits', 'Sport', 'Animalier', 'Concert', 'Automobile', 'Entreprise'];

// Fichiers à ne pas ré-optimiser (versions custom conservées)
const SKIP_FILES = [
    'hero.png',
    'hero.jpg',
    'hero.jpeg',
    'hero.webp',
    'persona_rev_2.jpg',
    'persona_rev_2.jpeg',
    'persona_rev_2.png',
    'persona_rev_2.webp',
    'contact_1.jpg',
    'contact_1.jpeg',
    'contact_1.png',
    'contact_1.webp'
];

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
        } else if (SUPPORTED_EXTENSIONS.includes(path.extname(entry.name).toLowerCase()) && !SKIP_FILES.includes(entry.name)) {
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

// Supprime les fichiers optimisés qui n'ont plus de source dans public/images/
function cleanOrphans() {
    let deleted = 0
    for (const category of CATEGORIES) {
        const sourceDir = path.join(SOURCE_DIR, category)
        const outDir = path.join(OUTPUT_DIR, category)
        if (!fs.existsSync(outDir)) continue

        const sourceBases = new Set()
        if (fs.existsSync(sourceDir)) {
            for (const entry of fs.readdirSync(sourceDir)) {
                const ext = path.extname(entry).toLowerCase()
                if (!SUPPORTED_EXTENSIONS.includes(ext)) continue
                if (SKIP_FILES.includes(entry)) continue
                sourceBases.add(path.parse(entry).name)
            }
        }

        for (const entry of fs.readdirSync(outDir)) {
            const fullPath = path.join(outDir, entry)
            if (fs.statSync(fullPath).isDirectory()) continue
            const ext = path.extname(entry).toLowerCase()
            if (!['.webp', '.avif'].includes(ext)) continue
            const base = path.parse(entry).name
            if (!sourceBases.has(base)) {
                fs.unlinkSync(fullPath)
                console.log(`  ✗ orphelin : ${category}/${entry}`)
                deleted++
            }
        }
    }
    if (deleted > 0) {
        console.log(`\n${deleted} orphelins supprimés dans public/optimized/`)
    }
}

// Génère le manifest de la galerie à partir du contenu de public/optimized/
function generateManifest() {
    const categories = {};

    for (const category of CATEGORIES) {
        const dir = path.join(OUTPUT_DIR, category);
        if (!fs.existsSync(dir)) {
            categories[category] = [];
            continue;
        }

        const files = fs.readdirSync(dir)
            .filter(f => path.extname(f).toLowerCase() === '.webp')
            .sort((a, b) => a.localeCompare(b, 'en', {numeric: true}));

        categories[category] = files;
    }

    const manifest = {
        generatedAt: new Date().toISOString(),
        categories
    };

    fs.mkdirSync(path.dirname(MANIFEST_PATH), {recursive: true});
    fs.writeFileSync(MANIFEST_PATH, JSON.stringify(manifest, null, 2) + '\n');

    const total = Object.values(categories).reduce((sum, arr) => sum + arr.length, 0);
    console.log(`\n✓ Manifest généré : ${MANIFEST_PATH}`);
    console.log(`  ${total} images dans ${CATEGORIES.length} catégories`);
}

// Main
async function main() {
    console.log('Optimisation des images...\n');

    // Créer le dossier de sortie s'il n'existe pas (sans effacer l'existant)
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, {recursive: true});
    }

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

    cleanOrphans();
    generateManifest();
}

main().catch(console.error);
