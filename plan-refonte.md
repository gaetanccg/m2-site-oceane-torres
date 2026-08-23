# Plan de refonte — Océane Torres Photographie

> **Document de travail exécutable.** Il traduit l'audit (`AUDIT_PROJET.md`, 13 sections) en actions
> ordonnées, chacune traçable jusqu'au constat qui la motive.
>
> **Date** : 2026-08-22 · **Source** : `AUDIT_PROJET.md` · **Périmètre** : 68 items de dette
> (16 critiques, 24 élevés) · **Charge estimée** : 50-53 jours

---

## 0. Mode d'emploi

### 0.1. Comment lire ce document

| Élément | Signification |
|---|---|
`[ ]` | Reste à faire
`[x]` | Fait et vérifié — un test ou une mesure l'atteste
`[~]` | **Partiel ou écarté** : la ligne juste en dessous dit ce qui manque et pourquoi
`→ §X.Y` | Renvoi vers l'audit, pour le détail et la justification
`#N` | Numéro d'item du registre de dette (§10, matrice de traçabilité)
🔴 🟠 🟡 🟢 | Critique · Élevé · Moyen · Faible
⛔ | **Bloquant** : rien de ce qui suit ne doit commencer avant
📋 | **Décision requise** : ne pas coder avant d'avoir tranché
👤 | **Implique la photographe** : décision produit, pas technique

### 0.2. Trois règles de conduite

1. **La phase 0 est indépendante de tout.** Elle corrige la production actuelle et se fait quel que
   soit le devenir du projet. Ne pas l'enchaîner avec le reste : la livrer.
2. **Ne jamais se fier à un commentaire ou à un README sans le vérifier.** L'audit a dû se corriger
   six fois pour cette raison (→ §9.8). Les seules sources fiables sont `docs/supervision.md`,
   `docs/audit-supervision.md` et `docs/analyse-paiement-sumup.md`.
3. **Les 25 invariants de la §9 de ce plan ne se négocient pas.** Chacun est né d'un incident de
   production. Les perdre, c'est les réapprendre par l'échec — aux frais de la photographe.

### 0.3. Choix d'approche retenu

