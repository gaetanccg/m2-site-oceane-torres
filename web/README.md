# Vitrine Océane (Vite + Vue 3 + TypeScript)

Ce dépôt contient une petite vitrine construite avec Vite, Vue 3 et TypeScript, stylée avec TailwindCSS.

Objectif de ce README

- expliquer comment lancer le projet localement
- détailler une procédure claire pour déployer le site sur Render (Static Site)

Table des matières

- Prérequis
- Installation & développement local
- Build & preview
- Déploiement sur Render (Site statique)
- Variables d'environnement
- Astuces de dépannage

---

## Prérequis

- Node.js (recommandé >= 18)
- npm (ou yarn / pnpm)
- Un compte Render (https://render.com) connecté à votre dépôt Git (GitHub / GitLab / Bitbucket)

Conseil : pour forcer une version de Node lors du build Render, ajoutez un fichier `.node-version` ou renseignez `engines` dans `package.json`.

---

## Installation et développement local

1. Installer les dépendances :

```bash
npm install
```

2. Lancer le serveur de développement (Vite) :

```bash
npm run dev
```

Ouvrez ensuite l'URL indiquée par Vite (par défaut http://localhost:5173).

---

## Build et preview

- Pour vérifier la compilation TypeScript puis builder le site :

```bash
npm run build
```

- Pour prévisualiser le build localement :

```bash
npm run preview
```

Si la commande `npm run build` échoue sur Render, reproduisez l'erreur localement avec la même commande pour diagnostiquer.

---

## Déploiement sur Render (Site statique)

Ci‑dessous une procédure pas à pas pour déployer ce site en tant que "Static Site" sur Render :

1. Connectez‑vous à Render puis cliquez sur "New" → "Static Site".
2. Choisissez votre repository et la branche à déployer.
3. Dans la configuration, renseignez :
    - Build Command :

      npm run build

    - Publish Directory :

      dist

    - (Optionnel) Root Directory : laissez vide si le projet est à la racine du repo.

4. (Recommandé) Spécifier la version de Node si nécessaire :
    - Ajoutez un fichier `.node-version` à la racine contenant par ex. `18.x`, ou
    - Ajoutez dans `package.json` :

```json
  "engines": {
"node": ">=18"
}
```

5. Add environment variables if needed (see next section).
6. Cliquez sur "Create Static Site". Render lancera automatiquement `npm install` puis `npm run build`.

Notes :

- Render exécute la commande de build dans un environnement propre ; toute différence avec votre local vient généralement d'une version de Node, d'une variable d'environnement manquante ou d'un asset manquant.
- Si votre projet requiert un comportement côté serveur (APIs), utilisez plutôt "Web Prestation" (Node) au lieu de "Static Site".

---

## Variables d'environnement

Si votre build ou votre site dépend de variables d'environnement (API keys, flags...), ajoutez‑les dans la configuration du service Render :

- Dans le tableau de bord de votre site Render → Environment → Add Environment Variable
- Ces variables sont disponibles pendant la phase de build et à l'exécution pour les fonctions côté serveur.

Exemple :

- `VITE_API_BASE_URL` pour une URL d'API consommée par le frontend (note : préfixez par `VITE_` pour qu'elle soit injectée dans le bundle client par Vite).

---

## Fichiers utiles / réglages conseillés

- `package.json` : les scripts utiles sont déjà présents :
    - `dev` : démarre Vite en mode dev
    - `build` : `vue-tsc -b && vite build` (typecheck + build)
    - `preview` : prévisualise le build

- `.node-version` ou `engines` dans `package.json` pour verrouiller la version Node sur Render.

- Assets : assurez‑vous que les images référencées existent dans `src/assets` ou `public/`.

---

## Dépannage fréquent

- Erreur TypeScript lors du build sur Render : reproduisez localement avec `npm run build`; corrigez les types ou ajustez `tsconfig.json`.
- Erreurs d'assets manquants : vérifiez les chemins relatifs (utilisez `new URL('../assets/..', import.meta.url).href` si nécessaire) et que les fichiers sont bien commités.
- Différences Node : ajoutez `.node-version` ou `engines` pour rendre l'environnement de build cohérent.
- Build trop lent / timeout : Render a des limites de temps ; si votre build est long, optimisez les étapes ou migrez à une image plus performante (option payante).

---

## Déploiement manuel (optionnel)

Si vous préférez builder localement puis déployer les fichiers statiques :

1. Générer le build localement :

```bash
npm run build
```

2. Déployer le dossier `dist/` vers Render via leur CLI (ou un autre hébergeur) :

```bash
# Exemple avec tar + scp ou outils équivalents vers un hôte
# Pour Render, on recommande la méthode automatique via repo connecté.
```

---

## Support et contact

Si vous avez besoin d'aide pour le déploiement, partagez l'erreur de build (log Render) et je peux t'aider à la corriger.

---

Licence

Consultez le fichier `LICENSE` du dépôt pour les informations de licence.
