# Océane Torres Photographie - Frontend

Site vitrine Vue 3 + Vite + TypeScript + TailwindCSS

---

## Installation

```bash
npm install
```

---

## Commandes

| Commande | Description |
|----------|-------------|
| `npm run dev` | Serveur de développement (http://localhost:5173) |
| `npm run build` | Build de production |
| `npm run build:prerender` | Build + prerendering SEO |
| `npm run preview` | Prévisualiser le build localement |

---

## Gestion des images du portfolio

Les images originales sont stockées dans `public/images/` par catégorie :

```
public/images/
├── Animalier/
├── Automobile/
├── Entreprise/
├── Portraits/
└── Sport/
```

Un script d'optimisation génère des versions **WebP** et **AVIF** (qualité 75, max 1200px de large) dans `public/optimized/`.

### Modifier les images

1. Ajouter, remplacer ou supprimer les images dans `public/images/<catégorie>/`
2. Lancer l'optimisation :
   ```bash
   node optimize-images.js
   ```
3. Mettre à jour les chemins dans `src/config/gallery.ts` si des images ont été ajoutées ou supprimées

---

## Déploiement sur Render

Le site utilise le prerendering local pour le SEO. Le dossier `dist/` est commité dans le repo.

### Configuration Render

- **Build Command** : `echo "Static site"`
- **Publish Directory** : `web/dist`

### Procédure de mise à jour

1. Faire les modifications
2. Générer le build prerendéré :
   ```bash
   npm run build:prerender
   ```
3. Commit et push :
   ```bash
   git add dist/
   git commit -m "Update site"
   git push
   ```

Render déploiera automatiquement les fichiers statiques.