**Restructuration ciblée**, pas réécriture complète (→ §11.3 de l'audit).

| | Réécriture | **Restructuration** | Durcissement seul |
|---|---|---|---|
Charge | 50-70 j | **30-40 j** (+ migration) | 10-15 j
Dette structurelle traitée | toute | **toute** | aucune
Savoir acquis conservé | ❌ | **✅** | ✅
Livrable par incréments | ❌ | **✅** | ✅

Justification : ~40 % du code porte la quasi-totalité du savoir acquis par les pannes (paiement,
pipeline image, RGPD, supervision). Les zones fragiles — facturation, authentification, panier, file
d'attente, sessions scolaires — sont réécrites ; le reste est conservé et documenté.

---

## 1. Tableau de bord

| Phase | Objet | Charge | Cumul | Livrable |
|---|---|---|---|---|
| **0** | Sécuriser l'existant | **3 j** | 3 j | Production sûre, 9 constats critiques fermés |
| **1** | Relever & décider | 2,5 j | 5,5 j | Document de relevé + 8 décisions écrites |
| **2** | Fondations | 8 j | 13,5 j | Dette structurelle traitée, iso-fonctionnel · **2.1 · 2.2 · 2.3 · 2.7 faits** |
| **3** | Réécriture des domaines fragiles | 15 j | 28,5 j | Facturation, auth, panier, scolaire testés |
| **4** | Migration des données | 13-16 j | 42-45 j | Bascule effectuée |
| **5** | Valeur ajoutée | 8 j | **50-53 j** | Portfolio administrable, avoirs, export compta |

**Jalon de contrôle** : fin de phase 2 (13,5 j). Le projet a perdu sa dette structurelle sans qu'aucune
fonctionnalité n'ait changé. Point de décision naturel pour continuer ou s'arrêter.

**Aléas** : volume de photos à chemin non résolu (peut doubler la phase 4) · résultat du test D1
(+2 à 5 j) · décisions produit D4/D5/D6 (0,5 à 10 j chacune) · disponibilité de la photographe.

---

## 1bis. Avancement réel

> Mis à jour au fil de l'exécution. Dernière mise à jour : 2026-08-22.

### Phase 0 — fait et vérifié

| Action | État | Vérification |
|---|---|---|
| 0.1 Commandes destructrices | ✅ | `make fresh`/`make seed` inexistants · garde-fou testé dans les 2 sens |
| 0.2 Sauvegardes | ✅ | dump 20 Mo restauré et **vérifié** : 9 tables témoins concordent |
| 0.3 Base de dev locale | ✅ | `postgres-dev` sur `127.0.0.1:55433`, 76 migrations + seed OK |
| 0.4 Compte admin du seeder | ✅ | **0 compte du seeder en base** — mesuré sur le dump |
| 0.5 `downloadFile()` | ✅ | route + méthode supprimées, 140 Mo purgés |
| 0.6 `storage:clean-orphans` | ✅ | 6 tests — les 3 ciblant le correctif échouent sans lui |
| 0.7 `ClientController::destroy` | ✅ | back + front supprimés, renvoi vers `/admin/privacy` préremplí |
| 0.8 IP cliente réelle | ✅ | `real_ip` sur prod **et** preprod, `nginx -t` OK, `trustProxies` restreint |
| 0.9 Rate limits | ✅ | 8 tests, dont clé par email à IP tournante + témoin négatif |
| 0.10 Déconnexion | ✅ | 4 tests, révocation réelle sous session **et** sous Bearer |
| 0.11 Quantité sur les 4 documents | ✅ | 7 tests · **a mis au jour #69, voir ci-dessous** |
| 0.14 Export scolaire | ✅ | `resolved_storage_path` + compteur d'omissions visible |
| 0.15 Supervision externe | ⛔ | **créations de compte requises** |
| 0.16 Veille de vulnérabilités | ✅ | job `security-audit` bloquant + `dependabot.yml` · **les deux audits sont verts** |
| 0.17 Files d'attente | ✅ | 3 files, routage vérifié à l'exécution, `--memory=1024` < `memory_limit` |
| 0.18 Correctifs courts | ✅ | 10/10 · build Docker d'imagick validé par Gaëtan |
| 0.19 Recette | 🟡 | **9/15 vérifiées** · 6 exigent la prod ou des comptes externes |
| 0.12 XSS `v-html` | ✅ | aucune directive `v-html` dans `web/src` |
| 0.13 Politique de mot de passe | ✅ | `min(12)->uncompromised()`, `StoreUserRequest` aligné |

**Suite de tests : 255 méthodes** (226 au départ, +29 écrites en phase 0).

### 🔴 #69 — NOUVEAU constat critique, manqué par les 3 passes de l'audit

`resources/views/pdf/invoice.blade.php:246` contenait :

```blade
Remise@if($order->gift_code) (code {{ $order->gift_code }})@endif
```

La regex de directives de Blade impose `\B` avant le `@`. Entre `e` et `@` il y a une
frontière de mot : **ce `@if` n'était pas compilé** et restait du texte littéral, alors
que le `@endif` qui suit `)` l'était. Le fichier compilé se retrouvait avec un `endif`
orphelin → `syntax error, unexpected token "endif"`.

**Conséquence : le gabarit ne compilait plus.** Or `completeOrder()` avale l'échec de
`generateForOrder()` (→ §7.5) : la ligne `invoices` était créée avec son numéro, puis
la génération du PDF échouait en silence.

**Portée exacte, mesurée sur le dump du 2026-08-22** — et elle corrige une première
formulation trop large de ce document, qui affirmait qu'aucune facture n'avait jamais
été produite :

| | |
|---|---|
Commit fautif | `30cfa58` « Add gift code functionality », **2026-06-04** |
Factures **avec** PDF | **114**, du 2026-03-04 au 2026-06-01 |
Factures **sans** PDF | **5**, du 2026-06-05 au 2026-06-18 |

La facturation fonctionnait donc, et **la fonctionnalité codes promo l'a cassée**. Les
dégâts se sont arrêtés à 5 factures faute de ventes depuis juin — la prochaine commande
payée aurait heurté le même mur.

C'est une régression livrée sans qu'aucun test ne rende la facture, ce qui confirme la
thèse de l'audit : les bugs sont dans les domaines non testés.

**Reste à décider** 👤 : régénérer les 5 factures manquantes. Les données sont
intactes, `Admin/OrderController::downloadInvoice()` sait déjà régénérer à la demande —
mais son endpoint n'est appelé par aucun bouton (→ 3.1).

### Écarts assumés par rapport au plan

Chacun a été constaté à l'exécution ; le plan avait raison sur l'intention, pas sur le détail.

| § | Le plan disait | Réalité | Décision |
|---|---|---|---|
| 0.1 | `abort_if(app()->environment('production'))` | `APP_ENV=local` alors que la base pointait ailleurs → le garde ne se déclenche **jamais** | Garde sur l'**hôte réellement connecté**, allowlist |
| 0.2 | `pg_dump … "$PROD_DSN"` | Le pooler transaction (6543) ne convient pas à `pg_dump` (pas de snapshot cohérent) | Port forcé à **5432** ; aucun client PG local → tout passe par conteneur |
| 0.8 | `set_real_ip_from <plages Cloudflare>` | Le pair immédiat est `cloudflared` **sur le NAS**, pas une IP publique CF → la règle ne matcherait jamais | Réseaux privés + `real_ip_header CF-Connecting-IP` |
| 0.10 | Supprimer `currentAccessToken()` si `PersonalAccessToken` | Sous session, c'est un `TransientToken` → le jeton 30 j **survivait** | `$user->tokens()->delete()` |
| 0.10 | `throw e` côté front | 3 appelants, dont un `.then()` sans `.catch()` → redirection perdue, utilisateur bloqué | Retour booléen + `error.value`, parcours préservé |
| 0.11 | Ajouter la quantité aux 4 gabarits | Le gabarit de facture **ne compilait pas** (#69) — corriger la quantité seule n'aurait rien produit | Espace avant `@if` + 4 tests de génération |

### Audit de dépendances — vert

Le job `security-audit` était rouge d'emblée : 18 avis Composer et 20 vulnérabilités
npm. Les deux chaînes ont été montées.

| Paquet | Avant | Après | Nature |
|---|---|---|---|
`dompdf/dompdf` | v3.1.5 | v3.1.6 | 6 avis · **rend des chaînes utilisateur dans la facture** |
`league/commonmark` | 2.8.2 | 2.10.0 | 6 avis, corrigés en 2.9.0 |
`guzzlehttp/guzzle` | 7.13.2 | 7.15.3 | — |
`puppeteer` | ^24.35.0 | ^25.8.0 | majeur · pré-rendu SEO |
`sharp` | ^0.34.5 | ^0.35.3 | majeur · vignettes |
`vite` | 6.4.1 | 6.4.3 | correctif, épinglage strict conservé |

`composer.json` est resté inchangé : les trois cibles PHP tenaient dans les contraintes
existantes. Quatorze vulnérabilités npm transitives ont été résolues par `npm audit fix`
sans changement de version déclarée.

Les deux sauts majeurs ne touchent que des `devDependencies` de scripts d'assets, et
les API employées (`launch`/`goto`/`content`, `resize`/`avif`/`webp`) sont inchangées
entre ces versions.

Vérifié : `composer audit` et `npm audit --audit-level=high` ne signalent plus rien ·
255 tests · `build:prerender` génère les 11 routes avec titre et contenu · `vue-tsc` et
ESLint propres.

### Recette de la phase 0 — 9 lignes sur 15

Vertes : Makefile sans commande destructrice · base de dev locale · `download-file`
absente de `route:list` · `clean-orphans` (6 tests) · `DELETE /admin/clients` absente ·
rate limit login (8 tests) · révocation au logout (4 tests) · quantité sur facture
(7 tests) · aucun `v-html`. Plus le mécanisme d'audit CI, constaté rouge avant montée
des dépendances.

Restent 6 lignes hors de portée locale : dump restauré, compte admin du seeder,
`consent_ip` après déploiement, UptimeRobot + healthchecks.io, et l'email de
confirmation pendant un export (exige un worker actif).

### 🔴 Les 8 colonnes JSON sont de type `json`, pas `jsonb`

Découvert en outillant la phase 1. Conséquence immédiate : **la requête la plus
critique de l'audit ne peut pas s'exécuter telle qu'écrite.** §3bis.14-C emploie
`metadata ? 'supabase_path'` — l'opérateur `?` n'existe que sur `jsonb`. C'est
précisément le bloc qui dimensionne la phase 4.

Le relevé utilise `metadata->>'clé' IS NOT NULL`, qui fonctionne sur `json` et vérifie
en outre que la valeur n'est pas nulle — une clé présente à `null` est inutilisable pour
résoudre un chemin de fichier.

À reprendre dans l'item #38 : les 8 colonnes gagneraient à passer en `jsonb`, qui seul
permet l'indexation et les opérateurs de containment.

### Outillage de la phase 1 — prêt

`deploy/releve-production.sql` + `deploy/releve-production.sh` : les 14 blocs de
l'audit plus les 3 requêtes des passes de relecture, en une commande, produisant le
document « Relevé de production » daté qui est le livrable de la phase 1.

Refuse par défaut toute cible non locale (`ALLOW_REMOTE=1` pour forcer) — le relevé se
joue sur la copie restaurée. Validé à blanc contre le schéma réel migré :
**15 sections, 0 erreur SQL**.

```bash
./deploy/backup.sh all          # produit et vérifie le dump
./deploy/releve-production.sh   # puis le relevé
```

### ✅ imagick : build validé

`api/Dockerfile` épingle `IMAGICK_VERSION=3.8.1` (stable courante sur PECL, vérifiée) et
retire le `|| echo "skipped"` qui laissait le build réussir avec un repli GD silencieux —
cause du filigrane potentiellement illisible sur les photos vendues (→ §5.4).

Build vérifié par Gaëtan le 2026-08-22.

### 📊 Premières mesures réelles — l'audit n'en avait aucune

Dump du 2026-08-22, restauré et vérifié.

| Table | Lignes | Ce que ça change |
|---|---|---|
`photos` | **5 350** | dont **0** avec `supabase_path` — voir ci-dessous |
`galleries` | 196 | |
`orders` | 211 · 120 payées | |
`invoices` | 119 | 114 avec PDF, 5 sans (→ #69) |
`users` | 18 | dont **2 admins**, non 1 comme supposé |
`clients` | 17 | |
`reservations` | **0** | le formulaire public n'a jamais produit une ligne |
`gift_cards` | **0** | aucun bon cadeau vendu → pèse sur D4 |
`availability_slots` / `_patterns` | **0** / **0** | D3 « supprimer » sans enjeu de données |
`client_forms` | **0** | R3 « absorber » sans objet |
`subscriptions` / `_items` | **0** / **0** | suppression Cashier sans risque |
`privacy_audit_logs` | **0** | rien à migrer — contrainte C4 sans objet |

**🟢 L'aléa n°1 de la phase 4 n'existe pas.** Le risque n°1 de l'audit (§3bis.15) était
`photos.metadata->supabase_path` dans la chaîne de résolution des fichiers, susceptible
de doubler la phase 4. Mesure : **0 photo sur 5 350** en dépend. Toutes ont
`file_path_hd` **et** `storage_path`, aucune n'a d'URL absolue, aucune n'est non traitée.
Population A uniquement, la seule saine.

**✅ Action 0.4 répondue.** `SELECT count(*) … WHERE email IN ('admin@oceanetorres.fr',
'client@test.com')` → **0**. Le compte `admin123` n'a jamais existé en production. Le
constat critique #2 est fermé par la preuve.

⚠️ Ce contrôle a dû être joué sur une copie **non** anonymisée : il cherche une adresse
littérale, que l'anonymisation détruit précisément. Une mise en garde est inscrite dans
le bloc G du relevé.

### Base de développement sur données réelles anonymisées

`deploy/anonymize.sql` + `deploy/load-dev-db.sh` : restauration jetable →
anonymisation → dump anonymisé réutilisable → chargement dans `postgres-dev`. La base
de développement ne voit jamais les données réelles, et le chargement est annulé si une
adresse réelle subsiste.

Pseudonymisation **déterministe** : une même valeur réelle produit toujours le même
substitut, dans toutes les tables — sinon la carte des identités du relevé (bloc O)
deviendrait fausse. Vérifié : **138 emails distincts avant comme après**.

Préservé octet pour octet : UUID, les 7 colonnes de chemin, `metadata->storage_path`,
montants, dates, statuts, `order_number`, `invoice_number`, et la nullité de chaque
colonne. `share_code` et `access_token` sont en revanche **régénérés** — ce sont des
accès vivants. Corollaire : la répétition générale de la phase 4, qui vérifie qu'une
galerie s'ouvre avec son ancien code, se fait sur une copie non anonymisée.

PostgreSQL aligné sur la production (**17**) pour `postgres-dev`, `postgres-test` et la
CI : ils étaient en 16 alors que la prod tourne en 17.6.

### 🔴 D1 tranchée — et le diagnostic de l'audit était incomplet

L'audit proposait deux voies : rester en pooler transaction en retirant
`STRINGIFY_FETCHES` (option A, recommandée), ou réduire le besoin de connexions pour
repasser en pooler session (B). **A est réfutée par la mesure**, et il existe une
option C que le plan n'envisageait pas.

**Ce qui se passe réellement.** `EMULATE_PREPARES`, qu'impose le pooler transaction,
fait interpoler les paramètres côté client par PDO. Or `prepareBindings()` de Laravel
convertit tout booléen en **entier**. Le SQL produit est donc `where "flag" = 0`, et
PostgreSQL n'a pas d'opérateur `boolean = integer`. En `INSERT` : *« column is of type
boolean but expression is of type integer »*.

Retirer `STRINGIFY_FETCHES` ne touche pas à cela — c'était un défaut **distinct**, qui
faisait remonter les booléens en chaînes et cassait les comparaisons strictes.

**Option C retenue.** PostgreSQL accepte les littéraux quotés `'true'` et `'false'`, en
lecture comme en écriture. Une seule surcharge de `prepareBindings()` sur la connexion
remplace les 31 contournements dispersés : le trait sur 11 modèles, 19 `whereRaw`, et
un contournement manuel dans `PhotoController`.

| | Avant | Après |
|---|---|---|
Trait sur des modèles | 11 | **0** — fichier supprimé |
`whereRaw` booléens | 19 | **0** |
Contournements manuels | 1 | **0** |
Lignes | — | **−111 / +36** |

**Vérifié sur les 5 350 photos réelles** : les six scopes concordent exactement avec
leur équivalent SQL littéral (downloadable 327, images 5350, liked 352, purchasable
5350, published 196, prestations actives 6). Les booléens remontent en `bool` natif,
donc `=== false` fonctionne enfin. 8 tests de régression, qui échouent tous si l'on
retire la surcharge. Suite complète : **263 tests**.

Conséquence pour la suite : le choix du pooler n'est plus une dette. B et C de l'audit
(pooler session, Postgres auto-hébergé) deviennent sans objet.

### Phase 2 — avancement

> Le tableau ci-dessous résume ; les **cases des sections `## 2.x`** sont la référence
> détaillée. Les deux doivent concorder — si elles divergent, croire les cases.

| Action | État | Résultat |
|---|---|---|
| 2.1 Suppressions | ✅ | **−3 345 lignes** · 181 → 151 routes · 38 → 34 tables |
| 2.2 Couche booléenne (D1) | ✅ | 31 contournements → 1 surcharge de connexion |
| 2.3 Redis | ✅ | cache + sessions + URL signées cachées + binaires sortis du cache |
| 2.7 Vitest | ✅ | 25 tests · a attrapé une régression de 2.1 dès le 1er lancement |
| 2.4 Convention de réponse API | 🔵 | `docs/api-conventions.md` écrit · `per_page`/`sort_by` partout · 12 `fetch()` sauvages supprimés · une seule couche d'accès HTTP · `formatOrder()` → API Resources. Reste : les autres Resources, puis l'enveloppe |
| 2.5 Couche d'autorisation | 🔵 | `OrderPolicy` + `GalleryPolicy` **toujours via le Gate** · règle des 30 min supprimée, remplacée par une signature courte · `POST /orders/by-email` retiré · `docs/authorization.md`. Reste : #34 (décision produit) |
| 2.6 Déploiement reproductible | 🔵 | l'image **est** l'artefact : code copié, plus de bind mount du code, `migrate` sorti du démarrage des conteneurs, rollback par tag. Non validé par un déploiement réel |

**Suite de tests : 329 back + 49 front.**

Détail de 2.4, dans l'ordre fixé par `docs/api-conventions.md` §6 :

| Étape | État | Note |
|---|---|---|
Convention écrite | ✅ | enveloppe `{data, meta?, message?}`, **sans** clé `success` ; codes HTTP ; règles front |
`per_page` plafonné à 100 | ✅ | 8 sites en dur (5 tailles différentes) → paramétrable partout |
`sort_by`/`sort_dir` + allowlist | ✅ | trait `PaginatesQueries` ; sans allowlist, `sort_by` atteint le `ORDER BY` |
`fetch()` hors couche de services | ✅ | 12 supprimés. Les 3 téléchargements MinIO vérifient enfin `response.ok` — un lien expiré enregistrait le XML d'erreur S3 sous le nom de la photo |
Une seule couche d'accès HTTP | ✅ | l'audit annonçait 2 couches parallèles : faux, `BaseAdminService` héritait déjà. Le vrai écart était `request()` qui imposait `Content-Type: application/json` et 10 s — incompatible avec un envoi de fichiers |
`formatOrder()` → API Resource | ✅ | méthode statique publique de contrôleur appelée depuis 3 autres contrôleurs. `AdminOrderResource` isole `user`/`payment`/`sumup_*`, qui ne doivent pas sortir sur les endpoints publics |
Fuite de `access_token` fermée | ✅ | 🔴 **escalade de privilège trouvée et prouvée** — voir ci-dessous |
Resources publiques galerie/photo | ✅ | `GalleryResource`, `PhotoResource`, `DownloadableGalleryResource` · galerie **23 → 10 clés**, photo **18 → 9** |
Resources admin | ⏳ | forme conservée : les vues admin consomment beaucoup de champs calculés (`download_status`, `total_downloads_count`, `client_id`…) |
`PrestationResource` | ✅ | public + admin · `price` et `background_opacity` cessent de sortir en **chaînes** |
`UserResource` | ✅ | 4 endpoints d'authentification · `email_verified_at` ne sort plus |
`ReservationResource` | ✅ | tableau de bord client · 🔴 `notes` (commentaire interne) ne sort plus |
Resources Client / SchoolSession | ⏳ | **admin uniquement**, aucune audience publique |
Enveloppe unique | ⏳ | **en dernier** : rupture de contrat sur 151 endpoints, à migrer avec le front |

Deux défauts trouvés en chemin, hors périmètre annoncé :

`Events.vue` lisait `data.meta.current_page` — l'API renvoie un paginateur Laravel à plat,
sans clé `meta`. `hasMore` valait donc toujours faux et « charger plus » ne s'activait jamais.
Latent aujourd'hui (11 galeries publiées pour une page de 12), la 12e serait devenue
invisible. Le type `PaginatedResponse<T>` décrivait cette même forme inexistante et n'était
utilisé par personne — vraisemblablement l'origine de l'erreur.

Le **build de production était cassé** : `npm run build` échouait sur les tests ajoutés en
2.7. Passé inaperçu parce que `npm run typecheck` lançait `vue-tsc --noEmit` sur un
`tsconfig.json` à `"files": []` — sans `-b`, il ne vérifiait **aucun fichier**. Corrigé, plus
une étape `typecheck` en CI.

### 2.5 — la règle des « 30 minutes » n'était pas du code mort

Deux vérifications inline décidaient l'accès à une commande, avec des règles
**différentes** : `OrderController::show` autorisait sur `created_at`,
`OrderService::getOrderForDownload` sur `paid_at`. Aucune ne passait par le Gate.

`created_at < 30 min` accordait la lecture d'une commande — email du client, nom, adresse
de livraison — à quiconque connaissait son identifiant, sans rien prouver.

Mais la supprimer sèchement aurait cassé le parcours invité : le `return_url` de SumUp ne
porte que `?order=id`, et le jeton de téléchargement n'existe pas encore à ce moment-là
puisqu'il est généré **à** la confirmation du paiement (`OrderService:414`). C'est donc
cette règle qui faisait fonctionner la page de confirmation après paiement.

Remplacée par `OrderAccessSignature` — HMAC de `id|expiration` sous `APP_KEY`, 2 h, placé
dans le `return_url` et renvoyé par `confirmFreeOrder`. Le front lisait déjà
`route.query.token` : seul `Checkout.vue` change, pour propager le jeton de la commande
gratuite. 7 tests couvrent les bords, dont l'expiration repoussée en conservant le digest.

`POST /orders/by-email` est retiré : il renvoyait **toutes les commandes d'une adresse**
sans authentification, et aucun consommateur ne l'appelait. `docs/authorization.md` §6
décrit comment rétablir la fonction sans en faire un oracle d'énumération.

### 2.4 — trois défauts trouvés en écrivant les Resources restantes

**🔴 `notes` sortait sur le tableau de bord du client.** C'est le commentaire interne de la
photographe sur une réservation, écrivable seulement par l'administration
(`AdminUpdateReservationRequest`). Le client concerné le lisait sur son propre espace.
`ReservationResource` expose les 7 champs que le front déclare ; `notes`, `guest_*`,
`user_id`, `client_id` et les 4 `$appends` n'en font pas partie.

**`price` et `background_opacity` sortaient en chaînes.** Le cast `decimal:2` du modèle
sérialise `"150.00"` et `"0.50"`, alors que les types front déclarent `number`. Rien ne
cassait — `Intl.NumberFormat` coerce — mais la forme envoyée contredisait le contrat
déclaré. `PrestationResource` les envoie en nombres.

**`email_verified_at` sortait sur les 4 endpoints d'authentification.** État interne, lu
par personne. `password` et `remember_token` étaient déjà couverts par `$hidden`, mais la
sérialisation nue dépendait de `$fillable` : une colonne ajoutée à `users` apparaissait
d'office dans les réponses.

Un piège d'outillage rencontré deux fois : mes motifs de remplacement à faible indentation
sont des **sous-chaînes** de ceux à forte indentation, ce qui produit des substitutions en
double ou des assertions fausses. Corrigé en ciblant par ligne complète.

### 2.4 — Resources publiques : la forme ne dépend plus du modèle

`GalleryResource` + `PhotoResource` + `DownloadableGalleryResource` sur les 4 endpoints
publics. La galerie passe de **23 à 10 clés**, la photo de **18 à 9**. Avant, la réponse
dépendait de `$fillable` et `$appends` : ajouter un accesseur au modèle changeait tous les
endpoints sans que rien ne le signale.

Trois éléments morts retirés d'abord, pour ne pas avoir à les modéliser : `GET
/api/galleries` (aucun consommateur — front, prerendu, tests), la clé `thumbnail_photo`
de 5 réponses (le front ne lit que `thumbnail_photo_id` et `cover_photo`), et le fichier
`GalleryResource.php` d'origine, jamais référencé et qui appelait `parent::toArray()` —
donc reproduisait la sérialisation nue.

`file_path` ne sort plus. Le front s'en servait comme dernier terme de chaînes
`thumbnail_url || preview_url || display_url || file_path`, dans 9 emplacements des vues
publiques. Terme inatteignable — `resolveImageUrl()` ne rend jamais vide pour une image —
et un chemin de bucket n'est de toute façon pas une URL. Les 9 replis sont nettoyés.

**Le typecheck a rattrapé la conséquence** : sans ce dernier terme non-optionnel, les
chaînes devenaient `string | undefined`. J'ai terminé par `|| ''` en conservant l'ordre de
priorité, plutôt que de déclarer non-nullables des champs qui peuvent être nuls pour une
vidéo dont la signature échoue.

**Piège rencontré** : `isset($this->cover_photo)` dans une Resource déclenche la
résolution d'attribut du modèle et rendait `true` là où le contrôleur n'avait rien posé,
ajoutant une clé absente auparavant. Remplacé par
`array_key_exists('cover_photo', $this->resource->getAttributes())`, qui ne peut pas
déclencher de résolution.

`GallerySerializationTest` fige les deux formes et vérifie qu'aucun des 12 champs
interdits n'apparaît dans le corps brut des 3 endpoints publics.

### 🔴 Une galerie `public` livrait les originaux sans filigrane, sans aucun jeton

Trouvé en croisant l'inventaire des sérialisations avec les endpoints d'image.
`Gallery::isAccessible(null)` rend **`true`** pour `type = 'public'`. Trois endpoints
servant du contenu **sans filigrane** s'appuyaient dessus :

- `GET /api/images/clean/{photo}`
- `GET /api/images/clean-thumb/{photo}`
- `GET /api/photos/{photo}/download`

Sur une galerie `public`, toute photo marquée `is_downloadable` était donc servie propre,
sans jeton. C'est exactement ce que le filigranage protège.

**Piège latent, pas fuite active** : la production ne compte **aucune** galerie `public`
— 151 `private` + 45 `event` = 196, mesuré sur le dump anonymisé. La première créée
aurait suffi.

Corrigé par `Gallery::grantsCleanDerivatives()`, qui exige le jeton sans exception.
`isAccessible()` reste inchangée pour la **consultation** : une galerie publique doit bien
rester consultable sans jeton. La distinction est le fond du sujet — voir une galerie
n'est pas obtenir ses originaux.

`CleanDerivativeAccessTest` couvre les 3 endpoints × 3 cas (sans jeton, jeton valide,
jeton erroné). Un test existant asseyait l'ancien ordre de vérification : il demandait le
message « photo non téléchargeable » sur une galerie publique **sans jeton**, donc il
passait pour la mauvaise raison. Réécrit avec un jeton valide, il teste enfin le drapeau.

**Trou dans mon propre test, signalé par l'inventaire.** `$hidden` ne filtre pas une
chaîne : `getCleanThumbnailUrlAttribute()` recompose `?token={access_token}` dans l'URL de
repli. Si `clean_thumbnail_url` entrait un jour dans `Photo::$appends`, le jeton
repartirait par le payload des photos — que mes assertions sur la racine `gallery` ne
voyaient pas. Test étendu.

### 🔴 Escalade de privilège : le code de partage livrait le jeton de téléchargement

Trouvée en cherchant les sérialisations de modèle nu. `Gallery` n'avait **aucun `$hidden`**,
et les endpoints publics renvoyaient le modèle tel quel. Chaîne prouvée par test :

1. `GET /api/galleries/code/{share_code}` — public, code à **6 caractères** — renvoyait
   `access_token` en clair (64 caractères), ainsi que `assigned_email`, l'adresse de la cliente.
2. `GET /api/galleries/download/{access_token}` → **HTTP 200, `mode: download`**.

Or c'est ce second endpoint qui expose `clean_thumbnail_url`, les dérivés **sans filigrane**.
Le code de partage ouvre la boutique en photos filigranées ; il donnait en réalité accès aux
originaux propres. Les galeries publiques et d'événement fuitaient de la même façon.

Correction en **exposition explicite** plutôt qu'en liste blanche : les trois champs sont
`$hidden` sur le modèle, donc masqués partout par défaut, et rendus visibles aux seuls
endroits légitimes — administration, propriétaire de la galerie, et l'endpoint de
téléchargement lui-même (dont l'appelant détient déjà le jeton). Un nouvel endpoint public ne
peut plus les exposer par oubli.

`tests/Feature/GalleryTokenExposureTest.php` — 7 tests, qui vérifient les deux sens : la
fermeture côté public **et** le maintien de l'accès pour l'admin et le propriétaire.

Écarts assumés en 2.1, tous justifiés par une mesure ou par un test :

| Élément | Plan | Décision | Raison |
|---|---|---|---|
`/admin/health` | supprimer | **conservé** | 3 tests le couvrent ; niveau 3 d'un dispositif conçu |
`bulk-downloadable`, `sort-order` | supprimer | **conservés** | le bon correctif est de les brancher (3.6), pas de les retirer |
`gift_cards.pdf_path` | supprimer | **conservée** | utilisée par `PersonalDataEraser`, et requise par D4 option A |
`Photo::display_url` | retirer de `$appends` | **conservé** | 4 composants front s'en servent comme repli — pas iso-fonctionnel |
`ReservationPolicy` | supprimer | **supprimée** | ses 5 routes clientes le sont aussi, donc elle redevient morte |
Cache de filigrane | supprimer **ou** réactiver | **supprimé** | réactiver exige de juger le rendu visuel, hors iso-fonctionnel |

Deux routes pointaient vers des méthodes **inexistantes** —
`GalleryController::downloadPhotos` et `Admin\NotificationController::store` : deux 500
latents de plus, en sus des trois corrigés en 0.18.

### Bloqué sur intervention manuelle

```
[x] 0.2  ./deploy/backup.sh all — dump vérifié
[x] 0.4  Compte admin du seeder — 0 occurrence
[~] 0.2b ./deploy/backup.sh storage — miroir MinIO : écarté (plusieurs centaines de Go)
[ ] 0.15 UptimeRobot + 2 checks healthchecks.io (créations de compte)
[ ] 0.19 Les 6 lignes de recette qui exigent la production
[~] 1.x  Relevé de production — fait le 2026-08-22, phase 1 réduite ensuite
```

---

# PHASE 0 — Sécuriser l'existant

**3 jours. Indépendante de la refonte. À livrer sur la production actuelle.**

Ferme 9 des 16 constats critiques. Les actions 0.1 à 0.4 sont ⛔ bloquantes pour tout le reste.

## 0.1 ⛔🔴 Neutraliser les commandes destructrices — 10 min · #57

**Pourquoi** : `make fresh` exécute `migrate:fresh --seed` sur la **base de production** — il n'existe
aucune base de développement locale, et `api/.env` pointe la prod. La cible est listée dans
`make help`, deux lignes au-dessus de `make test`. → §12.1

```bash
[x] # 1. Supprimer les deux cibles du Makefile
    #    (ou les conditionner à un APP_ENV local ET un DB_HOST non-prod)
```

```php
[x] // 2. database/seeders/DatabaseSeeder.php — garde-fou en tête de run()
    public function run(): void
    {
        abort_if(app()->environment('production'), 500, 'Seeder interdit en production.');
        // …
    }
```

```php
[x] // 3. Retirer les mots de passe en dur (#2) — les tirer de l'environnement
    'password' => Hash::make(env('SEED_ADMIN_PASSWORD') ?? Str::random(32)),
```

**Recette** : `make help` ne propose plus `fresh` ni `seed` · `php artisan db:seed` échoue si
`APP_ENV=production`.

## 0.2 ⛔🔴 Sauvegardes vérifiées — 1 j · #7

**Pourquoi** : aucune sauvegarde de la base ni de MinIO. Aucune restauration jamais testée. Et la
migration (phase 4) **exige un dump**. → §4.2, §10.2

```bash
[ ] # Dump base
    pg_dump --format=custom --no-owner --no-privileges \
            --file=oceane-prod-$(date +%F).dump "$PROD_DSN"

[ ] # Restauration de contrôle (obligatoire — un dump non restauré n'est pas une sauvegarde)
    createdb oceane_audit
    pg_restore --dbname=oceane_audit --no-owner --no-privileges oceane-prod-*.dump
    psql oceane_audit -c "SELECT count(*) FROM orders;"   # comparer à la prod

[ ] # Copie objet MinIO
    mc alias set src https://s3.oceanetorresphotographie.fr "$KEY" "$SECRET"
    mc mirror --preserve src/galleries ./minio-backup/galleries
    mc ls --recursive src/galleries | wc -l    # comparer à la copie

[ ] # Rendre récurrent : pg_dump quotidien (rétention 30 j) + mc mirror hebdomadaire
[x] # Documenter la procédure de restauration dans deploy/README.md
```

**Recette** : une restauration complète réussie sur une base séparée, avec comptages identiques.

## 0.3 ⛔🔴 Base de développement locale — 2 h · #57

**Pourquoi** : le compose documente lui-même que l'application de dev utilise **Supabase**. Il n'y a
pas d'alternative locale — c'est la cause racine du risque `make fresh`. → §12.1

```yaml
[x] # docker-compose.yml — ajouter un service SANS profil
    postgres-dev:
      image: postgres:16-alpine
      container_name: ot-postgres-dev
      environment:
        - POSTGRES_USER=postgres
        - POSTGRES_PASSWORD=postgres
        - POSTGRES_DB=oceane_dev
      ports: ["55433:5432"]
      volumes: [pgdata-dev:/var/lib/postgresql/data]
      healthcheck:
        test: ["CMD-SHELL", "pg_isready -U postgres -d oceane_dev"]
```

```bash
[ ] # .env racine et api/.env → pointer 127.0.0.1:55433 / oceane_dev
[ ] # MAIL_MAILER → smtp Mailpit (127.0.0.1:1025), JAMAIS Brevo
[ ] # Charger un dump anonymisé de production dans oceane_dev
```

**Recette** : `php artisan tinker --execute="echo config('database.connections.pgsql.host');"`
ne renvoie **plus** un hôte Supabase · un mail de test arrive dans Mailpit, pas chez une cliente.

## 0.4 ⛔🔴 Vérifier le compte administrateur du seeder — 5 min · #2

```sql
[ ] SELECT id, email, role, created_at, updated_at FROM users
    WHERE email IN ('admin@oceanetorres.fr', 'client@test.com');

[ ] -- Inventaire des comptes privilégiés
    SELECT id, email, role, created_at, email_verified_at FROM users WHERE role = 'admin';
```

→ Si `admin@oceanetorres.fr` existe : **le supprimer ou changer son mot de passe immédiatement**.
Le mot de passe du seeder est `admin123`, et `/auth/login` n'a aucun rate limit. → §6.14

## 0.5 🔴 Supprimer `downloadFile()` — 15 min · #1

**Pourquoi** : route **publique**, sans authentification, avec traversée de répertoire
(`?file=../../../.env` → identifiants Supabase, MinIO, SumUp, Brevo, `APP_KEY`) **et** suppression
arbitraire (`deleteFileAfterSend(true)`). La fonctionnalité est morte. → §5.9, §6.10.1

```bash
[x] # Supprimer routes/api.php:75
    #   Route::get('/galleries/{gallery}/download-file', [GalleryController::class, 'downloadFile']);
[x] # Supprimer GalleryController::downloadFile() (lignes 419-431)
[x] # Purger les vestiges : 140 Mo de ZIP oubliés
    rm api/storage/app/temp/*.zip
```

## 0.6 🔴 Corriger `storage:clean-orphans` — 1 h · #58

**Pourquoi** : la liste de chemins référencés **omet** `file_path_thumbnail_clean`,
`metadata->storage_path` et `metadata->supabase_path`. Avec `--delete`, la commande supprimerait
**toutes les vignettes sans filigrane** et **les originaux HD des photos d'ère Supabase**
(irrécupérables, et achetées par des clientes). → §12.2

```php
[x] // app/Console/Commands/CleanOrphanFiles.php
    Photo::query()
        ->select(['id', 'file_path', 'file_path_hd', 'file_path_preview', 'file_path_thumbnail',
                  'file_path_thumbnail_clean',   // ← AJOUT
                  'file_path_web', 'file_path_watermark',
                  'metadata'])                   // ← AJOUT
        ->chunkById(500, function ($photos) use (&$referencedPaths) {
            foreach ($photos as $photo) {
                // … les 6 colonnes existantes …
                $referencedPaths->push($photo->file_path_thumbnail_clean);        // ← AJOUT
                $referencedPaths->push($photo->metadata['storage_path']  ?? null); // ← AJOUT
                $referencedPaths->push($photo->metadata['supabase_path'] ?? null); // ← AJOUT
            }
        });
```

```bash
[x] # Écrire un test (aucun n'existe) : une photo dont l'original est référencé
    #  uniquement par metadata->supabase_path ne doit PAS être listée comme orpheline
[ ] # Vérifier en dry-run sur la production AVANT toute autre action
[x] # NE PAS planifier en --delete. Planifier en dry-run hebdomadaire avec rapport (#43)
```

## 0.7 🔴 Supprimer `ClientController::destroy()` — 30 min · #59

**Pourquoi** : anonymise les commandes **payées** (viole la rétention légale et contredit
`PersonalDataEraser`), **plante** sur `contact_messages.email` NOT NULL, **sans transaction** → état
partiel irréversible, **sans audit**, **sans confirmation**. Le module RGPD fait déjà le travail
correctement. → §12.3

```bash
[x] # Supprimer Admin/ClientController::destroy() et la route apiResource correspondante
    #   Route::apiResource('clients', ClientController::class)->except(['destroy']);
[x] # Supprimer clientApi.deleteClient() côté front + le bouton de suppression
[x] # Rediriger l'admin vers /admin/privacy (écran RGPD) avec un lien explicite
```

```sql
[ ] -- Cette méthode a-t-elle déjà tourné ? À ajouter au relevé de la phase 1
    SELECT count(*) FROM orders WHERE guest_first_name = 'Supprimé' AND status = 'paid';
    -- Non nul = des commandes payées ont perdu l'identité de leur client
```

## 0.8 🔴 Restaurer l'IP cliente réelle — 1 h · #4

**Pourquoi** : `$request->ip()` renvoie `172.19.0.1` (passerelle Docker). **Trois conséquences** :
les 5 rate limiters comptent tout le monde ensemble (donc protègent personne **et** se transforment en
déni de service, #61), et `orders.consent_ip` / `privacy_audit_logs.ip_address` n'ont aucune valeur
probante. → §4.7, §6.7, §12.5

```nginx
[x] # deploy/nginx.prod.conf — bloc location ~ \.php$
    fastcgi_param HTTP_X_FORWARDED_FOR $http_cf_connecting_ip;
    fastcgi_param REMOTE_ADDR          $http_cf_connecting_ip;
    # (ou set_real_ip_from <plages Cloudflare>; real_ip_header CF-Connecting-IP;)
```

```php
[x] // bootstrap/app.php — restreindre au lieu de faire confiance à tout
    $middleware->trustProxies(at: [
        '173.245.48.0/20', '103.21.244.0/22', /* … plages Cloudflare … */
        '172.16.0.0/12',   // réseau Docker
    ]);
```

**Recette** : `SELECT DISTINCT consent_ip FROM orders ORDER BY created_at DESC LIMIT 20;` montre des
IP publiques variées · aucun `429` inexpliqué dans les logs Nginx.

## 0.9 🔴 Ajouter les rate limits manquants — 1 h · #5

**Pourquoi** : le groupe `api` ne déclare aucun `throttle` par défaut. Aucune limite sur le login
(bruteforce), l'inscription, le mot de passe oublié (inondation d'une boîte tierce), l'historique de
commandes par email, ni l'énumération des codes de galerie (36⁶/N essais). → §6.7

```php
[x] // AppServiceProvider::configureRateLimiting()
    RateLimiter::for('auth',     fn (Request $r) => [
        Limit::perMinute(5)->by($r->ip()),
        Limit::perMinute(5)->by((string) $r->input('email')),   // par compte aussi
    ]);
    RateLimiter::for('gallery-code', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
    RateLimiter::for('public-form',  fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
```

```php
[x] // routes/api.php
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/register',        …);
        Route::post('/auth/login',           …);
        Route::post('/auth/forgot-password', …);
        Route::post('/orders/by-email',      …);
    });
    Route::middleware('throttle:gallery-code')
        ->get('/galleries/code/{code}',      …);
    Route::middleware('throttle:public-form')->group(function () {
        Route::post('/contact',              …);
        Route::post('/booking-request',      …);
    });
```

⚠️ **Sans l'action 0.8, ces limites sont inopérantes.** Les deux vont ensemble.

## 0.10 🔴 Corriger la déconnexion — 30 min · #3

**Pourquoi** : `currentAccessToken()` renvoie un `TransientToken` quand l'authentification passe par
la session — classe qui **n'a pas de méthode `delete()`** → 500. Le front avale l'erreur
(`catch {}`). Résultat : « se déconnecter » ne révoque **ni la session, ni le token 30 jours**.
→ §6.2.2

```php
[x] // AuthController::logout()
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'Déconnexion réussie.']);
    }
