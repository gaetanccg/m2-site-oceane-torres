# Gestion RGPD & monitoring des données depuis l'admin — guide technique

> **Livré.** Ce document décrit la conception, la carte de traitement des données et le
> **pourquoi** de chaque décision. Il sert de référence pour la conformité et l'évolution
> de la fonctionnalité.

Fonctionnalité : depuis le back-office, **rechercher** une personne (par e-mail, téléphone
ou n° de commande), **visualiser** ce qu'on détient sur elle, **exporter** ses données
(ZIP : JSON + PDF), **supprimer / anonymiser** ses données, le tout **tracé** dans un
journal d'audit ; plus une **visionneuse de logs applicatifs**.

## Décisions retenues
1. **Logs** = 3 volets : (a) traçabilité RGPD (audit des actions), (b) écran « activité par personne », (c) visionneuse de `laravel.log`.
2. **Suppression** = **anonymiser/conserver** les données comptables (factures, paiements, commandes) au titre de l'obligation légale de conservation (~10 ans, Code de commerce), et **supprimer réellement** le reste (CRM, contacts, paniers, réservations, logs de téléchargement…).
3. **Export** = **ZIP** (un `data.json` structuré + les PDF de factures), généré via un **job asynchrone** (même patron que les exports scolaires).

---

## 1. État de l'existant (à compléter, pas à recréer)

Un embryon RGPD existe déjà :
- `App\Models\Client::gdprExport()` — `api/app/Models/Client.php:165`
- `App\Http\Controllers\Api\Admin\ClientController::destroy()` (anonymisation) — `:96` ; `gdprExport()` — `:153`
- Scope `App\Models\Order::forEmail()` — `api/app/Models/Order.php:179` (matche `users.email` via relation **ou** `orders.guest_email`)

