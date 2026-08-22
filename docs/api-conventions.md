# Conventions de l'API

> Établi en phase 2.4 du plan de refonte. L'audit avait relevé **quatre formes de
> réponse différentes dans un seul contrôleur de 99 lignes**, 97 occurrences de
> `'success' => true`, 38 de `'data' =>`, des clés ad hoc, des paginateurs Laravel
> bruts, et 4 API Resources pour 151 endpoints.
>
> Le coût était mesurable côté front : deux couches d'accès parallèles, ~1 000 lignes
> de types décrivant une forme par endpoint, et 8 `fetch()` contournant la couche de
> services précisément parce que la réponse ne rentrait dans aucun helper.

## 1. Enveloppe de réponse

Une seule forme, pour toutes les réponses de succès.

```json
{ "data": …, "meta": { … }, "message": "…" }
```

| Clé | Présence | Contenu |
|---|---|---|
`data` | **toujours** | la ressource, ou la liste de ressources. `null` si l'action ne renvoie rien |
`meta` | si pagination ou contexte | `pagination`, agrégats, options d'affichage |
`message` | si l'action mérite un retour lisible | phrase destinée à l'utilisateur, en français |

Il n'y a **pas** de clé `success`. Le code HTTP porte cette information : un 2xx est un
succès, tout le reste ne l'est pas. Une clé booléenne redondante invite à des réponses
« 200 avec success:false », que le front doit alors tester deux fois.

### Liste paginée

```json
{
  "data": [ … ],
  "meta": {
    "pagination": {
      "current_page": 1, "last_page": 4, "per_page": 20,
      "total": 73, "from": 1, "to": 20
    }
  }
}
```

Ne jamais renvoyer le paginateur Laravel brut : sa forme dépend d'une version de
framework, et il expose `links` et `path` dont le front n'a pas l'usage.

## 2. Erreurs

```json
{ "message": "…", "errors": { "champ": ["…"] } }
```

`errors` n'est présent que sur une 422. C'est la forme native de Laravel, donc le
handler global n'a rien à réécrire.

| Code | Quand |
|---|---|
400 | `BusinessException` — règle métier violée, message sûr à afficher |
401 | non authentifié |
403 | authentifié mais non autorisé |
404 | ressource inexistante — **jamais** le nom de la classe du modèle |
409 | conflit d'état (commande déjà payée, code promo épuisé) |
422 | échec de validation |
429 | limite de débit atteinte |
503 | indisponibilité **transitoire** — c'est ce que lit SumUp pour retenter |

## 3. API Resource par ressource

Toute ressource exposée passe par une `JsonResource`. Pas de tableau construit à la
main dans un contrôleur, pas de sérialisation Eloquent nue.

La sérialisation nue dépend de `$fillable`, `$hidden` et `$appends` : ajouter un
`$appends` à un modèle change alors la réponse de tous les endpoints qui l'exposent,
sans que rien ne le signale. C'est ainsi que `display_url`, marqué `@deprecated`, est
resté dans les réponses.

Une méthode statique publique qui formate une ressource est une API Resource déguisée —
`Admin/OrderController::formatOrder()` en était une, appelée depuis un autre contrôleur.

## 4. Pagination, tri, filtres

| Paramètre | Règle |
|---|---|
`per_page` | accepté **partout** où il y a pagination · défaut par endpoint · **plafonné à 100** |
`page` | standard Laravel |
`sort_by` | validé contre une **allowlist** de colonnes, par endpoint |
`sort_dir` | `asc` ou `desc`, défaut `desc` |

`sort_by` ne doit jamais atteindre le SQL sans passer par l'allowlist. Le trait
`App\Http\Concerns\PaginatesQueries` implémente les deux et retombe sur la colonne par
défaut pour toute valeur non autorisée.

Un plafond sur `per_page` n'est pas du confort : sans lui, `?per_page=100000` sur les
galeries déclenche autant de signatures MinIO qu'il y a de photos.

## 5. Règles côté front

- **Une seule** couche d'accès HTTP. Deux couches parallèles produisent deux types de
  retour pour la même chose.
- **Aucun `fetch()` hors de la couche de services.** Un `fetch()` direct n'a ni gestion
  d'erreur normalisée, ni timeout, ni interception du 401.
- Toujours vérifier `response.ok` avant de lire un corps. Sans cela, un téléchargement
  qui échoue produit un fichier contenant le XML d'erreur S3, sous le nom attendu.

## 6. Migration de l'existant

L'enveloppe unique est une **rupture de contrat** : elle change la forme de chaque
réponse, donc casse chaque consommateur front. Elle ne peut pas se faire endpoint par
endpoint sans migrer le consommateur dans le même mouvement.

Ce qui est déjà en place :

- [x] `per_page` accepté partout, plafonné à 100
- [x] `sort_by` / `sort_dir` avec allowlist
- [ ] une API Resource par ressource exposée
- [ ] `Admin/OrderController::formatOrder()` remplacée par une Resource
- [ ] enveloppe unique, back **et** front migrés ensemble
- [ ] fusion des deux couches d'accès front
- [ ] suppression des `fetch()` sauvages
- [ ] régénération des types depuis la convention

**Ordre recommandé** : les `fetch()` sauvages et la fusion des couches d'accès d'abord
— ils ne changent aucune réponse. Puis les API Resources, ressource par ressource, en
conservant la forme actuelle. L'enveloppe en dernier, quand un seul point du front
décode les réponses : elle devient alors un changement local.

Ne pas entamer l'enveloppe sans pouvoir valider le parcours dans l'application : les
tests front couvrent les stores et les utilitaires, pas le rendu des vues.