```

```js
[x] // web/src/stores/auth.ts — retirer le catch silencieux qui a masqué le bug
    async function logout(): Promise<void> {
        try { await authApi.logout() }
        catch (e) { toast.error('Déconnexion partielle', 'Réessayez.'); throw e }
        finally { clearSession() }
    }
```

**Recette** : après déconnexion, `GET /api/auth/user` renvoie 401 · la ligne
`personal_access_tokens` a disparu.

## 0.11 🔴 Corriger la quantité sur les 4 documents — 1 h · #6

**Pourquoi** : la quantité est absente de la facture PDF **et des 3 emails**. Les lignes de facture ne
totalisent pas le sous-total, et surtout **l'email de notification des tirages fait imprimer le mauvais
nombre d'exemplaires**. → §7.6

```blade
[x] {{-- Dans les 4 gabarits : pdf/invoice, emails/order-confirmation,
         emails/school-order-confirmation, emails/print-order-notification --}}
    <td>
        {{ $item->photo_title ?? 'Photo' }}
        <br><span class="product-type">{{ $item->getProductTypeLabel() }}</span>
        @if($item->quantity > 1)
            <br><span>{{ $item->quantity }} × {{ number_format($item->price, 2, ',', ' ') }} €</span>
        @endif
    </td>
    <td>{{ number_format($item->price * $item->quantity, 2, ',', ' ') }} €</td>
```

```sql
[ ] -- Quelles factures déjà émises sont concernées ? (à joindre au relevé phase 1)
    SELECT o.order_number, i.invoice_number, o.paid_at,
           SUM(oi.price) AS somme_lignes, SUM(oi.price * oi.quantity) AS somme_reelle
    FROM orders o JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN invoices i ON i.order_id = o.id
    WHERE o.status = 'paid'
    GROUP BY o.id, o.order_number, i.invoice_number, o.paid_at
    HAVING SUM(oi.price) <> SUM(oi.price * oi.quantity);
```

[ ] Décider avec la photographe s'il faut **réémettre** les factures concernées. 👤

## 0.12 🔴 Supprimer le `v-html` — 5 min · #62

**Pourquoi** : `customer_email` (donnée utilisateur) interpolée sans échappement dans du HTML rendu
par `v-html`. Sink XSS, aggravé par l'absence de CSP et le token 30 jours en `localStorage`. → §13.1

```vue
[x] <!-- views/OrderConfirmation.vue:42 — remplacer le v-html par du balisage -->
    <p class="text-sm text-gray-600 mt-1">
        {{ confirmationIntro }} <strong>{{ order.customer_email }}</strong>.<br>
        {{ confirmationOutro }}
    </p>
```
[x] Réécrire `confirmationMessage` en deux computed de **texte brut** (`confirmationIntro`, `confirmationOutro`).
[x] Vérifier par recherche qu'il ne reste **aucun** `v-html` dans `web/src`.

## 0.13 🔴 Durcir la politique de mot de passe — 15 min · #27

```php
[x] // AppServiceProvider::boot()
    Password::defaults(fn () => Password::min(12)->uncompromised());
```
```php
[x] // Admin/StoreUserRequest — aligner (aujourd'hui min:8, sans `confirmed`)
    'password' => ['required', 'confirmed', PasswordRule::defaults()],
```

## 0.14 🟠 Corriger l'export scolaire — 10 min · #60

**Pourquoi** : `file_path_hd ?? file_path` contourne la cascade de résolution → **une photo achetée
peut être silencieusement absente du ZIP de production**. → §12.4

```php
[x] // SchoolSessionExportService:87
    $sourcePath = $photo->resolved_storage_path;   // au lieu de file_path_hd ?? file_path
```
[x] Ajouter un compteur d'omissions au rapport d'export, pour rendre le silence visible.

## 0.15 🟠 Brancher la supervision externe — 2 h · #12

**Pourquoi** : les 3 filets qui couvrent l'angle mort structurel (« si le scheduler meurt, il ne peut
pas prévenir de sa propre mort ») sont **spécifiés et non branchés**. Ce sont des créations de compte
gratuites. → §8.10, `docs/supervision.md` §5 et §11

```bash
[ ] # UptimeRobot : 2 moniteurs (procédure exacte en docs/supervision.md §5.1)
    #   - API   : mot-clé "status":"ok" sur https://api.…/api/health, 5 min
    #   - Front : HTTP(s) sur https://oceanetorresphotographie.fr/, 5 min
    #   - Alerte après 2 vérifications consécutives

[ ] # healthchecks.io : 2 checks (procédure en docs/supervision.md §5.2)
    #   - Purge RGPD hebdo    : cron 0 4 * * 0, grâce 2 h
    #   - Réconciliation      : cron */10 * * * *, grâce 20 min
    #   → coller les URL dans deploy/.env.prod :
    #     HEALTHCHECKS_RGPD_URL / HEALTHCHECKS_RECONCILE_URL

[ ] # Objet témoin MinIO (#8 de la §8.15)
    SUPERVISION_STORAGE_WITNESS=.supervision/witness.txt
    mc cp witness.txt src/galleries/.supervision/witness.txt

[ ] # Ajouter un ping à privacy:purge-expired (mensuel, silence indétectable aujourd'hui)
[ ] # SUPERVISION_ALERT_EMAIL → adresse dédiée, pas le Gmail de la photographe (#10 §8.15)
```

## 0.16 🟠 Veille de vulnérabilités — 1 h · #26

```yaml
[x] # .github/workflows/ci.yml — 2 étapes
    - name: Composer audit
      working-directory: ./api
      run: composer audit --no-interaction
    - name: NPM audit
      working-directory: ./web
      run: npm audit --audit-level=high