**Lacunes à combler** (relevées dans l'inventaire) :
- Export **incomplet** : ignore `carts`/`cart_items`, `client_forms`, `gift_cards` (par `recipient_email`), `notifications`, les champs `shipping_*` + `consent_ip` des commandes, les paiements liés aux **commandes** (seuls ceux liés aux réservations sont pris), `photo_uploads`, `galleries.assigned_email`, l'adresse sur `users`.
- Effacement **incomplet** : ne touche pas la ligne `users` liée, `carts.guest_email`, les `shipping_*`/`consent_ip`, `client_forms`, `gift_cards`, `notifications`, `galleries.assigned_email`, ni les réservations matchées seulement par `guest_email`.
- **Uniquement par e-mail** : pas de recherche par téléphone ni par n° de commande.

## 2. Architecture admin (patrons à réutiliser)

- **Routes** : groupe `Route::middleware(['auth:sanctum','admin'])->prefix('admin')` — `api/routes/api.php:172`. Guard admin = middleware `EnsureUserIsAdmin` (`User::isAdmin()` → `role === 'admin'`).
- **Contrôleurs** : `api/app/Http/Controllers/Api/Admin/*` ; **Form Requests** : `api/app/Http/Requests/Admin/*` ; logique métier dans `api/app/Services/*`.
- **Enveloppe de réponse** : `{ success: true, <clé>: [...], pagination: {...} }` (cf. `Admin/OrderController@index`).
- **Export fichier async (patron de référence)** : `SchoolSessionExport` (modèle + statut) + `GenerateSchoolSessionExportJob` + `SchoolSessionExportService` + endpoints `createExport`/`latestExport`/`downloadExport` (polling côté front, `BinaryFileResponse`).
- **Fichiers** : `InvoiceService` (PDF facture sur MinIO), `MinioStorageService` (suppression objets).
- **Front** : vues `web/src/views/admin/*` (enfants d'`/admin`, `meta.requiresAdmin`) ; services `web/src/services/admin/*` étendant `BaseAdminService`, agrégés dans `web/src/services/adminApi.ts` ; UI via `@/components/admin/ui/DataTable` (+ `AdminHeader`, `Modal`, `Button`, `StatusBadge`) ; nav dans `AdminSidebar.vue` (`menuItems`) ; types dans `web/src/types/admin`.
- **Aucun audit-log** aujourd'hui. Logs applicatifs : `storage/logs/laravel.log` (`LOG_CHANNEL=stack`, `LOG_STACK=single`).

---

## 3. Cœur du système : le résolveur de « personne concernée »

Service central **`PersonalDataLocator`** (`api/app/Services/Privacy/PersonalDataLocator.php`), réutilisé par l'activité, l'export ET l'effacement.

Entrée : `(type ∈ {email, phone, order_number}, value)`.
Sortie : un objet structuré recensant **toutes** les lignes rattachées, par catégorie, avec pour chacune la **classe de traitement** (voir §4).

Clés de recherche (tables.colonnes exactes) :

| Clé | Tables.colonnes à interroger |
|---|---|
| **email** | `users.email`, `clients.email`, `orders.guest_email` (+ via `users`), `carts.guest_email`, `reservations.guest_email`, `contact_messages.email`, `galleries.assigned_email`, `gift_cards.recipient_email`, `password_reset_tokens.email` |
| **phone** | `users.phone`, `clients.phone`, `reservations.guest_phone`, `orders.shipping_phone`, `contact_messages.phone`, `client_forms.phone` |
| **order_number** | `orders.order_number` → puis `order_items.order_id`, `payments.order_id`, `invoices.order_id`, `carts.id = orders.cart_id` |

> ⚠️ Faux positifs : un même téléphone/e-mail peut être réutilisé. La recherche renvoie un **regroupement** ; l'admin **choisit/valide** les entités avant export/suppression (jamais d'action en masse aveugle).

---

## 4. Carte de traitement par table (le cœur métier)

Trois classes : **CONSERVER** (obligation légale, non supprimable pendant la durée de rétention), **ANONYMISER** (vider les champs PII, garder la ligne), **SUPPRIMER** (ligne détruite).

| Table | À l'EXPORT | À l'EFFACEMENT | Notes |
|---|---|---|---|
| `invoices` (+ PDF MinIO) | ✅ inclure | **CONSERVER** | Obligation légale ~10 ans ; PDF conservé tel quel |
| `payments` | ✅ | **CONSERVER** | Pièce comptable |
| `orders` | ✅ | **CONSERVER** (purge après rétention) | Rattachée à la facture ; informer la personne que c'est conservé au titre légal |
| `order_items` | ✅ | **CONSERVER** | Fait partie de la commande |
| `gift_cards` (+ PDF) | ✅ (par `recipient_email`) | **ANONYMISER** le destinataire (garder code/montant/lien paiement) | Lié paiement |
| `users` | ✅ (dont adresse) | **ANONYMISER** les champs PII (ou supprimer si aucune commande) | `orders.user_id` = `set null` |
| `clients` | ✅ | **SUPPRIMER** | Hub CRM |
| `reservations` | ✅ | **SUPPRIMER** (ou anonymiser si acompte payé) | inclut demandes de booking (front `BookingRequestModal`) |
| `client_forms` | ✅ | **SUPPRIMER** | via `reservation_id` |
| `carts` / `cart_items` | ✅ | **SUPPRIMER** | Transitoire |
| `contact_messages` | ✅ | **SUPPRIMER** | Formulaire contact (dont `consent_ip`) |
| `galleries.assigned_email` | ✅ | **ANONYMISER** le champ (galerie = contenu métier) | `access_token`/`share_code` à vider aussi |
| `photos` (fichiers MinIO) | référence | **SUPPRIMER** si portraits/scolaire propres à la personne | via galerie ; prudence contenu partagé |
| `download_logs` | ✅ (via galeries) | **SUPPRIMER** | `ip_address`, `user_agent` |
| `photo_uploads` | ✅ | **SUPPRIMER** | `original_filename` |
| `notifications` | ✅ | **SUPPRIMER** | `user_id` cascade |
| `sessions`, `password_reset_tokens`, `personal_access_tokens` | — | **SUPPRIMER** | infra (IP, tokens) |

> **Boucle de rétention** : une commande console planifiée `privacy:purge-expired` supprime définitivement les enregistrements « CONSERVER » dont la durée légale est expirée. Sans ça, l'effacement n'est jamais « complet ».

Services dédiés :
- **`PersonalDataExporter`** — construit `data.json` (toutes tables ci-dessus) + rassemble les PDF factures → délègue au job async pour zipper (réutilise le patron `SchoolSessionExport`).
- **`PersonalDataEraser`** — applique la carte ci-dessus **dans une transaction**, supprime les fichiers MinIO concernés (hors factures conservées), renvoie un **récapitulatif** (compteurs par table) ; écrit une entrée d'audit.

---

## 5. Traçabilité RGPD (audit)

- Migration + modèle **`PrivacyAuditLog`** : `id`, `actor_user_id`, `action` (`search|export|erasure`), `subject_type` (`email|phone|order_number`), `subject_value`, `affected` (json : compteurs par table), `ip_address`, `created_at`.
- Écrit systématiquement par les services export/effacement (et optionnellement à la recherche).
- Consultable en lecture seule dans l'admin (liste paginée + filtres action/date).
- Note : le journal contient lui-même de la PII (email de la personne effacée) — c'est **légitime** (preuve de traitement) et à conserver de façon restreinte.

## 6. Visionneuse de logs applicatifs

- Endpoint admin `GET /admin/logs` : lit `storage/logs/laravel.log` en **tail paginé** (offset/limite — ne jamais charger tout le fichier), filtres par niveau (`ERROR/WARNING/…`) et recherche texte ; `GET /admin/logs/download` renvoie le fichier brut.
- Vue admin dédiée (lecture seule, monospace, auto-refresh optionnel).

---

## 7. Endpoints à ajouter (groupe `admin`)

```
GET    /admin/privacy/search?type=email|phone|order_number&value=...   → activité agrégée (PersonalDataLocator)
POST   /admin/privacy/export        {type,value}                        → crée un PrivacyExport (job async)
GET    /admin/privacy/export/{id}                                       → statut (polling)
GET    /admin/privacy/export/{id}/download                              → ZIP (BinaryFileResponse)
GET    /admin/privacy/erasure/preview?type=...&value=...                → aperçu (ce qui sera supprimé vs conservé)
POST   /admin/privacy/erasure       {type,value, confirm}               → exécute (transaction + audit + MinIO)
GET    /admin/privacy/audit                                             → journal d'audit (paginé)
GET    /admin/logs                                                      → visionneuse logs
GET    /admin/logs/download
```
Form Requests dans `api/app/Http/Requests/Admin/Privacy/*`.

## 8. Front (une section « RGPD / Données »)

- Nouveau service `web/src/services/admin/privacyApi.ts` (+ branché dans `adminApi.ts`), types dans `web/src/types/admin`.
- Vue **`web/src/views/admin/Privacy.vue`** : sélecteur de clé (email/tél/n° commande) + champ + recherche → **écran « activité par personne »** (cartes par catégorie : comptes, commandes, réservations, paniers, messages, téléchargements) ; boutons **« Exporter (ZIP) »** (job + polling + download) et **« Supprimer / anonymiser »**.
- **Garde-fous suppression** : ouvre une **modale d'aperçu** (X à supprimer, Y conservés au titre légal), exige une **confirmation tapée** (ex. saisir l'email), avertit de l'irréversibilité.
- Onglet/vue **« Journal RGPD »** (audit) et vue **« Logs applicatifs »**.
- Entrées dans `AdminSidebar.vue` (`menuItems`) + routes enfants d'`/admin` (`requiresAdmin`).

