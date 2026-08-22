<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BaseConnection;

/**
 * Lie les booléens en littéraux 'true'/'false' plutôt qu'en entiers.
 *
 * Le pooler transaction impose PDO::ATTR_EMULATE_PREPARES, donc PDO interpole les
 * paramètres côté client. Or prepareBindings() de Laravel convertit tout booléen en
 * entier : where('is_video', false) produit le SQL littéral `= 0`, et PostgreSQL n'a
 * pas d'opérateur boolean = integer. En INSERT, l'erreur est « column is of type
 * boolean but expression is of type integer ».
 *
 * PostgreSQL accepte en revanche les littéraux quotés 'true' et 'false'. Cette seule
 * surcharge remplace le trait CastsBooleansForPostgres, qui appliquait la même
 * conversion sur 11 modèles, et les 13 whereRaw qui contournaient le problème.
 */
class PostgresConnection extends BaseConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
