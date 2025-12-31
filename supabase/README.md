# Configuration Supabase

## Création du projet

1. Créer un compte sur [Supabase](https://supabase.com)
2. Créer un nouveau projet
3. Noter les informations de connexion :
   - **Project URL** : `https://your-project.supabase.co`
   - **API Key (anon)** : Pour le frontend
   - **API Key (service_role)** : Pour le backend (à garder secret)
   - **Database Password** : Pour la connexion PostgreSQL

## Configuration Laravel

Dans `api/.env` :

```env
DB_CONNECTION=pgsql
DB_HOST=db.your-project.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your_database_password

SUPABASE_URL=https://your-project.supabase.co
SUPABASE_KEY=your_anon_key
SUPABASE_SERVICE_KEY=your_service_role_key
```

## Storage Buckets

### Créer les buckets dans Supabase Dashboard :

1. **public-portfolio** (Public)
   - Photos du portfolio public
   - Watermarkées automatiquement

2. **private-galleries** (Private)
   - Structure : `{user_id}/{gallery_id}/`
   - Accès via tokens signés

### Policies à configurer :

```sql
-- Bucket public-portfolio : lecture publique
CREATE POLICY "Public read access"
ON storage.objects FOR SELECT
USING (bucket_id = 'public-portfolio');

-- Bucket private-galleries : accès authentifié
CREATE POLICY "Authenticated users can access their galleries"
ON storage.objects FOR SELECT
USING (
  bucket_id = 'private-galleries'
  AND auth.uid()::text = (storage.foldername(name))[1]
);
```

## Row Level Security (RLS)

Les migrations Laravel créent les tables. Configurez les policies RLS dans Supabase :

### Table galleries

```sql
-- Les utilisateurs voient leurs propres galeries
ALTER TABLE galleries ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view own galleries"
ON galleries FOR SELECT
USING (user_id = auth.uid() OR type = 'public');

-- Seuls les admins peuvent créer/modifier
CREATE POLICY "Admins can manage galleries"
ON galleries FOR ALL
USING (
  EXISTS (
    SELECT 1 FROM users
    WHERE id = auth.uid() AND role = 'admin'
  )
);
```

### Table photos

```sql
ALTER TABLE photos ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Photos accessible via gallery"
ON photos FOR SELECT
USING (
  EXISTS (
    SELECT 1 FROM galleries g
    WHERE g.id = gallery_id
    AND (g.type = 'public' OR g.user_id = auth.uid())
  )
);
```

## Notes importantes

- Les migrations Laravel gèrent le schéma de la base
- Les policies RLS sont configurées manuellement dans Supabase
- Le storage est géré via l'API Supabase depuis Laravel