## 9. Sécurité & conformité

- Admin only (`auth:sanctum` + `admin`) sur tout le groupe.
- Effacement **transactionnel** + suppression fichiers MinIO **après** commit DB.
- Aperçu obligatoire + confirmation tapée avant effacement (pas d'action destructive en un clic).
- Audit systématique (qui/quoi/quand/IP).
- Tail des logs borné (pas de lecture intégrale d'un gros fichier).
- ⚠️ **Point juridique à valider** : la carte « CONSERVER » (durée de rétention factures/paiements) — faire relire par une source fiable/expert-comptable avant mise en prod.

## 10. Découpage en lots (checklist)

- [x] **Lot 0 — Audit** : migration + `PrivacyAuditLog` + service `PrivacyAuditLogger`. ✅
- [x] **Lot 1 — Résolveur + activité (lecture seule)** : `PersonalDataLocator` (email/tél/n° commande), endpoint `search` + `audit`, vue `Privacy.vue` (onglets Recherche + Journal), sidebar. ✅ (9 tests)
- [x] **Lot 2 — Export ZIP** : ✅ (8 tests)
  - [x] **Export global** (`PrivacyExport` + `PrivacyExportService::buildGlobal` + `GeneratePrivacyExportJob` + endpoints `export-all`/`exports/{id}`/`download` + UI bouton/polling).
  - [x] **Export ciblé par personne** (`buildSubject` réutilisant `PersonalDataLocator` + PDF des factures de la personne + endpoint `export-subject` + bouton dans les résultats).
- [x] **Lot 4 — Logs applicatifs** : `LogController` (tail borné + filtres niveau/recherche + download) + vue `Logs.vue` + sidebar. ✅ (5 tests)
- [x] **Lot 3 — Effacement** : `PersonalDataEraser` (carte §4, transaction + guards) + aperçu (`erasure/preview`) + confirmation tapée (`ErasePrivacyRequest`) + audit + suppression PDF bons cadeaux (MinIO) + commande `privacy:purge-expired` (rétention légale, `--years`/`--dry-run`). ✅ (7 tests) + vue (bouton danger + modale aperçu/confirmation).
- [x] **Lot 5 — Tests & doc** : tests ✅ (34 tests RGPD, 172 au total) ; README maj (endpoints `/admin/privacy/*` + `/admin/logs` + commande de purge) ; `privacy:purge-expired` planifiée le 1er de chaque mois (`routes/console.php`). Reste hors-scope dev : **relecture juridique de la durée de rétention** (§9).

Ordre conseillé : 0 → 1 → 2 → 4 → 3 (l'effacement en dernier, une fois l'export et l'aperçu fiables).

## 11. Tests (PHPUnit, aligné sur l'existant)

- `PersonalDataLocator` : retrouve bien toutes les lignes par email / téléphone / n° commande (y compris invités).
- Export : le JSON couvre **toutes** les tables de la carte ; le ZIP contient les PDF factures.
- Effacement : anonymise `users`/`gift_cards`/`galleries`, **conserve** `invoices`/`payments`/`orders`, **supprime** `clients`/`reservations`/`carts`/`contact_messages`/`download_logs` ; écrit une entrée d'audit ; supprime les bons objets MinIO (via `Storage::fake('minio')`).
- `privacy:purge-expired` : ne purge que le comptable au-delà de la rétention.
- Autorisation : 401 sans auth, 403 pour un non-admin, sur tous les endpoints.
- Logs : tail paginé, filtre niveau, non-admin refusé.

## 12. Risques / points d'attention

- **Irréversibilité** de l'effacement → aperçu + confirmation + audit obligatoires.
- **Exactitude juridique** de la rétention (§9) → à faire valider.
- **Faux positifs** sur téléphone/e-mail partagés → validation manuelle des entités.
- **Contenu partagé** (galerie scolaire) : supprimer les photos d'une personne sans casser une galerie collective → traiter au cas par cas (anonymiser l'accès plutôt que supprimer la galerie).
- **Volume des logs** applicatifs (`single`) → tail borné, envisager la rotation (`LOG_CHANNEL=daily`).
- PII dans le **journal d'audit** lui-même → accès restreint, rétention encadrée.
