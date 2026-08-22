<?php

namespace Tests\Feature;

use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrestationSerializationTest extends TestCase
{
    use RefreshDatabase;

    private const CLES = [
        'id', 'icon', 'title', 'description', 'features', 'price', 'price_text',
        'price_unit', 'duration', 'category', 'background_image',
        'background_opacity', 'disclaimer', 'is_active', 'sort_order',
    ];

    private function prestation(array $attributes = []): Prestation
    {
        return Prestation::create([...[
            'title' => 'Séance Portrait',
            'description' => 'Studio ou extérieur.',
            'price' => 150,
            'duration' => 60,
            'category' => 'Portrait',
            'is_active' => true,
            'sort_order' => 1,
            'background_opacity' => 0.5,
            'features' => ['10 photos retouchées'],
        ], ...$attributes]);
    }

    public function test_la_liste_publique_expose_exactement_la_forme_attendue(): void
    {
        $this->prestation();

        $body = $this->getJson('/api/prestations')->assertOk()->json('prestations.0');

        $this->assertSame(self::CLES, array_keys($body));
    }

    /**
     * Le cast `decimal:2` du modele serialisait ces deux champs en chaines, alors que
     * les types front les declarent `number`.
     */
    public function test_les_montants_ne_sont_plus_envoyes_en_chaines(): void
    {
        // JSON ne distingue pas int et float : 149.90 le montre, 150.0 se relirait en int.
        $this->prestation(['price' => 149.90, 'background_opacity' => 0.35]);

        $body = $this->getJson('/api/prestations')->assertOk()->json('prestations.0');

        $this->assertIsNumeric($body['price']);
        $this->assertIsNumeric($body['background_opacity']);
        $this->assertSame(149.9, $body['price']);
        $this->assertSame(0.35, $body['background_opacity']);
    }

    public function test_les_horodatages_internes_ne_sortent_plus(): void
    {
        $this->prestation();

        $body = $this->getJson('/api/prestations')->assertOk()->json('prestations.0');

        $this->assertArrayNotHasKey('created_at', $body);
        $this->assertArrayNotHasKey('updated_at', $body);
    }

    public function test_features_reste_un_tableau(): void
    {
        $this->prestation(['features' => ['a', 'b']]);

        $body = $this->getJson('/api/prestations')->assertOk()->json('prestations.0');

        $this->assertSame(['a', 'b'], $body['features']);
    }

    public function test_l_administration_recoit_la_meme_forme(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->prestation(['is_active' => false]);

        $body = $this->getJson('/api/admin/prestations')->assertOk()->json('data.0');

        $this->assertSame(self::CLES, array_keys($body));
        $this->assertFalse($body['is_active']);
    }
}