```
```yaml
[x] # .github/dependabot.yml (nouveau)
    version: 2
    updates:
      - package-ecosystem: composer
        directory: /api
        schedule: { interval: weekly }
      - package-ecosystem: npm
        directory: /web
        schedule: { interval: weekly }
```

## 0.17 🟠 Séparer les files d'attente — 2 h · #10

**Pourquoi** : une seule file, un seul worker. Un export de 3600 s bloque **toutes** les confirmations
de commande, factures et SMS. → §8.3

```php
[x] // Temps réel
    Mail::to($email)->queue((new OrderConfirmationMail(…))->onQueue('realtime'));
    SendSmsJob::dispatch(…)->onQueue('realtime');
[x] // Traitements lourds
    GenerateSchoolSessionExportJob::dispatch($id)->onQueue('heavy');
    GeneratePrivacyExportJob::dispatch($id)->onQueue('heavy');
    ProcessSchoolSessionJob::dispatch($id)->onQueue('heavy');
    ProcessPhotoJob::dispatch(…)->onQueue('heavy');
```
```yaml
[x] # deploy/docker-compose.prod.yml — service queue
    command: php artisan queue:work --queue=realtime,default,heavy
             --sleep=3 --max-time=3600 --timeout=3600 --memory=1024
    #                                                        ↑ #9 §8.15 : sous memory_limit
```
**Recette** : lancer un export de session scolaire, passer une commande de test en parallèle,
vérifier que l'email de confirmation arrive **pendant** l'export.

## 0.18 🟡 Correctifs courts complémentaires

```php
[x] // #61 — l'anti-bruteforce des codes promo devient un DoS avec une IP constante.
    //        Corrigé par 0.8, mais durcir la clé : combiner IP + session panier.
    $throttleKey = 'gift-code-failures:'.sha1($request->ip().'|'.$cart->id);
```
```php
[x] // #36 — EventCategoryController::reorder est cassé (DB::raw n'accepte pas de bindings).
    //        Appliquer le correctif déjà fait dans PhotoController::updateSortOrder.
    DB::transaction(function () use ($validated) {
        foreach ($validated['categories'] as $c) {
            EventCategory::where('id', $c['id'])->update(['sort_order' => (int) $c['sort_order']]);
        }
    });
```
```php
[x] // #37 — $this->authorize() sans le trait → 500 sur 3 routes de réservation.
    class Controller { use \Illuminate\Foundation\Auth\Access\AuthorizesRequests; }
    //  (ou supprimer les 3 routes mortes — cf. phase 2)
```
```php
[x] // #32 — cgv_version en dur à '1.0' → traçabilité inopérante.
    'cgv_version' => config('shop.cgv_version', '1.0'),   // + clé dans config/shop.php
