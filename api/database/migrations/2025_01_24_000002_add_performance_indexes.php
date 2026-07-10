<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Indexes pour la table clients.
        // Sur une installation fraîche, cette migration (2025) s'exécute AVANT
        // la création de la table clients (2026) : on saute alors ce bloc, les
        // index étant créés directement dans create_clients_table. Sur les bases
        // déjà migrées où clients existait, le bloc s'applique normalement.
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->index('source');
                $table->index('created_at');
            });
        }

        // Indexes pour la table galleries
        Schema::table('galleries', function (Blueprint $table) {
            $table->index('type');
            $table->index(['type', 'created_at']);
        });

        // Indexes pour la table reservations
        Schema::table('reservations', function (Blueprint $table) {
            $table->index('status');
            $table->index(['status', 'date']);
            $table->index('created_at');
        });

        // Indexes pour la table photos
        Schema::table('photos', function (Blueprint $table) {
            $table->index('is_downloadable');
            $table->index('is_liked');
            $table->index(['gallery_id', 'sort_order']);
        });

        // Indexes pour la table payments
        Schema::table('payments', function (Blueprint $table) {
            $table->index('status');
            $table->index(['status', 'created_at']);
        });

        // Indexes pour la table notifications
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropIndex(['source']);
                $table->dropIndex(['created_at']);
            });
        }

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['type', 'created_at']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'date']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['is_downloadable']);
            $table->dropIndex(['is_liked']);
            $table->dropIndex(['gallery_id', 'sort_order']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
