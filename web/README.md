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
