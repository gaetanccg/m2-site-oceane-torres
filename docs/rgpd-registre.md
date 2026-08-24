# Registre des traitements — inventaire technique

> **Ce document n'est pas le registre légal.** C'est l'inventaire technique qui l'alimente :
> il dit ce que le code stocke, où, combien de temps, et par quel chemin l'effacer. Les
> **bases légales** et les **finalités déclarées** relèvent d'Océane et, au besoin, d'un
> conseil juridique — les colonnes marquées 📋 restent à confirmer par elle.
>
> Établi en phase 3.4, à l'occasion de D7 : ajouter une table de coordonnées de parents
> d'élèves sans son effacement et sa durée de conservation n'était pas acceptable.
> Inventaire relevé dans le schéma, pas déduit.

## 1. Traitements

| Traitement | Table | Données | Conservation | Effacement |
|---|---|---|---|---|
Comptes clients | `users` | nom, prénom, email, téléphone, adresse postale | tant que le compte existe | anonymisation par `PersonalDataEraser` |
Fiches clients (CRM) | `clients` | nom, email, téléphone, notes | 📋 | suppression |
Commandes et facturation | `orders`, `invoices`, `payments` | identité, email, adresse de livraison, IP de consentement | **10 ans** (Code de commerce) | `privacy:purge-expired` |
Demandes de réservation | `reservations`, `client_forms` | nom, email, téléphone, préférences, message | 📋 | suppression |
Messages de contact | `contact_messages` | nom, email, téléphone, IP de consentement | 📋 | suppression |
Paniers | `carts` | email invité | expiration du panier | suppression |
Accès aux galeries | `galleries.assigned_email` | email du destinataire | vie de la galerie | suppression avec la galerie |
**Contacts de sessions scolaires** | `school_session_contacts` | nom du destinataire, email, téléphone, historique d'envoi | **clôture + 3 mois** | `privacy:purge-expired` · `PersonalDataEraser` |
**Notifications d'administration** | `notifications` | nom du client dans `message` et dans `data.client_name` | vie de la notification | `PersonalDataEraser` (par `data.reservation_id`)
Journal de connexions | `login_attempts` | email saisi, IP, user-agent | **90 jours** | `privacy:purge-expired` |
Journal des téléchargements | `download_logs` | adresse IP | 📋 | suppression avec la galerie |
Journal d'audit RGPD | `privacy_audit_logs` | IP de l'administrateur, sujet visé | 📋 — c'est la **preuve** des demandes traitées | conservé volontairement |
Exports RGPD générés | `privacy_exports` + ZIP | copie des données du sujet | **7 jours** | `exports:purge` |
Exports scolaires | `school_session_exports` + ZIP | commandes d'une session | **30 jours** | `exports:purge` |

## 2. Ce qui est automatisé

```
privacy:purge-expired   mensuel   commandes/factures 10 ans · contacts scolaires clôture+3 mois
                                  · journal de connexions 90 j
exports:purge           quotidien ZIP d'export : 30 j scolaire, 7 j RGPD, plus les orphelins
storage:clean-orphans   hebdo     fichiers MinIO sans référence en base (rapport seul)
```

Les durées sont des options de commande, pas des constantes : `--years`, `--login-days`,
`--school-contact-months`, `--school-days`, `--privacy-days`.

## 3. Droits des personnes

`/admin/privacy` couvre la recherche, l'export et l'effacement, par email, téléphone ou
numéro de commande. Voir `docs/plan-rgpd-admin.md`.

**Deux natures d'effacement, et la distinction compte.** Les données comptables sont
**conservées** — une facture ne s'efface pas avant dix ans — tandis que le CRM et les
journaux sont **supprimés**. Le compte est **anonymisé** plutôt que détruit, pour ne pas
briser les commandes qui le référencent.

Toute catégorie ajoutée doit l'être **dans les deux** : `PersonalDataLocator` (sinon une
recherche ne la voit pas) et `PersonalDataEraser` (sinon un effacement la laisse en place).
C'est exactement ce qui manquait pour les contacts scolaires avant D7.

🔴 **Et le piège suivant, trouvé en phase 3.7 : le propriétaire de la ligne n'est pas
toujours le sujet.** Les notifications d'administration ont `user_id` = l'identifiant de
l'**administratrice**, mais portent le nom du client dans `message` et dans
`data.client_name`. L'effacement filtrait sur le sujet : il ne les voyait donc jamais, et
le nom survivait indéfiniment à une demande d'effacement. Elles sont désormais retrouvées
par `data->>'reservation_id'`.

La leçon vaut pour toute table à venir : chercher les données personnelles **par contenu**,
pas seulement par propriétaire.

## 4. Sous-traitants

| Destinataire | Ce qu'il reçoit | Rôle |
|---|---|---|
Supabase (UE) | l'ensemble de la base | hébergement PostgreSQL |
MinIO (NAS, auto-hébergé) | photos, PDF de factures, ZIP d'export | stockage |
SumUp | montant, email, identité de l'acheteur | paiement |
Brevo | email ou téléphone du destinataire, contenu du message | envoi email et SMS |
Sentry (UE) | traces d'erreur | supervision · 📋 vérifier qu'aucune donnée personnelle n'y transite |
Render | rien de personnel — site statique | hébergement du front |

📋 Chacun exige un accord de sous-traitance (art. 28) à vérifier.

## 5. Violation de données (art. 33)

Notification à la CNIL sous **72 heures** après en avoir pris connaissance, et information
des personnes si le risque est élevé.

Ce que le code permet de répondre :

1. **Quoi** — le tableau du §1 dit quelles catégories sont dans quelle table.
2. **Qui** — `PersonalDataLocator` énumère les personnes concernées à partir d'un email,
   d'un téléphone ou d'un numéro de commande.
3. **Quand** — `login_attempts` porte les tentatives de connexion réussies et échouées avec
   IP et horodatage ; `privacy_audit_logs` porte les actions d'administration.
4. **Comment** — les erreurs applicatives remontent à Sentry depuis que le canal de log y
   est branché (phase 3.1).

**Limite connue** : il n'existe aucun journal des *lectures* de données. Une consultation
administrative de fiches clients ne laisse pas de trace, hors actions RGPD. En cas de
compromission d'un compte d'administration, on saurait dire *qu'il* y a eu accès — par
`login_attempts` — mais pas *à quoi*.