```
```php
[x] // #42 — plafond ZIP de 500 photos silencieux → le rendre explicite dans la réponse
[x] // #46 — SESSION_DOMAIN=api.oceanetorresphotographie.fr (au lieu du wildcard)
[x] // #54 — retirer les add_header de nginx.prod.conf (X-Frame-Options contradictoire)
[x] // #53 — aligner APP_VERSION sur la version réelle
[x] // #65 — retirer `sortable: true` des 8 colonnes (tri non branché) → §13.4
[x] // #19 — épingler la version d'imagick dans le Dockerfile (driver non déterministe)
```

## 0.19 Recette de la phase 0

```
[ ] Aucune commande destructrice atteignable depuis le Makefile
[ ] Un dump restauré avec succès sur une base séparée
[ ] La base de dev locale fonctionne, api/.env ne pointe plus la prod
[ ] Le compte admin du seeder est absent ou son mot de passe est changé
[ ] GET /api/galleries/{id}/download-file → 404
[ ] storage:clean-orphans en dry-run ne liste plus les thumbnail-clean
[ ] DELETE /api/admin/clients/{id} → 404 ou 405
[ ] consent_ip contient des IP publiques variées
[ ] 6 tentatives de login en 1 min → 429
[ ] Après logout, GET /api/auth/user → 401
[ ] Une facture de test avec quantité 3 affiche « 3 × 10,00 € » et un total de 30,00 €
[ ] Aucun v-html dans web/src
[ ] UptimeRobot et healthchecks.io reçoivent des signaux
[ ] La CI échoue sur une dépendance vulnérable connue
[ ] Un email de confirmation arrive pendant un export scolaire
```

---
# PHASE 1 — Relever et décider — **RÉDUITE, non exécutée**

**En quoi elle consistait.** L'audit n'avait consulté aucune donnée réelle : tout le plan
reposait sur des hypothèses. La phase prévoyait 2,5 jours pour les confirmer — 14 blocs de
requêtes sur une copie restaurée de la production, un document « Relevé de production », puis
huit décisions à trancher. Elle prévoyait aussi de constituer une base de développement
complète sur données réelles anonymisées.

**Pourquoi on ne la fait pas.** On travaille sur la base et le MinIO de **preprod** : c'est un
environnement de test, le casser temporairement est sans conséquence. Une base de test
complète n'apporterait rien au travail réel, qui est d'améliorer et de corriger le code. La
production n'est pas touchée.

**Ce qui a quand même été mesuré**, et qui reste utile parce que les phases 2 à 4 s'appuient
dessus — détail en §1bis :

- 5350 photos, 196 galeries, 211 commandes, 119 factures, 18 utilisateurs, 17 clients
- `reservations`, `gift_cards`, `availability_slots`/`_patterns`, `client_forms`,
  `subscriptions`/`_items`, `privacy_audit_logs` : **toutes vides**
- `supabase_path` renseigné sur **0 des 5350 photos** → le risque n°1 de l'audit et le
  principal danger de la phase 4 n'existent pas
- 0 photo dont le fichier manque dans MinIO ; 114/114 PDF de facture présents
- 0 compte issu du seeder en production

**Les huit décisions** — conservées ici parce que les phases suivantes les référencent par
leur identifiant. Seules D1 et D3 sont tranchées ; les autres attendent un arbitrage produit.

| # | Sujet | État |
|---|---|---|
| D1 🔴 | Couche booléenne / pooler PostgreSQL · #14 | ✅ **tranchée** — option C, `PostgresConnection::prepareBindings()`. Ni A ni B du plan : voir §1bis « D1 tranchée » |
| D2 🟠 | Modélisation de `galleries` · #39 | ⏳ recommandation : table unique + discriminant explicite + scopes obligatoires + contrôleurs séparés par audience |
| D3 👤 | Module Disponibilités · code mort | ✅ **tranchée** — tables vides, supprimées en phase 2.1 |
| D4 👤 | Bons cadeaux | ⏳ recommandation : outiller le registre manuel (PDF + email depuis l'admin). Table vide, donc sans urgence |
| D5 👤 | Remboursement et avoir · #16 | ⏳ recommandation : remboursement déclaratif + avoir numéroté `AV-YYYY-NNNNN` |
| D6 👤 | Portfolio administrable · #40 | ⏳ recommandation : portfolio en base servi par l'API, en dernière position |
| D7 👤 | Contacts scolaires · #29 | ⏳ recommandation : persister dans `school_session_contacts` avec purge automatique |
| D8 🔴 | Périmètre de l'authentification · #13, #28 | ⏳ à trancher — impacte 2.5 |

👤 = décision produit, nécessite la photographe.

---

# PHASE 2 — Fondations

**8 jours. Iso-fonctionnel : aucun comportement métier ne change.**

C'est la phase qui traite les 5 dettes structurelles. À l'issue, le projet est assaini sans qu'aucune
fonctionnalité n'ait bougé — **point de contrôle naturel** pour décider de continuer.

## 2.1 Suppressions — 1 j

**~1 050 lignes de PHP, 33 routes, 2 tables, ~15 colonnes.** Aucun risque fonctionnel. → §9.4, §12.8

### Backend

```
[x] Module Disponibilités (si D3 = supprimer) — 543 l.
    [x] AvailabilityController (199 l.) + les 12 routes
    [x] Models AvailabilitySlot, AvailabilityPattern (217 l.)
    [x] 5 FormRequests Admin/*Availability* (127 l.)
    [x] Migration de suppression des 2 tables + reservations.availability_slot_id
[x] 6 migrations Cashier/Stripe (200 l.) + 2 tables + 4 colonnes de users
[x] OrderPolicy (65 l.) — jamais appelée, logique recopiée ailleurs
[x] ReservationPolicy (37 l.) — supprimée : ses 5 routes clientes l'ont été aussi
[x] Edge Function Supabase (82 l.) + réécrire supabase/README.md
[x] Cache de filigrane désactivé (~45 l.) — OU le réactiver et mesurer (Imagick, ÷21 rendus)
[x] SumUpService : processCheckout (⚠️ manipule des données de carte), completeCheckout,
    getPaymentMethods (~60 l.)
[x] Cart::order() — relation cassée, carts n'a pas de colonne order_id
[x] Photo::getDownloadUrlAttribute() — raccourci de chemin, aucun appelant
[~] Photo::display_url — @deprecated mais dans $appends
    → CONSERVÉ : 4 vues front s'en servent comme repli intermédiaire. Le retirer n'est pas
      iso-fonctionnel. PhotoResource l'expose donc encore explicitement.
[x] Colonnes mortes : photos.file_path_web, photos.file_path_watermark, gift_cards.pdf_path
    (⚠️ confirmer vides au relevé 1.2 d'abord)
[~] Valeurs d'enum mortes   (⚠️ CONSERVER orders.status='refunded' — cf. D5)
    [x] clients.source='contact' — aucune écriture, seul un scope mort le lisait. Migration
        réversible, éprouvée par un aller-retour sur la preprod. Les 3 scopes
        `fromReservation` / `manual` / `fromContact` partent avec : zéro appelant.
        ⚠️ le plan oubliait que 'manual' est VIVANT — `Admin\ClientController::store`
           l'écrit et c'est le défaut de la colonne.
    [x] availability_slots.status — sans objet, la table est supprimée
    [ ] gift_cards.status='cancelled' — laissé en attente de D4. Le module entier est
        suspendu à cette décision produit ; si D4 retient l'option A, un statut annulé
        est plausiblement voulu. Le retirer pour le remettre serait du bruit.
```

### Routes sans consommateur — 33 endpoints → §2.16

```
[x] Supprimer : /reservations CRUD client (5) — fait, seules les 6 routes admin restent,
    apiResource /admin/users (5), GET /gift-cards/{id}, /gift-cards/validate/{code},
    GET /galleries/{id}, /galleries/token/{token}, GET /photos/{photo},
    /my-galleries (2), POST /admin/notifications, GET /admin/health,
    PUT /auth/profile, /admin/photos/bulk-downloadable, /admin/photos/sort-order
[~] FINIR au lieu de supprimer : forgot-password + reset-password (interface manquante),
    → ce n'est pas une suppression mais la construction d'une interface : relève de la
      phase 3, pas de 2.1. Laissé ici pour ne pas perdre la trace.
    GET /orders/{id}/invoice + /admin/orders/{id}/invoice (aucun bouton),
    POST /gift-cards (selon D4)
[~] BRANCHER au lieu de supprimer : GET /admin/dashboard + /admin/dashboard/stats (§13.2)
    → câblage, donc phase 3 (§3.6). Même remarque.
```

### Front

```
[x] adminApi.uploadPhotos / uploadEventPhotos — pointent vers des routes inexistantes
[x] api.getGalleryItems() — le portfolio est statique
[x] api.getGiftCards() — appelle une route qui n'existe pas publiquement
[x] L'événement @sort du DataTable + les 8 déclarations sortable: true — retirées en 2.1,
    le tri est branché côté API en 2.4 (sort_by/sort_dir avec allowlist)
```

### Fichiers et dossiers

```
[x] api/storage/app/temp/*.zip — 140 Mo → mesuré à 0 o
[x] bootstrap/, database/, resources/, storage/ à la RACINE — squelette résiduel suivi par Git
[x] Purger storage/app/private/exports/ (les ZIP s'accumulent sans purge, §10.0)
    → commande `exports:purge`, planifiée quotidiennement à 04h30. Conservation : 30 j
      pour les exports scolaires, **7 j pour les exports RGPD** — un ZIP de données
      personnelles n'a pas à traîner sur disque.
    → `--delete-orphans` traite les ZIP sans ligne en base : c'est ce qu'étaient les
      **4,8 Go mesurés** ici, 3 fichiers de mai 2026 sans aucune ligne correspondante.
      Purgés, dossier à 0 o. Supprimer un ZIP est sans risque : `downloadExport` répond
      410 et l'interface propose de régénérer.
    → 6 tests, dont « un orphelin récent est épargné » (un export en cours d'écriture).
```

## 2.2 D1 — Traiter la couche booléenne — 1 à 3 j · #14

Selon la décision D1.

```
[x] Appliquer l'option retenue (config/database.php)
[x] Si la lecture des booléens est correcte sans le trait :
    [x] Supprimer Models/Concerns/CastsBooleansForPostgres.php
    [x] Retirer le trait des 12 modèles
    [x] Remplacer les 20 whereRaw par des where() natifs
    [x] Supprimer le whereRaw('1 = 0') (garde « ne renvoie rien »)
[x] Faire tourner la suite de tests complète (226 méthodes) — c'est le filet de cette étape
[x] Vérifier manuellement les scopes booléens : galeries publiées, photos téléchargeables,
    photos aimées, prestations actives
```

## 2.3 Redis — cache et sessions — 1 j · #24, #17

**Un seul changement résout trois problèmes** : le couplage caché de la supervision via le bind mount,
l'impossibilité de cacher les URL signées MinIO (~19 s de latence évitée sur 200 photos), et les
sessions liées au système de fichiers. → §4.7, §5.11, §2.3

```yaml
[x] # docker-compose (dev et prod)
    redis:
      image: redis:7-alpine
      command: redis-server --maxmemory 512mb --maxmemory-policy allkeys-lru
      healthcheck: { test: ["CMD", "redis-cli", "ping"] }
```
```dotenv
[x] CACHE_STORE=redis
    SESSION_DRIVER=redis
    REDIS_HOST=redis
```
```
[x] Vérifier que les heartbeats de supervision restent lisibles par api-php
    (c'est justement le couplage que Redis supprime)
[x] Réactiver le cache des URL signées MinIO dans Photo::resolveImageUrl (TTL 12 h,
    signature valable 24 h → marge de 12 h) → §2.3
[x] #17 — NE PLUS mettre les binaires d'images en cache : persister le dérivé sur MinIO
    (comme generateAndStoreCleanThumbnail) et servir une URL signée
```

## 2.4 D4 — Convention de réponse API — 2 j · #20

**Le chantier au meilleur rapport bénéfice/effort** : il supprime mécaniquement une grande part des
1 000 lignes de types front et la tentation du `fetch()` sauvage. → §3.7

```
[x] Choisir UNE enveloppe et la documenter dans docs/api-conventions.md
    Retenu : { data: …, meta?: { pagination }, message?: … } — SANS clé `success`
             erreurs : { message: …, errors?: { champ: [messages] } }
    ⚠️ documentée et arrêtée, mais PAS encore appliquée — voir la dernière ligne
[~] Créer une API Resource pour CHAQUE ressource exposée (4 au départ, 11 aujourd'hui)
    [x] OrderResource + AdminOrderResource + OrderItemResource
    [x] GalleryResource + DownloadableGalleryResource + PhotoResource (endpoints publics)
    [x] PrestationResource (public + admin)
    [x] UserResource (4 endpoints d'authentification)
    [x] ReservationResource (tableau de bord client)
    [ ] formes ADMIN de galerie/photo, Client, SchoolSession
        → laissées volontairement : leurs vues consomment beaucoup de champs calculés
          (download_status, total_downloads_count, client_id, photos_count) et je ne peux
          pas parcourir l'interface pour valider. Passage dédié.
[x] Supprimer Admin/OrderController::formatOrder() (API Resource déguisée en méthode statique)
[x] Pagination : accepter per_page PARTOUT (4 endpoints sur 15 au départ), plafonné à 100
[~] Ajouter sort_by / sort_dir sur les endpoints listés dans un DataTable (#65)
    [x] trait PaginatesQueries avec allowlist par endpoint
    [x] /admin/reservations, /admin/galleries
    [x] /admin/orders, /admin/clients, /admin/school-sessions, /admin/gift-codes,
        /admin/privacy/audit, /gift-cards — toutes passent par `paginated()` + `sorted()`.
        `latest()` valait `created_at desc`, soit le défaut de `sorted()` : l'ordre par
        défaut est inchangé.
[x] Front : une seule couche d'accès (fusionner BaseApiService et BaseAdminService)
    → l'audit annonçait 2 couches parallèles : faux, BaseAdminService héritait déjà.
      Le vrai écart était request() imposant Content-Type: application/json et 10 s.
[x] Front : supprimer les 8 fetch() sauvages qui contournent la couche services (§2.0)
    → 12 trouvés, tous traités. Les 3 fetch(download_url) vérifient enfin response.ok.
[~] Régénérer types/ depuis la nouvelle convention
    [x] types partagés `types/gallery.ts`, dérivés des Resources et de leurs tests de forme
    [x] les 4 vues publiques de galerie n'ont plus une seule interface locale :
        **54 → 34** interfaces locales dans le front, −167 lignes dans ces fichiers
    [ ] types admin — la forme admin n'est pas encore garantie par des Resources
[~] ⚠️ Enveloppe unique — **NON APPLIQUÉE, décision assumée**
    Ce qui est fait : la convention est écrite et arrêtée, les Resources garantissent la
    forme des endpoints publics, et les types front en découlent au lieu de la deviner.
    Ce qui ne sera pas fait maintenant, et pourquoi :
    → rupture de contrat sur 143 endpoints, pour un gain de **cohérence interne** —
      ni fonctionnel, ni de sécurité.
    → les 49 tests front couvrent les stores et les utilitaires ; **aucun ne rend une
      vue**. Le typecheck ne voit pas une clé de réponse renommée à l'exécution.
    → cette session a montré le motif : chaque changement de forme a produit une
      conséquence non anticipée (chaîne `:src` devenue `string | undefined`, `isset()`
      déclenchant une résolution d'attribut, `sort_order` supprimé à tort). Toutes ont été
      rattrapées par tsc ou par un test. L'enveloppe touche le rendu, où ce filet n'existe pas.
    **Condition pour la lever** : soit des tests de rendu sur les parcours critiques
    (galerie par code, tunnel d'achat, listes d'administration), soit un point unique de
    décodage dans la couche d'accès front — auquel cas la migration devient endpoint par
    endpoint, sans toucher une vue.
```

## 2.5 D5 — Couche d'autorisation unique — 1,5 j · #21

Quatre mécanismes contradictoires aujourd'hui ; la règle des « 30 minutes » est écrite **4 fois**.
→ §3.5, §6.5

```
[x] Ajouter le trait AuthorizesRequests à la classe Controller de base (#37)
[x] Réécrire les Policies et les invoquer TOUJOURS via le Gate
    → 2 Policies et non 3 : ReservationPolicy est morte en 2.1 avec ses 5 routes clientes.
      GalleryPolicy conservée, OrderPolicy créée.
[x] Supprimer les vérifications inline dupliquées :
    [x] OrderController::show — autorisait sur `created_at`
    [x] OrderService::getOrderForDownload — autorisait sur `paid_at`
        ⚠️ les deux règles DIVERGEAIENT : ce n'était pas une duplication mais deux
           politiques différentes pour la même question
    [x] GalleryController — app(GalleryPolicy::class)->download, hors du Gate
[x] #— Remplacer la règle « 30 minutes » par un token signé à courte durée émis au checkout
    → App\Support\OrderAccessSignature : HMAC de `id|expiration` sous APP_KEY, 2 h,
      placé dans le return_url SumUp et renvoyé par confirmFreeOrder
    ⚠️ la règle n'était PAS morte : le return_url ne portait que ?order=id, et le jeton de
       téléchargement n'existe qu'À la confirmation du paiement. La supprimer sèchement
       cassait le parcours invité.
[x] #— Sécuriser POST /orders/by-email
    → RETIRÉ plutôt que sécurisé : aucun consommateur ne l'appelait, et il renvoyait
      toutes les commandes d'une adresse sans authentification. docs/authorization.md §6
      décrit comment le rétablir sans en faire un oracle d'énumération.
[x] #34 Décider : soit preview/thumbnail passent derrière le token de galerie, soit documenter
    → **option A retenue** : les deux endpoints exigent désormais un credential. Ils
      n'exigeaient RIEN — l'UUID d'une photo suffisait, définitivement.
    → credential = **code de partage**, pas jeton d'accès : c'est celui que détient la
      visiteuse, et une URL est une chaîne que `$hidden` ne filtre pas — y mettre
      `access_token` rouvrirait l'escalade vers les dérivés propres.
    → coût mesuré nul : sur les 5350 photos de production, **5350** ont leur dérivé
      généré, donc aucune n'emprunte ce chemin en fonctionnement normal.
    → 🔴 **trouvé en cours de route** : `Gallery` n'invalidait aucun cache, alors que
      `ImageProxyController` garde la photo AVEC sa galerie 1 h. L'ancien code serait
      resté valable jusqu'à une heure après régénération — la correction aurait été
      cosmétique. `Gallery::booted()` purge `photo_meta_*` sur rotation de credential.
    → fenêtre résiduelle assumée de 24 h sur les URL signées MinIO déjà distribuées,
      documentée dans `docs/authorization.md` §6bis.
    → 16 tests, dont « régénérer le code révoque l'accès » et « le code de partage
      n'ouvre pas les dérivés propres ».
[x] Documenter la matrice « qui a le droit de quoi » dans docs/authorization.md
```

**Trouvé en chemin, hors périmètre annoncé** : `Gallery::isAccessible(null)` rendait `true`
pour une galerie `type = 'public'`, et trois endpoints servant du contenu **sans filigrane**
s'appuyaient dessus. Corrigé par `grantsCleanDerivatives()`. Piège latent — la production ne
compte aucune galerie `public`. Détail en §1bis.

## 2.6 Déploiement reproductible — 1 j · #22, #8

```
[x] Créer une cible Dockerfile `production-fpm` (php-fpm, composer --no-dev, code COPIÉ)
    → bâtie sur `base`, qui est déjà php-fpm. Image construite et vérifiée : code présent,
      vendor sans dépendances de dev, imagick chargé, `artisan --version` répond sans
      aucun montage. Volontairement PAS de `config:cache` au build : il figerait
      l'environnement de construction, pas celui d'exécution.
    → la cible `production` (php-cli + artisan serve, destinée à Render) est SUPPRIMÉE :
      référencée par aucun compose ni script, elle dupliquait tout le socle de `base` et
      portait `migrate --force` dans son CMD.
[x] deploy/docker-compose.prod.yml : target production-fpm, image taguée, PLUS de bind mount ./api
    → `image: oceane-api:${API_IMAGE_TAG:-latest}` sur les 3 services (api, queue, scheduler)
    → `./api:/var/www` remplacé par `./api/storage:/var/www/storage` seul. Choix
      délibéré : les exports générés et les journaux vivent là et doivent survivre à un
      redéploiement. Un volume nommé aurait coupé l'accès aux fichiers déjà présents.
    → appliqué aussi à la PREPROD : une préprod qui ne déploie pas comme la production ne
      valide pas le déploiement.
[x] Sortir `php artisan migrate --force` du command: → étape explicite du deploy.sh,
    après confirmation
    → il tournait au démarrage de CHAQUE conteneur, donc à chaque redémarrage, sans
      surveillance — et les 3 services partageant l'image le lançaient en parallèle.
    → `deploy.sh` affiche les migrations en attente, rappelle la sauvegarde, et demande
      une confirmation explicite (`--yes` pour l'usage non interactif). En préprod,
      pas de confirmation : c'est l'environnement fait pour éprouver une migration.
[x] Récupérer et versionner la configuration Render (#56) → render.yaml
    ⚠️ Render ne reprend pas un service existant à partir du fichier : il faut le
      rattacher via Blueprints. Tant que ce n'est pas fait, le fichier documente la cible
      sans la piloter — c'est écrit en tête du fichier.
[x] Vérifier que le rollback est un changement de tag d'image, pas un git checkout
    → `deploy.sh --list` montre les tags disponibles et celui qui tourne ;
      `deploy.sh --rollback <tag>` redémarre dessus sans reconstruire. Les deux chemins de
      garde sont testés. Le tag est le SHA court du commit.
    → le retour arrière ne touche PAS la base : annuler une migration est une décision
      distincte, et le script le dit explicitement.
[x] #55 Supprimer les directives [Session] inertes de php.ini
    → Laravel gère ses sessions via SESSION_DRIVER et n'appelle jamais session_start().
      Les 6 directives `session.*` ne servaient qu'au gestionnaire natif de PHP. La raison
      est consignée dans le fichier, parce que `session.cookie_secure = 0` pouvait faire
      croire à une régression de sécurité.
```

**Ce qui reste vrai et non couvert** : rien de tout ceci n'est validé par un déploiement
réel. L'image est construite et exécutée localement, les scripts sont testés sur leurs
chemins de garde, mais le premier `deploy-preprod.sh` sera le vrai test — notamment les
permissions du `storage` monté depuis l'hôte du NAS.

## 2.7 Tests front — 0,5 j

```
[x] Installer Vitest + @vue/test-utils (le README l'annonçait déjà, cf. §1.8)
[x] Ajouter `npm run test` à la CI
[x] Premiers tests : store panier (calculs), utils/format, utils/errorHandler
[x] Activer la mesure de couverture backend (make test-coverage existe déjà, §12.8)
    → `coverage: pcov` dans la CI + étape de mesure et publication du rapport clover
      en artefact (14 j). pcov ajouté à la cible `development` du Dockerfile, pas à
      `base` : la production n'en a pas besoin.
    ⚠️ aucun **seuil** n'est posé : il n'y a pas de driver de couverture sur cette
      machine, donc pas de mesure fiable pour le fixer. À arrêter quand la CI aura
      publié un premier chiffre — poser un `--min` au hasard bloquerait la CI.
```

## 2.8 Recette de la phase 2

> **Écartée sur décision** (2026-08-23). Les 228 lignes de recette ne sont pas jouées.
> Le filet de sécurité de la phase 2 reste les **351 tests back + 49 front**, le typecheck
> et le build. Ce qui n'est pas couvert par eux ne l'est donc pas du tout : le rendu des
> vues, et tout ce qui exige la production ou un déploiement réel.

```
[ ] La suite de tests backend passe intégralement (226 méthodes)
[ ] Aucun whereRaw booléen ne subsiste (si D1 = A réussie)
[ ] Une galerie de 200 photos s'affiche avec des URL signées CACHÉES (2ᵉ visite instantanée)
[ ] Un export scolaire ne bloque plus les emails
[ ] Toutes les réponses API suivent la même enveloppe
[ ] Le rollback de production se fait par tag d'image
[ ] Aucune fonctionnalité n'a changé de comportement observable
```

---

# PHASE 3 — Réécriture des domaines fragiles

**15 jours.** Dans cet ordre : du plus risqué au moins risqué. **Chaque domaine est livré avec ses
tests** — c'est la raison d'être de la phase (les 6 bugs de l'audit étaient tous dans des domaines
sans test, → §9.6).

## 3.1 Facturation — 4 j · #9, #16, #33, #48

```
[x] #9  invoices.order_id → RESTRICT + SoftDeletes sur orders
        [x] Migration de changement de contrainte — réversible, aller-retour éprouvé
        [x] Confirmation explicite dans l'UI admin — **existait déjà** (Orders.vue:530).
            Mais une confirmation n'est pas une protection : le back acceptait toujours.
        [x] Vérifier qu'une commande payée ne peut plus être supprimée du tout — 409
        🔴 `destroy()` n'avait AUCUNE garde : il supprimait les lignes, le paiement, la
           commande, et la facture par cascade. Sur 120 commandes payées en production.
        ⚠️ SoftDeletes a cassé la purge RGPD, et le test l'a attrapé : la cascade ne
           partait plus, donc la facture survivait à la purge — avec ses données
           personnelles. `privacy:purge-expired` supprime maintenant la facture puis
           force-delete la commande.
[ ] #16 Avoirs (selon D5) : table `credit_notes`, séquence AV-YYYY-NNNNN sous advisory lock,
        statut orders.refunded écrit, gabarit PDF d'avoir
[x] #33 Adresse du client sur la facture
        → `Order::invoiceAddress()` : `shipping_*` si la commande en porte une, sinon
          l'adresse du compte. Sans ce repli, une commande **numérique** n'aurait aucune
          adresse : `shipping_*` n'est rempli que pour les tirages.
[x] #—  Libellés « Prix TTC » / « Total TTC » → « Montant » / « Total »
[x] #—  Ne plus avaler l'échec de génération de facture
        🔴 **Cause trouvée, plus grave que « avalé »** : `generateForOrder` retournait la
           facture existante SANS regénérer son PDF. Or le bouton « télécharger la
           facture » de l'admin l'appelle justement pour réparer — il ne réparait donc
           jamais rien, et une facture sans fichier l'était définitivement. C'est ce qui
           a laissé 5 factures sur 119 sans PDF en production.
        [x] une facture sans PDF est régénérée, **en conservant son numéro** — en
            réallouer un créerait un trou dans la séquence
        [x] `invoices:repair-pdf` (avec `--dry-run`) détecte et répare le stock existant
        🔴 **`Log::error` n'atteignait pas Sentry** : `LOG_STACK=daily` et aucun canal
           `sentry` déclaré. Les **70** `Log::error` du projet finissaient dans un fichier
           sur le NAS. Canal branché — tous deviennent visibles d'un coup.
        [~] l'email de confirmation part toujours, même sans facture : il porte le lien de
            téléchargement du client. Le bloquer punirait la cliente d'un défaut
            technique. Le PDF est une pièce jointe conditionnelle, et son absence est
            désormais visible et réparable.
[x] #—  Brancher GET /orders/{id}/invoice et /admin/orders/{id}/invoice
        → **aucun des deux n'avait de consommateur** : ni la cliente ni la photographe ne
          pouvaient récupérer une facture. Boutons ajoutés dans OrderConfirmation,
          account/OrderDetail et admin/Orders.
        → les deux endpoints divergeaient : l'admin réparait un PDF manquant, la version
          cliente renvoyait 404. Alignés.
[ ] #48 Export comptable CSV (ventes, avoirs, encaissements) — format à valider 👤
[x] #50 Registre des advisory locks → `docs/advisory-locks.md`
        → 42 et 43 documentés, plage 50-59 réservée aux séquences comptables.
        → consigne la raison de `xact` plutôt que `session` : la production passe par le
          pooler en **mode transaction**, où un verrou de session survivrait à la requête
          sur une connexion réattribuée à un tiers.
        → vérifié que les deux appels sont bien dans une transaction : hors transaction,
          `pg_advisory_xact_lock` se libère immédiatement et ne protège rien.
```

**Tests à écrire** *(aucun n'existe pour ce domaine)* :
```
[x] Génération idempotente · numérotation séquentielle sans trou · quantité × prix unitaire
[x] Une facture survit à la suppression de la photo (dénormalisation)
[x] Une commande payée ne peut pas être supprimée
[ ] Un avoir décrémente correctement et laisse la facture intacte — attend D5 👤
```

## 3.2 Authentification — 3 j · #13, #27, #28, D8

```
[~] Supprimer l'émission du token Bearer (login + register) et sa lecture côté front
    **D8 tranchée : on garde l'authentification hybride pour l'instant, en réduisant le
    risque plutôt qu'en le déplaçant.**
    Le danger du jeton n'est pas son existence mais sa durée de vie dans `localStorage`.
    Deux mitigations captent l'essentiel du gain sans toucher au parcours d'authentification :
    durée ramenée de 30 à 7 jours, et révocation à la réinitialisation du mot de passe.
    Le basculement en cookie de session seul dépend de `SESSION_DOMAIN`, des domaines
    stateful Sanctum et du CORS entre deux sous-domaines ; une erreur casse toute
    l'administration, et aucun test ne rend une vue.
    **Condition de levée** : des tests de rendu sur la connexion et une page
    d'administration. À reprendre avec l'enveloppe unique (2.4), même prérequis.
[~] Session cookie seule · SESSION_DOMAIN exact · SameSite=lax conservé — même décision
[~] Poser une Content-Security-Policy
    🔴 **la politique proposée par le plan casse le site.** `connect-src 'self'`
      bloquerait TOUS les appels d'API : le front est sur `oceanetorresphotographie.fr`,
      l'API sur `api.oceanetorresphotographie.fr`. Et le code encadre
      `www.youtube.com`, pas `youtube-nocookie`.
    🔴 **et l'emplacement était faux.** `SecurityHeaders` s'applique aux réponses de
      l'API : un `script-src` sur du JSON ne protège rien. Le HTML est servi par Render.
    [x] politique du **site** dans `render.yaml`, dérivée de ce que le front charge
        réellement — SumUp (script + iframe), GTM, Google Analytics, MinIO, miniatures
        YouTube, l'API, l'ingest Sentry.
    [x] **éprouvée** par `npm run check:csp` : un script Puppeteer sert `dist/` avec la
        politique réellement appliquée et parcourt 10 routes en collectant les violations.
        Branché en CI, donc une régression est détectée au build.
        🔴 il a immédiatement trouvé ce qu'un grep avait manqué : `src/style.css` faisait
        un `@import` vers **Google Fonts**. La politique aurait bloqué la feuille de style
        des polices sur toutes les pages. Police auto-hébergée via `@fontsource/inter` —
        meilleur sur les trois critères : plus de requête tierce, plus d'IP visiteur
        transmise à Google (point RGPD), et une dépendance externe de moins.
    [x] **promotion en deux moitiés**. Appliquées : `object-src`, `base-uri`,
        `form-action`, `frame-ancestors` — validées, et incapables de casser SumUp, GA ou
        Sentry puisqu'elles ne portent sur aucun chargement de script. Elles couvrent le
        détournement de formulaire, l'injection de `<base>` et l'inclusion en iframe.
        En observation : `script-src`, `connect-src`, `frame-src`, qui touchent des tiers
        qu'un build local ne sollicite pas. Les activer à l'aveugle risquerait le tunnel
        de paiement. Procédure de promotion écrite dans `render.yaml`.
    [x] politique de l'**API** : `default-src 'none'; frame-ancestors 'none'` — celle-là
        a du sens sur du JSON.
[x] Retirer X-XSS-Protection (obsolète) — aussi dans web/docker/nginx.conf
[~] TOTP sur le compte admin — **différé sur décision, et non par blocage**
    Le compte est déjà protégé par : mot de passe de 12 caractères vérifié contre HIBP,
    limitation à deux échelles (5/min et 20/h, par IP **et** par adresse), journal des
    connexions, jeton borné à 7 jours et révoqué à toute réinitialisation.
    Ce que le TOTP ajoute est réel mais marginal face à ça. Ce qu'il **risque** ne l'est
    pas : un enrôlement défectueux verrouille le seul compte qui administre une activité
    en production, et la réinitialisation de mot de passe ne rendrait pas l'accès — elle
    ne touche pas au second facteur. Or je ne peux pas éprouver un parcours d'enrôlement,
    aucun test ne rend une vue.
    **Prérequis à poser avant de l'écrire**, dans cet ordre :
    1. `php artisan admin:disable-2fa <email>` — une trappe de secours côté serveur,
       utilisable depuis le NAS sans passer par l'interface ;
    2. codes de récupération à usage unique, générés et affichés à l'enrôlement ;
    3. TOTP strictement optionnel, jamais imposé.
    Sans ces trois-là, le TOTP dégrade la stabilité plus qu'il n'améliore la sécurité.
[x] Finir « mot de passe oublié »
    🔴 **le parcours était inopérant bien avant l'interface** : la notification Laravel
      construisait son lien vers la route web `password.reset`, absente d'une API. Le
      rendu de l'email levait `RouteNotFoundException`. Mon propre test ne le voyait pas —
      `Notification::fake()` n'effectue aucun rendu, il vérifiait l'envoi, pas le contenu.
    [x] `ResetPassword::createUrlUsing()` pointe vers `/reinitialiser-mot-de-passe` du
        front, avec un test qui vérifie que le chemin reste aligné sur le routeur
    [x] `MotDePasseOublie.vue` + `ReinitialiserMotDePasse.vue`, routes en `noindex`
    [x] `authApi.forgotPassword()` / `resetPassword()`
[x] #— Révoquer les jetons à la réinitialisation
    🔴 une réinitialisation ne révoquait **rien** : sur un compte compromis, le jeton de
      l'attaquant restait valable jusqu'à son expiration. Réinitialiser le mot de passe ne
      rendait donc pas l'accès.
[x] #— Durée de vie du jeton Bearer : 30 jours → **7**, paramétrable par
    `SANCTUM_TOKEN_MINUTES`. Le jeton vit dans `localStorage` : sa durée est la fenêtre
    d'exploitation d'une XSS.
[x] Journal des connexions (réussies ET échouées) + verrouillage progressif
    → table `login_attempts` : email normalisé, IP, user-agent, succès, compte.
      Rien ne tracait les connexions : un bourrage d'identifiants ne laissait aucune
      trace exploitable.
    → verrouillage à deux échelles : 5/minute arrête une rafale, **20/heure** arrête un
      bourrage lent qui restait sous le seuil de la première.
    → le journal porte email + IP : purge à **90 jours**, horizon distinct de la
      conservation comptable de dix ans.
    ⚠️ le test a attrapé que ma purge était placée après le retour anticipé de
      `privacy:purge-expired` : sans commande expirée, elle ne tournait jamais.
[x] Uniformiser les messages de forgot-password (anti-énumération, #—)
    → 200 pour une adresse connue, 422 « aucun compte » sinon : une requête par adresse
      suffisait à savoir qui est inscrit. Réponse identique désormais.
    → `resetPassword` fuitait pareil : un jeton au hasard distinguait « aucun compte » de
      « jeton invalide ». Message unique.
[~] #— UpdateProfileRequest : exiger le mot de passe actuel pour changer l'email
    → **sans objet** : `PUT /auth/profile` a été supprimé en 2.1 comme mort. Il n'existe
      aucun moyen de changer une adresse. À reprendre si l'endpoint revient.
[~] #— UpdateUserRequest : interdire de rétrograder le dernier administrateur
    → **sans objet** : `apiResource /admin/users` a été supprimé en 2.1. Aucun endpoint
      ne modifie un rôle.
[~] #— Renseigner email_verified_at (colonne existante, jamais alimentée)
    → renseignée à la **réinitialisation de mot de passe** : un lien reçu par email prouve
      le contrôle de l'adresse. C'est le seul endroit du code où cette preuve existe.
      L'inscription ne vérifie rien, donc y laisser `null` est exact.
    → aucun code ne lit encore la colonne : une vraie vérification d'email est une
      fonctionnalité, pas un remplissage de colonne.
```

**Tests** :
```
[x] login/logout révoquent réellement — LogoutTest, 4 tests (phase 0)
[x] rate limit — RateLimitTest, 8 tests dont la limite horaire
[x] mot de passe faible refusé — PasswordPolicyTest, 8 tests.
    ⚠️ `Password::defaults()` porte min(12) + uncompromised() ; uncompromised() est
       désactivé en test, il interroge l'API HIBP. Le test couvre donc min(12) et
       `confirmed`, sur l'inscription ET la réinitialisation.
[x] énumération impossible — PasswordResetTest
[ ] TOTP — la fonctionnalité n'existe pas
[x] réinitialisation de bout en bout — jeton extrait de la notification réellement
    envoyée, pas de `Password::createToken`. Couvre aussi le rejeu du jeton et le fait
    que l'ancien mot de passe cesse de fonctionner.
```

⚠️ La suite dépassait les 128 Mo du CLI depuis les tests de facturation, qui génèrent de
vrais PDF : `memory_limit` passe à 512 Mo dans `phpunit.xml` (la production est à 2048).
405 tests en 21 s, dont la moitié dans les tests de limite de débit — inhérent.

## 3.3 Panier et tarification — 3 j · #45

```
[x] Factoriser le calcul des frais de port dans PricingService
    → vérifié mot pour mot : OrderService et CartService portaient la MÊME logique à
      l'identique, CreateCheckoutRequest en dupliquait la moitié (le booléen seul).
    → `PricingService::shippingFeeFor()` / `requiresShipping()`. La règle a été figée par
      7 tests AVANT la factorisation : maximum et non somme sur panier mixte, le numérique
      ne pèse pas dans le maximum, repli sur la configuration, exemption `print_scolaire`.
[x] #— Écrêter le CUMUL de quantité, pas seulement l'incrément
    🔴 `increment('quantity', 50)` appelé dix fois donnait **500 exemplaires**. Sur un
      tirage à 8 €, une commande de 4000 € et 500 tirages à produire.
    🔴 la fusion invité → compte additionnait sans plafond non plus : 50 + 50 = 100.
    → les deux chemins passent par un `cappedQuantity()` unique.
[x] #— Contrainte unique partielle : un seul panier actif par utilisateur
    → l'invariant n'était garanti qu'au niveau applicatif : deux requêtes concurrentes
      pouvaient créer deux paniers, et l'utilisateur payer un montant différent de
      l'affiché. Index unique partiel, précédé d'un collapse. Zéro doublon en preprod.
[x] #— Notifier le client quand validateCartItems() réécrit un prix
    🔴 elle faisait **trois** choses en silence : retirer les photos disparues, retirer
      celles devenues indisponibles, et réécrire les prix. Une cliente pouvait être
      débitée d'un montant qu'elle n'avait jamais vu.
    → décision : une photo retirée ou un prix **en hausse** arrête le paiement en 409 et
      renvoie au panier. Une **baisse** passe — elle paie moins que l'affiché.
    → point qui a décidé du choix : `getCartSummary()` appelle `recalculatePackPrices()`,
      donc l'affichage est toujours resynchronisé. Un écart au paiement signifie que le
      tarif a changé APRÈS la dernière vue du panier — refuser est donc correct, et un
      test prouve que le parcours normal (consulter puis payer) ne subit aucune friction.
    ⚠️ **défaut de ma première version, trouvé par la cartographie déléguée** : le message
      annonçait « votre panier a été mis à jour » alors que rien ne l'était.
      `validateCartItems` ne supprime aucune ligne, et le `throw` étant dans la
      transaction, même la resynchronisation de prix était annulée. Chaque nouvelle
      tentative rejouait donc la même erreur : cul-de-sac de paiement permanent, sans que
      la cliente puisse identifier l'article fautif.
      → la réconciliation passe **avant** la transaction et retire réellement les articles
      indisponibles. Deux tests ajoutés : le panier est bien modifié, et **la seconde
      tentative aboutit**.
[~] #— Codes promo : montant minimum de commande, restriction par galerie/produit ? 👤
    [x] **montant minimum** — colonne `gift_codes.min_order_amount`, champ dans
        l'administration, message qui annonce le montant requis à la cliente.
        Le minimum porte sur le **sous-total** des articles, comme la remise : les frais
        de port n'y comptent pas. Un test fige cette base de calcul.
    [ ] restriction par galerie ou par produit — non demandée, et elle changerait la forme
        du modèle (table de liaison). À trancher si le besoin apparaît.
```

**Tests** *(469 lignes de service aujourd'hui non testées)* :
```
[ ] Cumul cross-galerie des packs (offer signature) — la logique la plus subtile du projet
[ ] Invariant « un panier actif » · fusion invité→compte avec addition des quantités
[ ] Frais de port : règle du maximum sur panier mixte · exemption print_scolaire
[ ] Réécriture de prix au checkout · session scolaire clôturée refusée
```

## 3.4 Sessions scolaires — 3 j · #29, #35

```
[x] D7 — table school_session_contacts + purge à closed_at + 3 mois
        [x] contacts persistés · historique d'envoi (`channel`, `status`, `sent_at`,
            `send_attempts`, `error_message`). Clé unique (galerie, coordonnée) : une
            seconde diffusion met à jour au lieu de dupliquer.
        [x] réenvoi individuel (`contact_ids`) · relance des non-envoyés par défaut
            → `GET /admin/school-sessions/{id}/contacts` et `POST .../resend-messages`
        [x] purge à **clôture + 3 mois**, portée par `privacy:purge-expired`
            (`--school-contact-months`). Une session non clôturée conserve ses contacts.
        [x] intégré à `PersonalDataLocator` **et** `PersonalDataEraser` — sans le premier
            une recherche ne voit pas ces contacts, sans le second un effacement les
            laisse en place.
        [x] `docs/rgpd-registre.md` — inventaire technique des 13 traitements, relevé dans
            le schéma. Il dit ce qui est stocké, où, combien de temps et par quel chemin
            l'effacer ; les bases légales restent à confirmer par la photographe.
        ⚠️ mon premier `resendMessages` réutilisait `SendSchoolSessionMessagesRequest::createFrom()`,
          qui ne déclenche pas la validation : erreur 500. La logique d'envoi est extraite
          dans une méthode privée que les deux endpoints appellent.
        [x] interface d'administration : section « Diffusion » dans `SchoolSessionDetail`
            — liste des contacts avec statut et nombre de tentatives, relance groupée des
            non-envoyés avec choix du canal, et renvoi individuel.
            → le renvoi individuel utilise le **canal enregistré** du contact : il peut
              porter les deux coordonnées, et le renvoyer par le mauvais canal serait pire
              que de ne rien faire.
            → l'historique est chargé au montage et rafraîchi après chaque diffusion. Son
              échec ne casse pas la page : la session reste consultable sans lui.
        📋 l'import CSV n'est pas fait : le front envoie déjà une liste de contacts, et
          rien ne montre qu'un CSV soit le format attendu. À trancher avec la photographe.
[x] #35 Rendre le filtre « commande scolaire » SYMÉTRIQUE
        🔴 `index` excluait dès **un** article scolaire, tandis que la vue de session
          n'affiche que les commandes **payées**. Une commande mixte non payée
          n'apparaissait donc **nulle part** dans l'administration.
        → définition unique portée par le modèle : `Order::isEntirelySchool()` et son
          pendant SQL `scopeNotEntirelySchool()`. `isSchoolOrder` délègue au modèle.
        ⚠️ en unifiant j'ai conflaté deux notions : le **type de produit** vendu et la
          **galerie de session**. La seconde est le fait structurel — une galerie scolaire
          peut vendre du numérique. Un test construisait le cas impossible (du
          `print_scolaire` dans une galerie non scolaire) ; corrigé pour refléter la réalité.
[x] #— Durcir l'extraction ZIP (#47)
        🔴 `extractTo()` sans **aucun** contrôle : ni chemin, ni nombre d'entrées, ni
          taille décompressée, ni ratio. Un ZIP de quelques Mo pouvait remplir le disque
          du NAS, et un nom d'entrée viser hors du dossier d'extraction.
        → extraction entrée par entrée : refus des chemins absolus et des `..`, 5000
          entrées maximum, 20 Go décompressés, ratio plafonné à 200, et seules les
          extensions que la chaîne sait traiter.
        → 6 tests, dont l'évasion par `..`, le chemin absolu et la bombe de décompression.
        ⚠️ l'upload étant réservé à l'administration, ce n'était pas une attaque anonyme —
          mais une archive mal formée par accident produit le même effet.
[x] #— Borner l'offset de l'upload chunké
        → `offset` avait `min:0` sans maximum, et `total_chunks` n'était pas borné non
          plus. `fseek()` à une position arbitraire crée un fichier creux de taille
          quelconque. Plafonds : 10 Go d'archive, 10 000 fragments, et une vérification
          que `offset + taille du fragment` reste sous la limite.
[x] #— Les 3 jobs longs doivent RELANCER l'exception après avoir marqué le statut
        🔴 ils marquaient `failed` puis avalaient l'exception : la file croyait avoir
          réussi, `failed_jobs` restait vide, et la supervision ne voyait rien. Un export
          ou un traitement pouvait échouer sans que personne ne l'apprenne.
        → point qui rend la relance sans danger : les trois ont `$tries = 1`. Le job part
          donc directement en `failed_jobs` **sans réexécuter** un traitement qui n'est pas
          idempotent. 9 tests figent les trois propriétés : relance, une seule tentative,
          et présence du hook `failed()`.
[x] #— SendSchoolSessionSmsJob → SendSmsJob, et distinction des échecs
        → le nom était faux : `GalleryController::sendAccessSms` l'emploie pour un lien de
          galerie, hors contexte scolaire.
        → `BrevoSmsService::send()` rendait un booléen, donc le job réessayait **tout**.
          Un SMS se paie : trois tentatives pour un numéro invalide sont du gaspillage.
          Deux types d'échec désormais — permanent (numéro invalide, expéditeur refusé,
          clé absente, 4xx) et transitoire (429, 5xx, réseau). Le job échoue net sur le
          premier, réessaie sur le second.
        → l'ancien nom survit en sous-classe dépréciée : un job déjà en file au moment du
          déploiement doit rester désérialisable.
[ ] #— Suivre le nombre de photos omises dans un export (fait en 0.14)
```

**Tests** *(507 l. de contrôleur + 420 l. de service, zéro test)* :
```
[ ] Parsing de l'arborescence classe/enfant · dossier racine unique · photos partagées
[ ] Clôture/réouverture · export ZIP avec _index.csv · reprise après échec
[ ] Diffusion : contacts persistés, réenvoi, non-envoyés
```

## 3.5 Réservations et clients — 1 j · #31, #—

```
[ ] Réparer ou supprimer les 3 routes qui lèvent une 500 (#37, décidé en phase 2)
[ ] #31 clients.source : recalculer (reservation si réservation, contact si seul message,
        sinon manual) ou abandonner la colonne — DÉCIDÉ en phase 1
[ ] #— UserObserver : ne plus forcer source='reservation' à l'inscription
[ ] #— BookingRequestController : ne plus écraser gdpr_consent_at d'un client existant
        (perte de la date de consentement d'origine)
[ ] #— prestation_id en CASCADE → RESTRICT ou SET NULL (supprimer une prestation efface
        aujourd'hui tout l'historique des réservations, §3bis.4)
[ ] #— Accesseurs client_name/client_email/client_phone : ne plus dépendre de
        relationLoaded() (résultat différent selon l'appelant, §2.8)
[ ] #— Absorber client_forms dans reservations.guest_* puis abandonner la table
```

## 3.6 Front — véracité de l'affichage — 1 j · #63, #64, #65, #66, #67, #68

```
[ ] #63 Dashboard : appeler getDashboardStats() et getRecentActivity() au lieu de charger
        6 listes de 100 lignes. Supprime ~600 signatures MinIO et corrige 7 compteurs faux
[ ] #64 GiftCards + PromoCodes : les statistiques doivent venir du serveur, pas de la page
        courante (l'« encours de bons cadeaux » est un engagement financier affiché faux)
[ ] #65 Tri : brancher sort_by/sort_dir (fait en 2.4) ou retirer sortable: true
[ ] #66 Associer les libellés (for/id) sur les 5 formulaires publics — AuthModal (9 labels,
        0 for), Checkout (4/0), BookingRequestModal (7/0), GalleryAccess (0 label)
        + role/tabindex sur les 5 <div @click>
[ ] #67 Ajouter les autocomplete : email, current-password, new-password, name, tel,
        street-address, postal-code, address-level2 → friction directe dans le tunnel
[ ] #68 onUnmounted + clearTimeout dans Orders.vue et PromoCodes.vue
[ ] #41 Paginer les photos de galerie (aujourd'hui le JSON porte les 200 entrées)
[ ] #— Vérifier response.ok sur les 3 fetch(download_url) avant .blob()
        (sinon le fichier téléchargé contient le XML d'erreur S3)
[ ] #— Réduire le boilerplate des 13 vues admin (7 072 l.) : composant « ressource
        administrable » (état, modale de formulaire, modale de confirmation, toast)
```

## 3.7 Divers — 0,5 j

```
[ ] #18 deletePhotoVersions + deleteGalleryFolder doivent inclure thumbnail-clean
        (des dérivés SANS FILIGRANE survivent aujourd'hui à la suppression)
[ ] #23 Séparer les buckets MinIO : photos / invoices, rétentions distinctes
[ ] #25 Webhooks de délivrabilité Brevo (email et SMS) + table de suivi des envois
[ ] #30 Sortir le traitement photo de la requête HTTP (storeAsync est synchrone malgré son
        nom) — ou l'assumer et le documenter, avec les 3 lots parallèles comme limite
[ ] #38 Typer les 8 colonnes JSON ou les documenter dans docs/json-schemas.md
[ ] #44 Superviser l'espace disque du NAS (aggravé par le cache d'images et les exports)
[ ] #49 Uniformiser les précisions décimales (10,2 partout)
[ ] #51 ClearsEventGalleriesCache : purger par tag/préfixe, pas par boucle bornée à 10
[ ] #52 Centraliser adresse de contact et SIRET (3 sources de vérité aujourd'hui)
[x] #— Générer un registre des traitements RGPD (art. 30) + procédure de notification (art. 33)
    → `docs/rgpd-registre.md`, écrit à l'occasion de D7. Signale une limite connue :
      aucun journal des **lectures**, donc en cas de compromission d'un compte
      d'administration on saurait dire qu'il y a eu accès mais pas à quoi.
[ ] #— youtube-nocookie.com + clic-pour-charger (cookies YouTube avant consentement)
[ ] #— PHPStan/Larastan niveau 5+ dans la CI (aurait trouvé 3 des bugs de l'audit)
```

---

# PHASE 4 — Migration des données

**13 à 16 jours.** Le plan détaillé est en **§10 de l'audit**. Ce qui suit est la checklist
d'exécution ; ne pas s'en servir sans avoir lu §10.1 à §10.13.

## 4.1 Les cinq principes

```
[ ] P1 — La source de vérité est un DUMP, jamais le dossier migrations/ (§3bis.14)
[ ] P2 — Migrer les données, pas le schéma (un pg_restore reconduirait les 5 dettes)
[ ] P3 — L'ancienne base reste intacte et en lecture seule, indéfiniment
[ ] P4 — Chaque ligne conserve son UUID d'origine  ← DÉCISION LA PLUS IMPORTANTE
[ ] P5 — Le script est idempotent et produit un rapport de comptage
```

**Sur P4** : conserver les UUID rend les chemins MinIO valides (**aucun fichier à déplacer**), garde
les `share_code` et `access_token` communiqués aux clientes, préserve les liens envoyés par email et
SMS, et réduit la réconciliation à des `COUNT`. **Y renoncer imposerait de réécrire des dizaines de Go
d'arborescence MinIO.**

## 4.2 Classification des 38 tables — §10.4

```
[ ] 13 tables du cœur métier                → MIGRER (users, clients, galleries, photos,
      event_categories, gallery_product_types, pack_tiers, prestations, reservations,
      orders, order_items, invoices, payments)
[ ]  5 tables de support                    → MIGRER (gift_codes, gift_cards, school_sessions,
      privacy_audit_logs, contact_messages < 12 mois)
[ ]  8 tables régénérables                  → NE PAS MIGRER (cache, cache_locks, sessions,
      jobs, job_batches, failed_jobs, password_reset_tokens, personal_access_tokens)
[ ]  3 tables transitoires                  → NE PAS MIGRER (photo_uploads,
      school_session_exports, privacy_exports → migrer l'historique seul)
[ ]  4 tables à abandonner                  → subscriptions, subscription_items,
      availability_slots, availability_patterns
[ ]  1 table à absorber                     → client_forms → reservations.guest_*
```

## 4.3 Les sept contraintes non négociables — §10.5

```
[ ] C1 Rétention comptable ~10 ans : orders / order_items / invoices / payments migrés
       INTÉGRALEMENT, aucun filtre de date, aucune anonymisation
[ ] C2 invoice_number conservé à l'identique ; la nouvelle séquence REPREND au maximum existant
[ ] C3 Idem pour order_number (OT-YYYY-NNNNN)
[ ] C4 privacy_audit_logs migré intégralement (preuve de conformité)
[ ] C5 Consentements horodatés conservés tels quels — y compris les consent_ip erronés :
       les migrer SANS les corriger (inventer une preuve est pire que l'absence de preuve)
[ ] C6 Fuseau : vérifier timestamp vs timestamptz au relevé, ne PAS décaler
[ ] C7 Hashes bcrypt migrés tels quels — JAMAIS régénérés
```

## 4.4 Les douze règles de nettoyage — §10.7

```
[ ] R1  clients.source : recalculer ou abandonner (jamais migrer la valeur telle quelle)
[ ] R2  guest_first_name / guest_last_name : migrer tels quels, ne pas « re-corriger »
        (l'original scindé par explode() est perdu ; toute heuristique ajouterait des erreurs)
[ ] R3  client_forms absorbé dans reservations.guest_*
[ ] R4  Booléens à TROIS états : préserver le NULL (gallery_product_types.requires_shipping,
        galleries.shipping_fee, photos.price) — normaliser en false CHANGERAIT la facturation
[ ] R5  Booléens stockés en texte : convertir en boolean natif (seule transformation de type
        indispensable)
[ ] R6  prestations : price nullable + price_display (simplifier la triple représentation)
[ ] R7  gift_codes migrés AVEC orders.gift_code_id (sinon tous les quotas se réinitialisent)
[ ] R8  orders.metadata : extraire download_token en colonne dédiée ; ne pas migrer les expirés
[ ] R9  Ne pas créer les colonnes mortes (stripe_*, file_path_web, file_path_watermark, pdf_path)
[ ] R10 Ne pas reprendre les valeurs d'enum mortes — SAUF orders.status='refunded' (à CONSERVER)
[ ] R11 photos.metadata : ne supprimer storage_path/supabase_path qu'APRÈS validation des fichiers
[ ] R12 school_sessions : ne pas migrer zip_path/batch_id/error_message ;
        convertir product_types_config (JSON) en lignes relationnelles
```

## 4.5 🔴 Les fichiers MinIO — §10.8

**Le risque n°1 de l'opération.** Grâce à P4, aucun fichier ne bouge — mais les **références** doivent
être normalisées.

```
[ ] Étape 1 — Quantifier les 4 populations (§3bis.14-C) : A file_path_hd · B storage_path ·
    C supabase_path · D file_path seul (potentiellement une URL http absolue)
[ ] Étape 2 — Inventorier les objets présents : mc ls --recursive > minio-inventory.txt
[ ] Étape 3 — Réconcilier base ↔ objets via une table temporaire (requêtes §10.8)
    [ ] Photos dont le chemin résolu N'EXISTE PAS → liste à traiter à la main
    [ ] Objets non référencés → orphelins
[ ] Étape 4 — Normaliser : écrire le chemin résolu dans file_path_hd
[ ] Étape 5 — Arbitrer les irrécupérables 👤 : rejouer process-existing / marquer indisponible /
    documenter
[ ] ⚠️ NE JAMAIS supprimer une ligne photos référencée par un order_items
[ ] Factures : vérifier la présence de CHAQUE PDF (obligation légale) ; écrire une commande
    de régénération en masse si nécessaire
[ ] Séparer les buckets photos / invoices à cette occasion (#23)
```

## 4.6 Ordre d'exécution — §10.9

```
[ ]  1. users                        [ ] 12. gift_codes
[ ]  2. clients                      [ ] 13. reservations
[ ]  3. prestations                  [ ] 14. carts + cart_items (optionnel)
[ ]  4. event_categories             [ ] 15. orders
[ ]  5. school_sessions              [ ] 16. order_items
[ ]  6. galleries (sans parent_id ni thumbnail_photo_id)
[ ]  7. galleries — 2ᵈ passe : parent_id        [ ] 17. invoices
[ ]  8. photos                                  [ ] 18. payments
[ ]  9. galleries — 3ᵉ passe : thumbnail_photo_id [ ] 19. gift_cards
[ ] 10. gallery_product_types                   [ ] 20. contact_messages
[ ] 11. pack_tiers                              [ ] 21. privacy_audit_logs
                                                [ ] 22. privacy_exports
```

⚠️ **`galleries` a deux références circulaires** (`parent_id` auto-référent, et
`thumbnail_photo_id → photos` alors que `photos.gallery_id → galleries`) → **trois passes obligatoires.**

## 4.7 Le script — §10.10

```
[ ] Connexion Laravel `legacy` vers la COPIE, avec 'options' => [] (PAS d'EMULATE_PREPARES)
[ ] Commande artisan legacy:migrate --step=* --dry-run --chunk=500
[ ] Idempotence : upsert sur l'UUID d'origine
[ ] Traitement par lots : chunkById(500) — les tables volumineuses ne tiennent pas en mémoire
[ ] Normalisation explicite des booléens (R5)
[ ] Rapport de comptage source / cible / insérés par étape
[ ] ⚠️ LIRE EN QUERY BUILDER BRUT — jamais avec les modèles Eloquent existants :
    ils embarquent le trait booléen, les $appends (qui déclencheraient des SIGNATURES MINIO
    à chaque photo lue) et les hooks boot() (qui RÉGÉNÉRERAIENT les share_code)
```

## 4.8 Validation en 5 niveaux — §10.11

```
[ ] Niveau 1 — Comptages : égalité STRICTE sur les 13 tables du cœur, écarts documentés ailleurs
[ ] Niveau 2 — Cohérence référentielle : 0 orphelin (5 requêtes)
[ ] Niveau 3 — Invariants métier :
    [ ] SUM(total) des commandes payées identique de part et d'autre → écart 0,00 €
    [ ] Continuité de la séquence de facturation : même min, max, compte, aucun trou nouveau
    [ ] Codes promo : même nombre d'utilisations payées
    [ ] 0 galerie sans share_code ni access_token
    [ ] 0 mot de passe ne commençant pas par $2y$
[ ] Niveau 4 — Fichiers : 0 photo au chemin absent · 0 facture sans PDF
[ ] Niveau 5 — Manuel :
    [ ] Une galerie s'ouvre avec son ANCIEN code de partage
    [ ] Vignettes et previews s'affichent (galerie de 50+ photos)
    [ ] Un ZIP de galerie téléchargeable se génère
    [ ] Une facture existante se télécharge
    [ ] ⚠️ Un compte client existant se connecte avec SON MOT DE PASSE ACTUEL
    [ ] Le tableau de bord client affiche l'historique complet
    [ ] Une commande de test aboutit de bout en bout (sandbox SumUp)
```

## 4.9 Bascule — §10.12

**Bascule unique, fenêtre courte.** Pas de double écriture (le trafic est faible, une synchronisation
bidirectionnelle sur 18 tables coûterait plus en risque qu'une heure d'interruption).

```
[ ]  1. Répétitions générales sur la copie — 3 itérations minimum, validations 1-5
[ ]  2. Fenêtre creuse choisie (hors campagne scolaire, hors envoi de galeries)
[ ]  3. Photographe prévenue : ne rien faire dans l'admin pendant la fenêtre
[ ]  4. Vider la file (jobs à 0) et LIRE failed_jobs                        — 15 min
[ ]  5. Page de maintenance front + mode maintenance API                    —  5 min
[ ]  6. pg_dump final (post-arrêt d'écriture)                               — 10 min
[ ]  7. Exécuter la migration                                               — 30-60 min
[ ]  8. Validations 1-4 (automatisées)                                      — 10 min
[ ]  9. Validation 5 (manuelle)                                             — 20 min
[ ] 10. Basculer DNS / configuration front
[ ] 11. Surveillance rapprochée 48 h : logs, Sentry, /health, premiers paiements
```

**Retour arrière** : jusqu'à l'étape 9 incluse, c'est un changement de configuration — l'ancienne base
n'a pas été touchée (P3). **Point de non-retour** : la première écriture dans la nouvelle base.

## 4.10 Les treize interdits — §10.13

```
[ ] ❌ pg_dump + pg_restore vers la nouvelle base        [ ] ❌ Corriger les consent_ip
[ ] ❌ Régénérer les UUID                                [ ] ❌ « Réparer » les guest_first_name
[ ] ❌ Régénérer les numéros de facture / commande       [ ] ❌ Filtrer orders/invoices/payments
[ ] ❌ Re-hasher les mots de passe                       [ ] ❌ Lire avec les modèles Eloquent
[ ] ❌ Normaliser les NULL des booléens à 3 états        [ ] ❌ Migrer depuis la production
[ ] ❌ Créer un Client par guest_email                   [ ] ❌ Supprimer supabase_path trop tôt
[ ] ❌ Commencer avant la phase 0
```

---

# PHASE 5 — Valeur ajoutée

**8 jours. Après la bascule, une fois le socle sûr.**

```
[ ] D6 — Portfolio administrable (4-5 j) : table, API, écran admin, upload, réordonnancement,
         contenu éditorial des catégories en base (aujourd'hui ~250 mots/catégorie dans
         constants.ts), IDs YouTube administrables
[ ] D4 — Bons cadeaux outillés (2-3 j) : génération du PDF de la carte + envoi email d'un clic
[ ] #25 — Webhooks de délivrabilité Brevo + écran de suivi des envois
[ ] #48 — Export comptable (si non fait en 3.1)
[ ] #— Relance de panier abandonné (7 j d'expiration aujourd'hui silencieuse)
[ ] #— Notifications in-app étendues : commande payée, tirage à expédier, session traitée
        (aujourd'hui seules les demandes de réservation en produisent, §2.14)
[ ] #— Option `tunnel` du SDK Sentry front, si une régression JS passe inaperçue
        (20-40 % des visiteurs bloquent *.ingest.sentry.io, §8.12)
```

---

# 8. Conventions à établir

À écrire **avant** la phase 2, dans `docs/`. Sans elles, la dette se reforme.

| Document | Contenu |
|---|---|
`docs/api-conventions.md` | Enveloppe de réponse unique · pagination · tri · codes d'erreur · une API Resource par ressource
`docs/authorization.md` | Matrice « qui a le droit de quoi » · Policies toujours via le Gate · jamais de vérification inline
`docs/json-schemas.md` | Les 8 colonnes JSON, leur schéma et leur usage
`docs/naming.md` | `guest_*` vs `customer_*` · statuts · préfixes MinIO · advisory locks (registre)
`docs/testing.md` | Ce qui doit être testé avant merge · Postgres obligatoire (SQL non portable)
`docs/runbook.md` | Incidents, restauration, rotation des secrets, bascule
`docs/rgpd-registre.md` | Registre des traitements (art. 30) · procédure de violation (art. 33)

**Règles de code à faire respecter par l'outillage** :
```
[ ] declare(strict_types=1) partout (absent de tout le backend)
[ ] PHPStan/Larastan niveau 5+ bloquant en CI
[ ] Aucun fichier > 400 lignes sans justification (9 backend, 11 front dépassent 500)
[ ] Aucun v-html · aucun fetch() hors de la couche services
[ ] Un commentaire par garde-fou : expliquer le POURQUOI, pas le QUOI
    (c'est la meilleure pratique du projet actuel — la préserver)
```

---

# 9. Les 25 invariants à préserver

**Chaque ligne est née d'un incident de production.** Si un seul tableau de ce plan devait survivre,
c'est celui-ci. → §11.7 de l'audit

| # | Invariant | Sans lui |
|---|---|---|
| 1 | Ne **jamais** croire le statut de paiement annoncé par le client ; rappeler `getCheckout()` | Injection d'un faux `PAID` |
| 2 | `return_url` SumUp pointe le **backend**, jamais le SPA | Le SPA avale le webhook POST, commandes bloquées |
| 3 | Webhook : **503** sur erreur transitoire, **200** sur erreur définitive | Retries perdus ou spam infini |
| 4 | Drapeau `$justCompleted` avant les effets de bord de `completeOrder` | Double email (webhook + polling) |
| 5 | `where('status','pending')` sur l'`update` d'expiration | Un paiement valide écrasé en `expired` |
| 6 | Expirer les anciens checkouts avant d'en créer un nouveau | Un widget figé sur un ancien prix reste payable |
| 7 | `completeFreeOrder` refuse tout total > 0 | Court-circuit d'un vrai paiement |
| 8 | Revalider le code promo sous `lockForUpdate` **dans** la transaction | Dépassement de quota par course |
| 9 | Le sous-total multiplie par la quantité | **Sous-facturation SumUp** |
| 10 | Un seul décodage d'image pour dériver preview + vignette | Pic mémoire ×15 (260 Mo au lieu de 17) |
| 11 | Réduire **avant** de tourner selon l'EXIF | GD alloue 3× le full-size → OOM sur les portraits |
| 12 | Cloner la vignette **avant** le filigrane | Filigrane illisible à 600 px |
| 13 | Upload de l'original en flux (`putFileAs`) | 20-35 Mo en mémoire PHP |
| 14 | `withEvents(discover: false)` + listeners câblés à la main | **Tous les emails partent en double** |
| 15 | `ProcessPhotoJob` : sur échec, ne rien nettoyer ; laisser `failed()` le faire | Reprise impossible |
| 16 | Heartbeat du scheduler par `Schedule::command` (process forké) | Heartbeat vert alors que les tâches forkées échouent |
| 17 | Heartbeat du worker sur `Looping`, pas `JobProcessed` | Fausse panne dès que la file est calme |
| 18 | `withoutOverlapping` avec expiration **courte et explicite** | 24 h de blocage silencieux ; `Cache::has(mutexName())` ne détecte rien |
| 19 | Chronométrer l'ouverture de connexion **séparément** du `SELECT` | Fausse alerte « base lente » |
| 20 | Ne pas mettre le résultat des sondes en cache | Un état de santé caché ment au pire moment |
| 21 | `unicodeEnabled: false` + suppression des accents (SMS) | 3 SMS facturés au lieu d'1 |
| 22 | Dual endpoint MinIO (interne pour l'API, public pour les URL signées) | 100-300 ms par PUT |
| 23 | Dénormaliser `photo_title` / `gallery_title` sur `order_items` | Facture illisible après suppression de la photo |
| 24 | Un seul panier actif par utilisateur | Le client paie un montant ≠ de l'affiché |
| 25 | Chemin déterministe des vignettes clean (même `basename`) | Doublons à chaque backfill |

**Trois invariants ajoutés par ce plan** :

| # | Invariant | Sans lui |
|---|---|---|
| 26 | Toujours résoudre un chemin de fichier par `resolved_storage_path`, jamais par un raccourci | Une photo achetée absente d'un export (§12.4) |
| 27 | Toute liste de « chemins référencés » doit inclure les 7 colonnes **et** les 2 clés JSON | `clean-orphans` supprime des originaux (§12.2) |
| 28 | Un effacement RGPD passe **uniquement** par `PersonalDataEraser` | Deux politiques contradictoires (§12.3) |

---

# 10. Matrice de traçabilité — les 68 items

**Aucun item ne doit rester sans phase.** C'est le contrôle « rien oublié ».

| # | Item | Cot. | Phase |
|---|---|---|---|
| 1 | `downloadFile()` — lecture + suppression arbitraires | 🔴 | **0.5** |
| 2 | Compte admin du seeder (`admin123`) | 🔴 | **0.1 / 0.4** |
| 3 | La déconnexion ne révoque rien | 🔴 | **0.10** |
| 4 | `$request->ip()` constant | 🔴 | **0.8** |
| 5 | Aucun rate limit sur 5 routes publiques | 🔴 | **0.9** |
| 6 | Quantité absente de la facture et des 3 emails | 🔴 | **0.11** |
| 7 | Aucune sauvegarde | 🔴 | **0.2** |
| 8 | `migrate --force` au démarrage de conteneur | 🔴 | **2.6** |
| 9 | `DELETE /admin/orders` détruit la facture | 🔴 | **3.1** |
| 10 | Une seule file d'attente | 🔴 | **0.17** |
| 11 | Les 3 jobs longs avalent leur exception | 🔴 | **3.4** |
| 12 | Les 3 filets de supervision non branchés | 🔴 | **0.15** |
| 13 | Token Bearer 30 j en `localStorage` + pas de CSP | 🟠 | **3.2** |
| 14 | Couche booléenne PostgreSQL | 🟠 | **1.3-D1 / 2.2** |
| 15 | Aucun `SoftDeletes` + 18 CASCADE | 🟠 | **3.1** |
| 16 | Aucun remboursement / avoir | 🟠 | **1.3-D5 / 3.1** |
| 17 | Binaires d'images en cache applicatif | 🟠 | **2.3** |
| 18 | Vignettes clean non supprimées | 🟠 | **3.7** |
| 19 | Driver d'image non déterministe | 🟠 | **0.18** |
| 20 | Aucune convention de format de réponse | 🟠 | **2.4** |
| 21 | Contrôle d'accès sans couche ; règle « 30 min » ×4 | 🟠 | **2.5** |
| 22 | Prod sur la cible `development`, pas d'image versionnée | 🟠 | **2.6** |
| 23 | Photos et factures dans le même bucket | 🟠 | **3.7 / 4.5** |
| 24 | Cache fichier partagé par bind mount | 🟠 | **2.3** |
| 25 | Zéro suivi de délivrabilité email / SMS | 🟠 | **3.7 / 5** |
| 26 | Aucune analyse de dépendances | 🟠 | **0.16** |
| 27 | Politique de mot de passe à 8 caractères | 🟠 | **0.13** |
| 28 | Aucun 2FA sur le compte admin | 🟠 | **3.2** |
| 29 | Contacts scolaires non persistés | 🟠 | **1.3-D7 / 3.4** |
| 30 | Traitement photo en ligne | 🟠 | **3.7** |
| 31 | `clients.source` non fiable | 🟡 | **3.5 / 4.4-R1** |
| 32 | `cgv_version` en dur | 🟡 | **0.18** |
| 33 | Adresse client absente de la facture | 🟡 | **3.1** |
| 34 | Régénérer le code ne révoque pas les previews | 🟡 | **2.5** |
| 35 | Commandes mixtes invisibles en admin | 🟡 | **3.4** |
| 36 | `EventCategoryController::reorder` cassé | 🟡 | **0.18** |
| 37 | `authorize()` sans `AuthorizesRequests` → 500 | 🟡 | **0.18 / 2.5** |
| 38 | 8 colonnes JSON sans schéma | 🟡 | **3.7 / 8** |
| 39 | Contrôleurs mixtes public/admin | 🟡 | **1.3-D2 / 2.4** |
| 40 | Portfolio non administrable | 🟡 | **1.3-D6 / 5** |
| 41 | Aucune pagination des photos de galerie | 🟡 | **3.6** |
| 42 | Plafond ZIP de 500 silencieux | 🟡 | **0.18** |
| 43 | `storage:clean-orphans` non planifiée | 🟡 | **0.6** |
| 44 | Saturation disque du NAS non supervisée | 🟡 | **3.7** |
| 45 | Frais de port dupliqués ×3 | 🟡 | **3.3** |
| 46 | `SESSION_DOMAIN` sur tous les sous-domaines | 🟡 | **0.18** |
| 47 | Extraction ZIP non durcie | 🟡 | **3.4** |
| 48 | Aucun export comptable | 🟡 | **3.1 / 5** |
| 49 | Précisions décimales incohérentes | 🟢 | **3.7 / 4** |
| 50 | Advisory locks sans registre | 🟢 | **3.1 / 8** |
| 51 | `ClearsEventGalleriesCache` borné à 10 pages | 🟢 | **3.7** |
| 52 | 3 sources pour l'adresse de contact / le SIRET | 🟢 | **3.7** |
| 53 | `APP_VERSION` désaligné | 🟢 | **0.18** |
| 54 | En-têtes de sécurité contradictoires | 🟢 | **0.18** |
| 55 | Directives `[Session]` inertes | 🟢 | **2.6** |
| 56 | Render non versionné | 🟡 | **2.6** |
| 57 | `make fresh` / `make seed` sur la production | 🔴 | **0.1 / 0.3** |
| 58 | `storage:clean-orphans` destructeur | 🔴 | **0.6** |
| 59 | `ClientController::destroy` — commandes payées | 🔴 | **0.7** |
| 60 | Export scolaire : omission silencieuse | 🟠 | **0.14** |
| 61 | Anti-bruteforce codes promo → DoS | 🟠 | **0.8 / 0.18** |
| 62 | XSS `v-html` sur `customer_email` | 🔴 | **0.12** |
| 63 | Dashboard : 6 requêtes, compteurs faux | 🟠 | **3.6** |
| 64 | Stats sur la page courante | 🟠 | **3.6** |
| 65 | Tri du `DataTable` décoratif | 🟠 | **0.18 / 2.4 / 3.6** |
| 66 | Libellés non associés, `div @click` | 🟡 | **3.6** |
| 67 | Aucun `autocomplete` | 🟡 | **3.6** |
| 68 | Timers de recherche non nettoyés | 🟢 | **3.6** |

### Items mineurs supplémentaires (§12.6 et §13.7)

| Item | Phase |
|---|---|
`addItem` n'écrête pas le cumul de quantité | **3.3**
`UpdateUserRequest` : rétrogradation du dernier admin | **3.2**
`StoreUserRequest` : mot de passe `min:8` | **0.13**
`UpdateProfileRequest` : email sans confirmation | **3.2**
`AccountController` expose `download_token` | **2.5**
`BookingRequestController` écrase `gdpr_consent_at` | **3.5**
`Photo::getDownloadUrlAttribute()` (raccourci + mort) | **2.1**
`session_id` accepté en paramètre de requête | **2.4**
`PersonalDataEraser` : compteur de PDF faussé | **3.7**
Repli `productTypes` sans `print_scolaire` | **3.6**
`Makefile` annonce « MailHog » | **0.1**
`/gallery` pré-rendu alors que `noindex` | **3.6**
3 `fetch(download_url)` sans vérification de statut | **3.6**
`Galleries.vue` plafonne à 100 clients | **3.6**
`AdminHeader` / `PhotosManager` avalent 8 erreurs | **3.6**
`Cart.vue` sans état d'erreur inline | **3.6**
Aucun « réessayer » sur 5 vues publiques | **3.6**
`SchoolSessions.vue` : paliers par division (écart d'1 ct) | **3.4**

---

# 11. Critères de recette globaux

Le projet est considéré comme refondu quand **tout** ce qui suit est vrai.

## Sécurité
```
[ ] Aucun des 16 constats critiques n'est ouvert
[ ] Une CSP est posée et aucune violation n'apparaît en console
[ ] Aucun token d'authentification n'est accessible à JavaScript
[ ] Le compte admin est protégé par un TOTP
[ ] La déconnexion révoque session ET token, vérifié par test automatisé
[ ] Les rate limits sont effectifs (IP réelle) et ne se déclenchent pas entre clients
[ ] `composer audit` et `npm audit` passent en CI
```
## Données et conformité
```
[ ] Un dump restauré quotidiennement, restauration testée mensuellement
[ ] Aucune facture ne peut être détruite depuis l'interface
[ ] Un remboursement laisse une trace en base et un avoir numéroté
[ ] Un effacement RGPD passe par un seul chemin, tracé, avec confirmation
[ ] `consent_ip` contient l'IP réelle du client
[ ] Le registre des traitements existe et couvre les contacts scolaires
```
## Exploitation
```
[ ] Les 4 sondes sont vertes, les 3 filets externes reçoivent des signaux
[ ] Un export de 3600 s ne retarde aucun email transactionnel
[ ] L'espace disque du NAS est supervisé
[ ] Un rollback se fait par tag d'image en moins de 5 minutes
[ ] `storage:clean-orphans` tourne en dry-run hebdomadaire, avec rapport
```
## Qualité
```
[ ] PHPStan niveau 5+ bloquant, 0 erreur
[ ] Couverture mesurée, et non nulle sur : facturation, panier, auth, sessions scolaires
[ ] Vitest en place, tests sur les stores et les utilitaires
[ ] Aucun fichier > 400 lignes sans justification écrite
[ ] Toutes les réponses API suivent la même convention
```
## Fonctionnel
```
[ ] Une facture est juste : quantité, prix unitaire, total, adresse du client
[ ] L'email de tirages indique le bon nombre d'exemplaires
[ ] Les compteurs du tableau de bord sont exacts au-delà de 100 lignes
[ ] Le tri des tableaux fonctionne, ou n'est pas proposé
[ ] Les formulaires publics sont utilisables au clavier et avec un lecteur d'écran
[ ] L'autoremplissage du navigateur fonctionne dans le tunnel de paiement
[ ] Un client retrouve son mot de passe sans intervention
```

---

# 12. Journal de décisions

À remplir au fil de la phase 1. **Une décision non écrite est une décision qui sera reprise.**

| Date | Réf. | Décision | Par | Justification |
|---|---|---|---|---|
| 2026-08-22 | D1 | **Option C nouvelle** : surcharge de `prepareBindings()` sur la connexion. Ni A ni B. | mesure | A réfutée par test ; voir §1bis |
| | D2 | | | |
| | D3 | | | |
| | D4 | | | |
| | D5 | | | |
| | D6 | | | |
| | D7 | | | |
| | D8 | | | |
| | #34 | preview/thumbnail derrière token ? | | |
| | #31 | `clients.source` : recalculer ou abandonner | | |
| | #6 | Réémettre les factures fausses ? | | |
| | #48 | Format de l'export comptable | | |
| | | | | |

---

# 13. Ce que ce plan ne peut pas garantir

Par honnêteté, et parce que ces limites doivent être levées en phase 1.

```
[ ] Aucune donnée réelle n'a été consultée → toutes les volumétries sont des hypothèses
[ ] Le schéma est reconstruit depuis les migrations, pas relevé en base — et les deux divergent
[ ] Aucun test d'intrusion : les vulnérabilités sont déduites du code
[ ] Aucune mesure de performance : les budgets sont des estimations
[ ] L'historique Git est inaccessible depuis cette copie (1 commit squashé)
[ ] Le besoin métier n'a pas été recueilli — D3 à D7 en dépendent
[ ] Trois bugs restent « à confirmer par un test » : logout(), authorize(), reorder()
[ ] Il reste ~16 000 lignes non instruites (13 vues admin en détail, PersonalDataLocator,
    45 FormRequests, ce que les 34 fichiers de test n'assertent pas)
    → PHPStan niveau 5+ et une mesure de couverture rendront plus qu'une 4ᵉ passe manuelle
```

---

*Plan de refonte — dérivé de `AUDIT_PROJET.md` (13 sections, 8 594 lignes).*
*68 items tracés · 5 phases · 50-53 jours · dernière mise à jour 2026-08-22.*
