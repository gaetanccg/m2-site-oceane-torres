# Océane Torres Photographie - Frontend

Site vitrine Vue 3 + Vite + TypeScript + TailwindCSS

---

## Installation

```bash
npm install
```

---

## Commandes

| Commande                  | Description                                      |
|---------------------------|--------------------------------------------------|
| `npm run dev`             | Serveur de développement (http://localhost:5173) |
| `npm run build`           | Build de production                              |
| `npm run build:prerender` | Build + prerendering SEO                         |
| `npm run preview`         | Prévisualiser le build localement                |

---

## Gestion des images du portfolio

Les images originales sont stockées dans `public/images/` par catégorie :

```
public/images/
├── Animalier/
├── Automobile/
├── Concert/
├── Entreprise/
├── Portraits/
└── Sport/
```

### Convention de nommage : préfixe padded

Les fichiers doivent être nommés avec un **préfixe numérique sur 3 chiffres** qui détermine l'ordre d'affichage. Les incréments de 10 laissent de la marge pour insérer des photos au milieu **sans renommer les existantes** :

```
010_shooting-elise.jpg
020_shooting-marc.jpg
030_cso-cluny.jpg
...
```

**Exemples d'opérations :**

- Insérer une photo entre la 010 et la 020 → dépose `015_xxx.jpg`.
- Réordonner une photo → renomme uniquement ce fichier (ex: `020_xxx.jpg` → `005_xxx.jpg`).

Le suffixe après le préfixe est libre (descriptif ou non).

### Modifier les images

1. Ajouter / renommer / supprimer les fichiers dans `public/images/<catégorie>/`.
2. Optimiser + régénérer le manifest :
   ```bash
   node optimize-images.js
   ```
3. Générer les thumbnails (grille du portfolio) :
   ```bash
   node scripts/generate-thumbs.js
   ```

Le fichier `src/config/gallery-manifest.json` est régénéré automatiquement — **aucune édition manuelle** de `src/config/gallery.ts` n'est nécessaire pour ajouter/supprimer des photos.

Pour un ajustement de métadonnées (alt SEO d'une catégorie, ajout YouTube, ordre des catégories), éditer `src/config/gallery.ts`.

---
