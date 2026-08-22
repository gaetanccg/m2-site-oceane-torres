# Autorisation — qui a le droit de quoi

> Établi en phase 2.5. L'audit avait relevé **quatre mécanismes contradictoires** et la
> règle des « 30 minutes » écrite deux fois avec **deux colonnes différentes**
> (`created_at` d'un côté, `paid_at` de l'autre).

## 1. Les trois niveaux

| Niveau | Mécanisme | Où |
|---|---|---|
Authentification | Sanctum — cookie de session **ou** Bearer | middleware `auth:sanctum` |
Rôle | `role === 'admin'` | middleware `admin` (`EnsureUserIsAdmin`) |
Droit sur une ressource | **Policy, toujours via le Gate** | `app/Policies/` |

Un endpoint d'administration se protège par les middlewares. Un droit qui dépend de la
ressource visée se décide dans une Policy. Jamais les deux mélangés dans un contrôleur.

## 2. Règle unique : la Policy passe par le Gate

```php
Gate::forUser($user)->allows('view', [$order, $token, $email]);   // ✅
$this->authorize('view', [$order, $token]);                       // ✅ (contrôleur)

app(OrderPolicy::class)->view($user, $order, $token);              // ❌ contourne le Gate
if ($order->user_id === $user->id) { … }                          // ❌ règle dupliquée
```

Appeler la Policy en direct contourne le Gate : plus de `before`, plus de possibilité
d'intercepter, et surtout aucune garantie que ce soit la même règle qu'ailleurs. C'est
exactement ainsi que deux versions divergentes de la même règle ont coexisté.

Laravel 12 découvre les Policies par convention (`App\Models\X` → `App\Policies\XPolicy`).
Aucun enregistrement n'est nécessaire.

**Invités.** Le premier paramètre doit être `?User $user`. Sans le `?`, Laravel refuse la
Policy pour un visiteur non authentifié sans même l'exécuter — or la plupart des accès
ici sont des accès invités porteurs d'un jeton.

## 3. Matrice des droits

### Commande — `OrderPolicy`

| Qui | `view` | `download` |
|---|---|---|
Propriétaire authentifié (`user_id`) | ✅ | ✅ |
Invité dont l'email correspond à `guest_email` | ✅ | ❌ |
Porteur du jeton de téléchargement (`metadata.download_token`, 7 j) | ✅ | ✅ |
Porteur d'une signature d'accès valide (2 h) | ✅ | ✅ |
Quiconque, sur une commande récente | ❌ | ❌ |

`download` exige en plus que la commande soit payée.

L'asymétrie sur l'email est voulue : une correspondance d'email n'est pas une preuve de
possession, elle suffit à consulter un récapitulatif mais pas à récupérer les fichiers.

### Galerie — `GalleryPolicy`

| Qui | `view` / `download` |
|---|---|
Galerie `public` | ✅ sans condition |
Galerie `event` publiée | ✅ sans condition |
Porteur d'un `access_token` valide | ✅ |
Propriétaire (`user_id`) ou destinataire (`assigned_email`) | ✅ |
Autre | ❌ |

`download` délègue aujourd'hui à `view`. Si les deux doivent diverger — par exemple
n'autoriser le téléchargement que sur les photos marquées `is_downloadable` — c'est dans
la Policy que la distinction s'écrit, pas dans un contrôleur.

## 4. Accès sans compte : les deux porteurs

Le parcours d'achat est majoritairement **invité**. Deux jetons distincts, deux rôles.

| | Jeton de téléchargement | Signature d'accès |
|---|---|---|
Forme | 64 caractères aléatoires en base | HMAC de `id\|expiration` sous `APP_KEY` |
Durée | 7 jours | 2 heures |
Stocké | `orders.metadata` | **rien** — vérifiable sans lecture |
Émis à | la confirmation du paiement | la création du checkout |
Transmis par | email de confirmation | `return_url` de SumUp |

Les deux arrivent par le même paramètre `?token=`, que le front transmettait déjà. La
Policy essaie l'un puis l'autre.

**Pourquoi deux.** Le `return_url` est construit *avant* le paiement, or le jeton de
téléchargement n'est généré qu'*à* la confirmation du paiement. Il ne peut donc pas
figurer dans l'URL de retour. Sans la signature, l'accès reposait sur « commande créée
depuis moins de 30 minutes » — une règle qui accorde la lecture du nom, de l'email et de
l'adresse de livraison à quiconque connaît l'identifiant, sans rien prouver.

La commande gratuite ne passe pas par SumUp : `confirmFreeOrder` renvoie la signature
dans sa réponse, et le front la propage dans l'URL.

## 5. Ce qui ne doit jamais sortir

`Gallery` masque par défaut (`$hidden`) trois champs, rendus visibles explicitement par
`withPrivateFields()` :

| Champ | Pourquoi |
|---|---|
`access_token` | ouvre la galerie téléchargeable, donc les photos **sans filigrane** |
`assigned_email` | donnée personnelle d'un tiers |
`sms_template` | champ d'administration |

Visibles pour : l'administration, le propriétaire de la galerie, et l'endpoint de
téléchargement lui-même — dont l'appelant détient déjà le jeton.

Le masquage est **par défaut** et non par liste blanche par endpoint : un nouvel endpoint
public ne peut plus les exposer par oubli. C'était le défaut d'origine — `Gallery`
n'avait aucun `$hidden`, et un code de partage à 6 caractères livrait le jeton.

## 6. Endpoint retiré

`POST /orders/by-email` renvoyait **toutes les commandes d'une adresse** — nom, email,
adresse de livraison — sans authentification. La limitation de débit ne faisait que
ralentir l'énumération. Aucun consommateur ne l'appelait.

Pour rétablir la fonction proprement : n'accuser réception que d'une demande, et envoyer
**par email** un lien signé vers la liste. La réponse HTTP ne doit jamais dépendre de
l'existence de commandes pour cette adresse, sinon elle devient un oracle d'énumération.

## 7. Points ouverts

- **#34** — `preview_url` et `thumbnail_url` ne passent pas derrière le jeton de galerie :
  régénérer le code de partage ne révoque donc pas l'accès aux aperçus déjà connus.
  Décision produit : soit les faire passer derrière le jeton, soit assumer et documenter.
- **D8** — périmètre de l'authentification (#13, #28) : à trancher.
