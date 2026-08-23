<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * CartService garantissait « un seul panier actif » au niveau applicatif, en
     * fusionnant et expirant les doublons. Deux requetes concurrentes pouvaient donc en
     * creer deux, et l'utilisateur payer un montant different de ce qu'il voyait.
     */
    public function up(): void
    {
        // Collapse prealable : le plus recent survit, les autres expirent. C'est
        // exactement ce que fait deja getOrCreateCart.
        DB::statement(<<<'SQL'
            UPDATE carts SET status = 'expired'
            WHERE status = 'active'
              AND user_id IS NOT NULL
              AND id NOT IN (
                  SELECT DISTINCT ON (user_id) id FROM carts
                  WHERE status = 'active' AND user_id IS NOT NULL
                  ORDER BY user_id, updated_at DESC, created_at DESC
              )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX carts_one_active_per_user
            ON carts (user_id)
            WHERE status = 'active' AND user_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS carts_one_active_per_user');
    }
};
