# Registre des advisory locks PostgreSQL

> Établi en phase 3.1 (#50). Deux verrous existent, tous deux pour la même raison : une
> numérotation séquentielle **sans trou**. Un trou dans une séquence de factures est un
> problème légal, pas un détail d'affichage.

## Verrous attribués

| ID | Propriétaire | Protège | Portée |
|---|---|---|---|
`42` | `Order::generateOrderNumber()` | la séquence `OT-YYYY-NNNNN` | transaction |
`43` | `Invoice::generateInvoiceNumber()` | la séquence `F-YYYY-NNNNN` | transaction |

**`50` à `59` sont réservés** aux séquences comptables à venir (avoirs `AV-YYYY-NNNNN`,
selon D5). Ne pas réutiliser 42 ni 43 : deux séquences différentes derrière le même verrou
se sérialiseraient inutilement l'une sur l'autre.

## La règle qui compte : `xact`, jamais `session`

```sql
SELECT pg_advisory_xact_lock(42);   -- ✅ libéré à la fin de la transaction
SELECT pg_advisory_lock(42);        -- ❌ libéré à la fin de la SESSION
```

Deux raisons de n'utiliser que la variante `xact` ici.

**Le pooler.** La production se connecte au pooler Supabase en **mode transaction** (port
6543). Une connexion y est empruntée pour la durée d'une transaction puis rendue à
d'autres. Un verrou de session survivrait donc à la requête qui l'a pris, sur une
connexion réattribuée à quelqu'un d'autre — au mieux un verrou jamais relâché, au pire
relâché par un tiers.

**L'oubli.** Un `pg_advisory_unlock` manquant sur un chemin d'erreur laisse le verrou en
place. La variante `xact` n'a pas de chemin d'erreur : le `ROLLBACK` la libère.

## L'appel doit être dans une transaction

Hors transaction, `pg_advisory_xact_lock` s'acquiert et se libère **immédiatement** : la
protection est nulle et rien ne le signale.

Vérifié pour les deux :

- `42` — pris dans le hook `creating` de `Order`, lui-même appelé depuis
  `OrderService::createFromCart()`, entièrement enveloppé dans `DB::transaction`.
- `43` — pris dans `InvoiceService::generateForOrder()`, à l'intérieur de son
  `DB::transaction`.

En ajouter un ailleurs impose de vérifier le même point.

## Le verrou n'est pas la seule protection

`orders.order_number` et `invoices.invoice_number` portent une contrainte `UNIQUE`. Si le
verrou venait à manquer, une course produirait un échec d'insertion — bruyant — et non un
doublon silencieux. Garder les deux : le verrou évite la course, la contrainte rattrape le
verrou.

## Ajouter un verrou

1. Prendre le prochain identifiant libre de la plage réservée, et l'inscrire ici.
2. Utiliser `pg_advisory_xact_lock`.
3. Vérifier que l'appel est bien dans une transaction.
4. Adosser une contrainte `UNIQUE` à la séquence protégée.
