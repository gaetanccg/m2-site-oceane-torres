/**
 * L'administration ne doit pas repartir dans le bundle du site public.
 *
 * Ce que ce contrôle regarde : les fichiers que `dist/index.html` charge d'emblée —
 * littéralement ce que télécharge chaque visiteur, avant toute navigation. Les vues
 * d'administration sont en `import()` paresseux et vivent dans leurs propres chunks ; elles
 * n'entrent ici que si une arête d'import les y ramène.
 *
 * C'est arrivé, et sans bruit : `stores/auth.ts` importait le baril `@/services/adminApi`,
 * qui charge ses dix sous-services de manière eager. Le store étant importé par `main.ts` et
 * par le routeur, 15 chemins d'API d'administration — 91 occurrences, 16,8 Ko — partaient
 * chez chaque visiteur du site vitrine.
 *
 * ⚠️ Ce contrôle vit dans `build:prerender`, PAS dans la CI, et c'est délibéré : il n'existe
 * aucun workflow de déploiement. Render construit seul depuis le dépôt, avec ce script comme
 * commande de build. Un contrôle en CI n'empêcherait donc aucun déploiement ; ici, il fait
 * échouer le build — donc le déploiement.
 *
 * À dire nettement : masquer des chemins d'API n'est PAS une mesure de sécurité. Ils se
 * devinent, et les copies déjà publiées restent lisibles. Ce qui se joue ici est le poids
 * inutile, et surtout la surface JavaScript de l'admin exposée au même contexte que le site
 * public. La vraie frontière est ailleurs — `auth:sanctum` + `EnsureUserIsAdmin` côté API,
 * et à terme une origine distincte pour la console.
 */

import fs from 'fs/promises'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const distDir = path.resolve(__dirname, '../dist')

/**
 * Un chemin d'API d'administration : `/admin/` suivi d'un segment.
 *
 * Les routes du ROUTEUR ne matchent pas : le routeur déclare `path: '/admin'` avec des
 * enfants relatifs (`'galleries'`), donc « /admin/galleries » n'apparaît jamais en littéral.
 * Vérifié : le chunk d'entrée en contient zéro aujourd'hui.
 */
const CHEMIN_ADMIN = /\/admin\/[a-z][a-z0-9-]*/g

async function fichiersDuPremierChargement() {
    const html = await fs.readFile(path.join(distDir, 'index.html'), 'utf8')
    const refs = [...html.matchAll(/(?:src|href)="(\/assets\/[^"]+\.js)"/g)].map(m => m[1])

    if (refs.length === 0) {
        throw new Error("Aucun script trouvé dans dist/index.html — le build a-t-il abouti ?")
    }
    return [...new Set(refs)]
}

async function main() {
    const fichiers = await fichiersDuPremierChargement()
    const fautifs = []

    for (const ref of fichiers) {
        const contenu = await fs.readFile(path.join(distDir, ref.replace(/^\//, '')), 'utf8')
        const trouves = contenu.match(CHEMIN_ADMIN) || []
        if (trouves.length > 0) {
            const distincts = [...new Set(trouves)].sort()
            fautifs.push({ ref, occurrences: trouves.length, distincts })
        }
    }

    if (fautifs.length === 0) {
        console.log(`  ✅ Isolation de l'administration : ${fichiers.length} fichier(s) de premier chargement, aucun chemin d'API admin.`)
        return
    }

    console.error("\n  ❌ Des chemins d'API d'administration sont dans le bundle du site public.\n")
    for (const f of fautifs) {
        console.error(`     ${f.ref} — ${f.occurrences} occurrence(s), ${f.distincts.length} chemin(s) :`)
        f.distincts.slice(0, 20).forEach(c => console.error(`       ${c}`))
    }
    console.error(`
  Cause la plus probable : un fichier chargé au démarrage (main.ts, le routeur, un store)
  importe « @/services/adminApi » ou un « @/services/admin/* ». Le baril charge ses dix
  sous-services d'un coup, donc une seule arête suffit.

  Pour la trouver :  npx eslint src
  La règle « no-restricted-imports » d'eslint.config.js désigne le fichier fautif.
`)
    process.exit(1)
}

main().catch(e => { console.error(`  ❌ ${e.message}`); process.exit(1) })
