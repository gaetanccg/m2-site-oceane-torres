# Plan de refonte — Océane Torres Photographie

> **Document de travail exécutable.** Il traduit l'audit (`AUDIT_PROJET.md`, 13 sections) en actions
> ordonnées, chacune traçable jusqu'au constat qui la motive.
>
> **Date** : 2026-08-22 · **Source** : `AUDIT_PROJET.md` · **Périmètre** : 68 items de dette
> (16 critiques, 24 élevés) · **Charge estimée** : 50-53 jours

---

## 0. Mode d'emploi

> **Ce document a été scindé le 2026-08-30.** Il ne contient plus que ce qui est **devant
> nous** : l'ordre de travail, les décisions, ce qui reste. Le déroulé des phases 0 à 3 — ce
> qui était prescrit, ce qui a été fait, les écarts — est dans
> [`docs/journal-refonte.md`](docs/journal-refonte.md).
>
> Il faisait 2 638 lignes et deux métiers à la fois : journal de ce qui a été fait **et** liste
> de ce qui reste. C'est ce qui le rendait illisible.
>
> **Si vous n'ouvrez qu'une chose : §3.3.**

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
4. **Une affirmation de ce plan n'est pas une preuve.** Cinq d'entre elles se sont révélées
   fausses le 30/08 — dont §2.3 « Redis fait », alors qu'aucun environnement ne l'utilise.
   Vérifier avant de cocher, et dater ce qu'on écrit.

### 0.3. Choix d'approche retenu

**Restructuration ciblée**, pas réécriture complète (→ §11.3 de l'audit).

> Reposée le 2026-08-30 par panel contradictoire, avec des mesures cette fois : projet de
> 9 mois et demi, 13 419 lignes de données, 119 factures sous conservation légale, 100 commits
> de refonte déjà payés, 580 tests là où il n'y en avait aucun au front. **Conclusion
> inchangée** — le défaut réel n'est pas structurel mais fonctionnel : des fonctionnalités
> construites dans le désordre, dont certaines à zéro ligne en base. Cela se traite par
> décision et par suppression, pas par `git init`.

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

## 1. Où nous en sommes

> Dernière mise à jour : **2026-08-30**.

**Les phases 0 à 3 sont faites pour l'essentiel.** Leur déroulé — ce qui était prescrit, ce
qui a été fait, les écarts — est sorti de ce document : il est dans
[`docs/journal-refonte.md`](docs/journal-refonte.md), 1 979 lignes d'archive. On y va pour
comprendre *pourquoi* une chose a été faite ainsi, jamais pour savoir quoi faire ensuite.

**Les huit décisions D1 à D8 sont toutes tranchées** (§12). C'était le goulot : ni le code ni
le temps, mais ces décisions, qui bloquaient D2, D4, D5, D6 et par ricochet #48 et la moitié
de la phase 3.

**Ce qui reste tient dans une seule liste : §3.3, quinze étapes ordonnées.** Les trois
premières sont faites. Le point de bascule est à la neuvième, à environ neuf jours — après quoi on peut
s'arrêter sans dette.

| | |
|---|---|
| Décisions produit tranchées | **8 / 8** |
| Étapes de la liste vivante | **3 / 15** |
| Jusqu'au point de bascule | **~9 j** |
| Tests | **591** API (1 537 assertions) · **73** front |
| En attente de Gaëtan | **6 actions**, dont une urgente (§3bis) |

### Par où entrer dans ce document

| Section | Quand l'ouvrir |
|---|---|
| **§3.3** | Pour savoir quoi faire maintenant. C'est le plan. |
| **§3.1 · 3.2** | Le raisonnement derrière l'isolation de l'admin et les quatre décisions. |
| **§12** | Les huit décisions et leur justification. Vingt secondes. |
| **§3bis** | Ce qui n'attend que vous — comptes, clés, arbitrages. |
| **§2** | Ce qui reste des phases 0 à 3, sans avoir à lire le journal. |
| §4, §5 | Phases à venir. La 4 est à trancher (étape 6). |
| §8 à §11 | Conventions, invariants, matrice, recette. Consultation. |

---

## 2. Ce qui reste des phases 0 à 3

Le détail est au journal ; voici les seuls points encore ouverts.

**Phase 0** — la récurrence des sauvegardes n'est pas posée (§13bis, urgent) · UptimeRobot et
healthchecks.io à créer · les 6 lignes de recette qui exigent la production · #6, réémettre ou
non les factures dont le total de lignes était faux.

**Phase 2** — 🔴 **§2.3 est cochée à tort** : Redis n'est activé dans **aucun** environnement
réel (local et api sur `database`, préproduction **et production** sur `file`, zéro variable
`REDIS_*`), alors qu'un conteneur Redis tourne en production. C'est l'étape 5 · l'enveloppe
`{data, meta, message}` de §2.4 n'est appliquée nulle part — 154 `'success' =>` subsistent dans
les contrôleurs, et la convention écrite contredit frontalement le code · **aucune Resource
admin** n'existe : la surface admin sert des modèles Eloquent nus via `withPrivateFields()`,
la seule surface où la protection `$hidden` est délibérément désactivée (c'est l'étape 7 de D2)
· 4 contrôles d'accès restent hors du Gate, dont `POST /photos/{photo}/like`, public et sans
throttle · §2.6 n'a jamais été validée par un déploiement réel.

**Phase 3** — 3.7 : #23 (séparer les buckets photos/factures), #25 (webhooks de délivrabilité
Brevo), #38 (`docs/json-schemas.md` à écrire), #44 (supervision du disque du NAS), #52
(centraliser les mentions légales) · #28 TOTP, différé, ses trois prérequis non écrits ·
PHPStan absent (aucun `phpstan.neon`, aucune dépendance) · `declare(strict_types=1)` : 0 sur
189 fichiers.

