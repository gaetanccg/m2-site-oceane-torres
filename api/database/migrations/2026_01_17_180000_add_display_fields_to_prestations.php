<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestations', function (Blueprint $table) {
            $table->string('price_text')->nullable()->after('price')->comment('Prix affiche (ex: 50€, sur devis)');
            $table->string('price_unit')->nullable()->after('price_text')->comment('Unite de prix (ex: A partir de)');
            $table->json('features')->nullable()->after('description')->comment('Liste des caracteristiques');
            $table->string('background_image')->nullable()->after('category')->comment('URL image de fond');
            $table->decimal('background_opacity', 3, 2)->default(0.15)->after('background_image');
            $table->text('disclaimer')->nullable()->after('background_opacity');
            $table->string('icon')->nullable()->after('title')->comment('Icone (portrait, sport, animal, moto, entreprise, video)');
        });
    }

    public function down(): void
    {
        Schema::table('prestations', function (Blueprint $table) {
            $table->dropColumn([
                'price_text',
                'price_unit',
                'features',
                'background_image',
                'background_opacity',
                'disclaimer',
                'icon',
            ]);
        });
    }
};
