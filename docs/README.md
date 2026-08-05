# Documentation technique

Guides techniques par sujet : **ce qu'on a fait, comment et pourquoi**. Chacun est
autoportant et pensé pour rester utile sur un clone propre du repo.

| Guide | Sujet |
|-------|-------|
| [analyse-paiement-sumup.md](analyse-paiement-sumup.md) | Workflow de paiement SumUp : architecture (widget + webhook), invariant de sécurité, pièges SDK/3DS, diagnostic des échecs. |
| [feature-gift-code-promo.md](feature-gift-code-promo.md) | Codes promo (montant fixe / %) : schéma, calcul serveur, quota basé sur le payé, cas 0 €, anti brute-force. |
| [plan-rgpd-admin.md](plan-rgpd-admin.md) | RGPD depuis l'admin : recherche/export/effacement par personne, carte de traitement (conserver/anonymiser/supprimer), audit, purge de rétention. |
| [deploy-improve-photo-upload.md](deploy-improve-photo-upload.md) | Déploiement sur le NAS : pièges de configuration (pooler Postgres `6543`, rechargement des workers), vérifications post-déploiement. |
| [preprod-runbook.md](preprod-runbook.md) | Preprod : pièges réellement rencontrés (SSL, tunnel dashboard-managed, Compose V2, MinIO) et commandes d'exploitation. Complément de [`../PREPROD.md`](../PREPROD.md). |
| [supervision.md](supervision.md) | Supervision et alerte : périmètre, sondes et seuils, healthchecks Docker, alertes email, sondes externes (UptimeRobot / healthchecks.io / Sentry), procédure de réaction par type d'alerte. |
| [audit-supervision.md](audit-supervision.md) | État des lieux de l'observabilité **avant** la mise en place ci-dessus : ce qui existait, ce qui manquait, les cinq angles morts identifiés. |

## Convention

Les valeurs propres à l'infra sont **masquées par des placeholders** (`<NAS_IP>`,
`<NAS_USER>`, `<TUNNEL_ID>`, `<SUPABASE_PROD_REF>`, `<SUPABASE_PREPROD_REF>`,
`<supabase-pooler-host>`…). Aucun secret ni credential ne doit figurer dans ces
documents : ils sont versionnés.