**Tests manquants sur les domaines à risque** — cumul cross-galerie des packs
(`recalculatePackPrices`, `offer_signature`) · clôture, réouverture et reprise après échec des
sessions scolaires · parsing de l'arborescence classe/enfant · export ZIP avec `_index.csv`.

---

## 3. Isolation de l'admin, décisions tranchées et ordre d'exécution

> Établi le 2026-08-30 par relevé contradictoire : 3 architectures d'isolation notées par
> 3 juges sur 3 axes distincts, 4 décisions instruites séparément, analyse des manques du plan.
> Tout ce qui est chiffré ici a été mesuré sur la branche `refacto`.

### 3.1 Isolation de l'admin

**Retenu : console admin sur `admin.oceanetorresphotographie.fr`**, servie par le nginx du NAS,
exposée par le tunnel Cloudflare existant, derrière une politique Cloudflare Access. Seconde
entrée Vite depuis le **même** `web/src` — pas de paquet extrait, pas de code dupliqué.

Notes des trois axes — hôte séparé **6/7/8 = 21** · cloison CI **4/6/9 = 19** · frontière
serveur **9/5/5 = 19**. L'hôte séparé n'est premier que sur un axe mais dernier sur **aucun**.

**Mais elle ne part pas en premier.** Elle est précédée du durcissement serveur, qui seul ferme
quelque chose.

### Ce qui relève de l'obscurcissement, et ne compte pas comme de la sécurité

À dire net, parce que c'est là que « rendre l'admin imperméable » peut produire une fausse
assurance.

**Retirer les 15 chemins `/admin/*` du bundle ne ferme aucune porte.** Mesuré :
`index-*.js` = 137 969 octets, 15 chemins distincts, 91 occurrences. Ils se devinent en dix
minutes, et `render.yaml:23-25` sert `/assets/*` en `max-age=31536000, immutable` — les copies
déjà publiées restent lisibles. Le seul contrôle est `auth:sanctum` + `EnsureUserIsAdmin`, et
il ne bouge pas d'un pouce.

### Ce que l'hôte séparé ferme vraiment

**Une origine distincte, c'est un `localStorage` distinct.** `auth_token` vit aujourd'hui sur
l'origine publique, même clé pour l'administratrice et pour les 17 comptes clientes, **7 jours**
(`config/sanctum.php:55`), dans le même contexte JS que Google Analytics, Sentry et SumUp —
et `script-src` est encore en **Report-Only**. Une XSS sur le panier vole la session
d'administration.

### La moitié qu'elle ne ferme pas

`Auth::attempt()` ouvre **aussi** une session ; `config/cors.php:32` pose
`supports_credentials => true` avec l'origine publique dans la liste. Une XSS sur la boutique
peut donc faire `fetch('/api/admin/clients', {credentials:'include'})` sans jamais toucher au
`localStorage`. **L'isolation d'origine ne résout que la moitié du vol de credential.** L'autre
moitié, c'est la CSP `script-src` appliquée et le journal d'audit — pas le découpage des bundles.

