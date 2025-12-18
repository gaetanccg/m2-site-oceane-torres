# Frontend - Océane Torres Photographie

Ce dossier contiendra l'application frontend Vue.js.

## Stack technique

- **Vue.js 3** avec Composition API
- **Vite** - Build tool
- **TailwindCSS** - Framework CSS
- **shadcn-vue** - Composants UI
- **Pinia** - State management
- **Vue Router** - Routing
- **@supabase/supabase-js** - SDK Supabase

## Installation

```bash
cd web
pnpm install
pnpm run dev
```

## Variables d'environnement

Créer un fichier `.env.local` :

```env
VITE_API_URL=http://localhost:8000/api
VITE_SUPABASE_URL=https://your-project.supabase.co
VITE_SUPABASE_ANON_KEY=your-anon-key
```

## Scripts

- `pnpm run dev` - Serveur de développement
- `pnpm run build` - Build de production
- `pnpm run preview` - Prévisualisation du build
- `pnpm run test` - Lancer les tests
