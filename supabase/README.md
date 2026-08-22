# Supabase

Supabase ne sert que de **PostgreSQL managé**. Ni Auth, ni Storage, ni Realtime, ni
les API REST/GraphQL — le stockage des fichiers est assuré par MinIO
(`config/filesystems.php`, disque `minio`).

## Connexion

| Clé | Valeur |
|---|---|
`DB_CONNECTION` | `pgsql` |
`DB_PORT` | `6543` — pooler **transaction** |

Le port 6543 est un choix contraint : le pooler *session* (5432) a été essayé en
production et a saturé (`EMAXCONNSESSION`, ~5000×/jour sous charge).

Le pooler transaction impose `PDO::ATTR_EMULATE_PREPARES`, donc PDO interpole les
paramètres côté client. Comme Laravel convertit les booléens en entiers et que
PostgreSQL n'a pas d'opérateur `boolean = integer`, les booléens sont liés en
littéraux `'true'` / `'false'` par `App\Database\PostgresConnection`.

## Sauvegarde

`pg_dump` ne peut pas passer par le pooler transaction : il exige une session stable
et un snapshot cohérent. Voir `deploy/backup.sh`, qui force le port 5432 et se limite
au schéma `public`.

## Edge Functions

Aucune. La fonction `cleanup-gallery-files` a été supprimée : vestige de l'ancienne
architecture Supabase Storage, elle n'était appelée ni par l'API, ni par le front, et
visait un bucket qui n'existe plus.