### Rejeté explicitement

- **Cloudflare Access devant `api.*/api/admin/*`** : Access répond aux requêtes non
  authentifiées par une 302 HTML qu'un `fetch` ne suit pas. Et cet hôte porte le checkout,
  `/api/health` et le webhook SumUp — une erreur de périmètre y touche l'argent. Access va
  devant `admin.*`, un hôte de documents, en navigation de premier niveau.
- **Réactiver le CSRF sur `api/admin/*`** tel que conçu : `SESSION_DOMAIN` est host-only sur
  `api.*`, le cookie `XSRF-TOKEN` est donc illisible depuis l'origine Render, `baseApi.ts`
  renvoie `null` et l'en-tête n'est jamais envoyé. Retirer l'exception met **toutes** les
  mutations admin en 419.

### 3.2 Les quatre décisions — l'essentiel

**D2** — 7 contrôleurs de l'espace `Api\` servent **34 déclarations** de routes admin.
`DELETE /api/admin/galleries/{id_d_un_événement}` supprime la galerie, n'invalide pas le cache
du listing public (servi jusqu'à 5 min) et laisse les fichiers MinIO des sous-galeries
orphelins **définitivement** (`parent_id` en cascade). `type='public'` a **0 producteur** et
**3 consommateurs de sécurité** — une branche de privilège dormante. **4,5 j** (étapes 2-6) +
1,5 j pour les Resources admin. Si le budget se serre, **2-3-4 = 2 j** capturent les quatre
défauts. **D2 précède D6 et la migration d'enveloppe.**

**D4** — le bon reste émis, pas vendu. Les trois briques existent (dompdf éprouvé, Brevo avec
pièce jointe MinIO déjà écrite, écran admin de 387 lignes). **L'étape 7 (débit) n'est pas
reportable** : `GiftCard::use()` n'a aucun appelant, donc `outstanding` — la « Valeur totale
active » affichée en StatCard, un engagement au passif — deviendrait faux dès le premier bon
utilisé. **4,5 à 5 j** (le plan annonçait 2-3 j ; l'écart vient de l'écran de création absent
et du débit non outillé).

**D5** — 🔴 **prérequis que le plan ne nommait pas** : `invoices` ne stocke **aucun montant**,
le PDF est rendu depuis la commande vivante. Donc (a) un avoir n'a aucun total figé auquel
s'adosser, (b) toute modification de commande **réécrit silencieusement une facture émise**.
Réponse retenue : `credit_notes` porte son **propre instantané**. Figer les montants sur
`invoices` est nécessaire à **#48**, pas à D5.
**Trois effets de bord de `status='refunded'`, aucun écrit dans le plan** : révoque les
téléchargements de la cliente (`getOrderForDownload` exige `paid`) · rend son usage au code
promo (`GiftCode::paidCount()`) · sort rétroactivement une commande du CA de son mois
(`AdminStatsService::revenue()`). **4 à 5 j**, +1,5 à 2 j plus tard pour SumUp.

**D6** — invariant : *tout octet nécessaire au rendu de `/portfolio` est servi par Render ;
l'API n'intervient que pour **changer** le contenu, jamais pour le **servir***. Si l'API est
injoignable au build, **le build échoue franchement** et Render laisse le déploiement en cours
en ligne — la propriété est de construction, pas de politique.
Deux mesures qui rendent D6 plus rentable que prévu : **les originaux pleine résolution sont
publiés en clair** (`web/public/images/` = 286 Mo → `dist/images` = 311 Mo sur 367 Mo, sur le
site d'une photographe professionnelle) ; et **l'AVIF servi en primaire est plus lourd que le
WebP de repli** (31 104 Ko contre 20 436 Ko). **6 à 8 j.**
Coût d'exploitation à annoncer à Océane : publier une photo prend 5-10 min, pas 5 s, et
redéploie tout le code de la branche de production. En face, `dist/` passe de 367 Mo à ~55 Mo.

### 3.3 Ordre d'exécution

| # | Quoi | Pourquoi ICI | Charge |
|---|---|---|---|
| 1 | ✅ **`POST /gift-cards` sous garde admin** | N'importe lequel des 18 comptes clients pouvait créer un bon de 500 €. **Fait le 30/08** | 15 min |
| 2 | ✅ **L'administration ne part plus dans le bundle public** | Chunk d'entrée 137 969 → 121 162 octets, chemins `/admin` 15 → 0. Le second `login()` du store visait le **même** endpoint que `authApi`, sans appelant ; `AdminApiError` était redondant avec `AuthApiError`. Côté services, `login/logout/getUser` de `dashboardApi` étaient des copies : **supprimées, pas déplacées** — le plan disait de les déplacer, cela aurait créé des doublons. Vérifié au navigateur. **Fait le 30/08** | 0,5 j |
| 3 | ✅ **`AdminSurfaceAccessTest` — 96 routes × 3 acteurs** | Le filet avant D2. Périmètre défini par l'**URL et le contrôleur**, jamais par la présence du middleware : sinon une route qui s'échappe sort aussi de l'énumération, et le test passe. Identifiants **réels** — le route-model-binding s'exécute avant la garde, donc un id absent rend 404 et ne prouve rien. Éprouvé par deux mutations. **Fait le 30/08** | 1 j |
| 4 | 🔜 `routes/admin.php` via `withRouting` + test de garde + `throttle:admin` + abilities | Rend modifiables **en une ligne sur le groupe** toutes les mesures ultérieures, au lieu de 85 éditions. Aucun limiteur `admin` n'existe : les 95 routes sont sans plafond | 1,5 j |
| 5 | Établir l'état réel des environnements (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`) | §2.3 est cochée sur une supposition contredite par les deux `.env` | 0,25 j |
| 6 | Trancher le sort de la phase 4 | D2, D4 et D5 changent chacune le schéma, et le plan offre **deux véhicules contradictoires** | 0,5 j |
| 7 | Filet de tests de rendu (3 parcours) | Trois items du plan en sont prisonniers ; D2 et D6 en ajouteraient deux | 1 j |
| 8 | Règle ESLint + contrôle d'isolation **dans `build:prerender`** | ⚠️ Pas dans `ci.yml` : il n'existe **aucun workflow de déploiement**, Render construit seul. Un contrôle en CI ne bloquerait rien | 0,75 j |
| 9 | **D2 étapes 2-6** | Après 3 et 4. **Bloque D6** et la tranche admin des Resources | 3,5 j |
| 10 | **D4** | Après 1 et 9. Supprime du travail manuel récurrent, zéro risque sur le site vitrine. Rend plus tôt que D6 — le plan le plaçait derrière, à tort | 4,5 j |
| 11 | **D5** | **Dans** la phase 3.1, pendant que le domaine facturation est chaud. **Bloque #48** | 4,5 j |
| 12 | Isolation, partie infrastructure (entrée Vite, DNS, nginx, Access, trappe LAN) | Après 4, 8 et 9 : faire l'hôte séparé avant, c'est déménager du désordre | 3 j + 1 semaine d'usage |
| 13 | **D6** | Après 9 et 12. Les 3 correctifs détachés (fuite des originaux, AVIF, préchargements morts) partent **n'importe quand** | 7 j (+1 j) |
| 14 | Journal d'audit admin + #48 | 87 des 95 routes ne laissent aucune trace. #48 après 11 **et** 10 | 3 j |
| 15 | TOTP | En dernier, seul, jamais le même jour qu'Access. Pilotable par `ADMIN_2FA_REQUIRED` | 2,5 j |

### Le point de bascule — après l'étape 9

À ce moment-là : la faille des bons cadeaux est fermée · les 95 routes admin sont sous plafond,
dans un fichier dont la protection est portée par le **montage**, adossées à un test des trois
acteurs · la carte des chemins admin ne part plus chez chaque visiteur · les galeries ont un
discriminant explicite, `type='public'` est morte, les fichiers MinIO ne fuient plus · le plan
est de nouveau exact.

**On peut s'arrêter là sans dette.** Tout ce qui suit est **additif** : rien ne se dégrade si
on ne le fait pas. **Cumulé jusqu'au point de bascule : ~9 j.**

### 3.4 Ce qui reste à mesurer avant de chiffrer plus loin

- Combien des 211 commandes payées portent `sumup_transaction_id = sumup_checkout_id` — bloque
  toute estimation du remboursement par API.
- Si la clé SumUp porte la permission `transactions.refund` (test à 1 € en préproduction).
- Si `render.yaml` pilote réellement le service Render — le fichier prévient lui-même que non
  (`:3-6`), et les en-têtes de cache de D6 en dépendent.
- Si la chaîne Cloudflare → nginx → php-fpm remonte bien l'IP cliente : `nginx.prod.conf`
  configure `real_ip_header CF-Connecting-IP` depuis le 22/08, mais `consent_ip = 172.19.0.1`
  était relevé avant. Les 8 limiteurs de débit en dépendent.

---

## 3bis. Ce qui n'attend que vous

Ces points ne peuvent pas être fermés par du code.

| | Quoi | Pourquoi ça compte |
|---|---|---|
| 🔴 | **Poser les trois lignes de crontab sur le NAS** | Le seul filet de sauvegarde qui existe : la production est en Supabase plan Free, ni PITR ni sauvegarde quotidienne. Moins d'une heure, les lignes sont écrites dans `deploy/README.md` — **et elles omettaient `BACKUP_ENV_FILE`**, donc elles auraient sauvegardé la préproduction en silence. Corrigé le 30/08 |
| 🟠 | Créer les 2 moniteurs UptimeRobot + 2 checks healthchecks.io | La supervision externe n'est branchée qu'en préproduction. ~2 h |
| 🟠 | Clés MinIO restreintes au bucket `galleries-preprod` | Les clés actuelles ouvrent tout le serveur, partagé avec la production |
| 🟠 | Faire tourner les identifiants de production | Ils ont séjourné dans l'arborescence du projet ; la rotation est la seule façon d'annuler cette exposition |
| 🟡 | **#6** — réémettre ou non les factures dont le total de lignes était faux | Décision commerciale, pas technique |
| 🟡 | **#48** — faire relire le format d'export comptable | Un micro-entrepreneur déclare à l'encaissement : `date_piece` **et** `date_encaissement`, plus `reference_rectifiee`. Bloqué par D5 |

Les six lignes de recette de la phase 0 qui exigent la production restent également ouvertes
(voir le journal, §0.19).

---

# PHASE 4 — Migration des données — ⚠️ prémisse à trancher (étape 6)

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

# 10. Matrice de traçabilité — 82 items

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

### Items découverts après l'audit

| # | Item | Cot. | Phase | État |
|---|---|---|---|---|
| 69 | Le gabarit de facture ne compilait pas | 🔴 | **0.11** | ✅ fait |
| 70 | Supprimer une photo partagée détruit le fichier des autres élèves (804 lignes, 214 vendues) | 🔴 | **3.7** | ✅ 30/08 |
| 71 | Supprimer une galerie détruit des photos achetées, sans garde | 🔴 | **3.7** | ✅ 30/08 |
| 72 | Deux factures possibles pour une commande (`order_id` sans unicité) | 🔴 | **3.1** | ✅ 30/08 |
| 73 | « Total versé » à 0 € pour tous les clients — `clients` et `orders` non reliées | 🟠 | **D2 / 3.5** | ✅ provisoire, définitif en D2 |
| 74 | Le garde-fou du seeder aveugle à `DB_URL` | 🔴 | **0.1** | ✅ 30/08 |
| 75 | Les `.env.example` amorcent le bucket de production | 🟠 | **0.1** | ✅ 30/08 |
| 76 | Crontab de sauvegarde sans `BACKUP_ENV_FILE` : sauvegarderait la préproduction | 🔴 | **0.2** | ✅ doc corrigée, pose à faire |
| 77 | Le jeton d'admin est en `localStorage`, même origine que le site public | 🟠 | **§3.3** | ⏳ à traiter |
| 78 | Aucun journal d'audit des actions d'administration hors RGPD (87 routes sur 95) | 🟡 | **§3.3 étape 14** | ⏳ à traiter |
| 79 | `POST /gift-cards` ouverte à tout compte authentifié — bon de 500 € émissible par une cliente | 🔴 | **§3.3 étape 1** | ✅ 30/08 |
| 80 | Aucun limiteur de débit sur les 95 routes admin | 🟠 | **§3.3 étape 4** | ⏳ à traiter |
| 81 | Les originaux pleine résolution publiés en clair (286 Mo dans `dist/`) | 🟠 | **§3.2 D6** | ⏳ détachable |
| 82 | `invoices` ne stocke aucun montant : modifier une commande réécrit une facture émise | 🔴 | **§3.2 D5 / #48** | ⏳ à trancher |
| 83 | `/admin/dashboard` répondait 500 : `customer_name` est un accesseur, pas une colonne | 🔴 | **3.6 (régression)** | ✅ 30/08 |
| 84 | Le tableau de bord émet ses deux appels **en double** à chaque montage | 🟡 | **3.6** | ⏳ à traiter |
| 85 | `{export}` désigne **deux modèles** selon la route : `SchoolSessionExport` et `PrivacyExport` | 🟡 | **§3.2 D2** | ⏳ à traiter |

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
| 2026-08-22 | D1 | **Option C nouvelle** : surcharge de `prepareBindings()` sur la connexion. Ni A ni B. | mesure | A réfutée par test ; voir le journal, §1bis |
| 2026-08-30 | D2 | **Table unique + discriminant `kind` (`client\|event\|school`) + sous-classes Eloquent à global scope**, puis séparation des contrôleurs par audience. Ni table par nature, ni hiérarchie SQL. | Gaëtan → recommandation | Le discriminant est aujourd'hui éclaté sur 3 colonnes de 2 natures ; la garde est asymétrique ; `type='public'` a 0 producteur et 3 consommateurs de sécurité. Voir §3 |
| 2026-08-30 | D3 | Module Disponibilités **supprimé** | mesure | 0 ligne, 0 consommateur — fait le 22/08 |
| 2026-08-30 | D4 | **Option A — le bon reste émis par la photographe, non achetable en ligne.** On outille les 3 gestes manuels (visuel, envoi, solde), on ne construit aucun tunnel de paiement. | Gaëtan → recommandation | Il n'existe aucun endroit où encaisser : le bon paie une *prestation*, non vendue en ligne. Un tunnel serait le seul paiement ne produisant ni `Order` ni `Invoice`. Voir §3 |
| 2026-08-30 | D5 | **Avoir déclaratif, séquence `AV-YYYY-NNNNN` sous verrou 50, table auto-portante rendue inaltérable par trigger.** Remboursements partiels traités au même titre que les complets. Pas d'API SumUp maintenant. | Gaëtan → recommandation | Une écriture comptable ne doit pas dépendre d'un appel réseau ; 2 préconditions SumUp non vérifiables depuis le code. Voir §3 |
| 2026-08-30 | D6 | **Publication au build, jamais lecture au runtime.** Instantané versionné + deploy hook Render + `fetch-portfolio.js` avant `vite build`. | Gaëtan → recommandation | Contrainte imposée : le portfolio reste en ligne quand l'API est morte. Servir depuis MinIO ne serait pas un repli (même NAS, même tunnel). Voir §3 |
| 2026-08-23 | D7 | Contacts de sessions scolaires **persistés**, avec purge à clôture + 3 mois et inscription au RGPD | Gaëtan | Ajouter des coordonnées de parents sans effacement n'était pas acceptable — livré |
| 2026-08-22 | D8 | Authentification **hybride** conservée : session pour le front, jeton Bearer pour le reste | mesure | Livré en 3.2 |
| 2026-08-22 | #34 | Les dérivés **sans filigrane** exigent un credential, même sur galerie publique | mesure | Option A — livré |
| 2026-08-23 | #31 | `clients.source` **recalculée** et rendue fiable | mesure | Livré en 3.5 |
| | #6 | Réémettre les factures fausses ? | **en attente de Gaëtan** | Décision commerciale, pas technique |
| | #48 | Format de l'export comptable | **en attente du comptable** | `date_piece` **et** `date_encaissement` distinctes + `reference_rectifiee` : un micro-entrepreneur déclare à l'encaissement. Bloqué par D5 |
| 2026-08-30 | — | **Isolation de l'admin** : console sur `admin.oceanetorresphotographie.fr`, servie par le nginx du NAS, derrière Cloudflare Access. Seconde entrée Vite depuis le même `web/src`. | Gaëtan → recommandation | Voir §3. Précédée du durcissement serveur, qui seul ferme quelque chose |

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
*85 items tracés · 789 lignes · le déroulé des phases 0 à 3 est dans `docs/journal-refonte.md`.*
*Dernière mise à jour : 2026-08-30.*


---

