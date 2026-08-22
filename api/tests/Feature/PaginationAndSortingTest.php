<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationAndSortingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_per_page_is_honoured(): void
    {
        Gallery::factory()->count(8)->create(['type' => 'private']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/galleries?per_page=3')
            ->assertOk()
            ->assertJsonPath('per_page', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_per_page_is_capped(): void
    {
        Gallery::factory()->count(3)->create(['type' => 'private']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/galleries?per_page=99999')
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }

    public function test_an_invalid_per_page_falls_back_to_the_default(): void
    {
        Gallery::factory()->count(3)->create(['type' => 'private']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/galleries?per_page=0')
            ->assertOk()
            ->assertJsonPath('per_page', 20);
    }

    public function test_sorting_ascending_and_descending_differ(): void
    {
        Gallery::factory()->create(['type' => 'private', 'title' => 'Alpha']);
        Gallery::factory()->create(['type' => 'private', 'title' => 'Zoulou']);

        $asc = $this->actingAs($this->admin())
            ->getJson('/api/admin/galleries?sort_by=title&sort_dir=asc')
            ->assertOk()->json('data.0.title');

        $desc = $this->actingAs($this->admin())
            ->getJson('/api/admin/galleries?sort_by=title&sort_dir=desc')
            ->assertOk()->json('data.0.title');

        $this->assertSame('Alpha', $asc);
        $this->assertSame('Zoulou', $desc);
    }

    public function test_an_unauthorised_sort_column_is_ignored(): void
    {
        Gallery::factory()->count(2)->create(['type' => 'private']);

        // Sans allowlist, sort_by atteindrait le SQL : une colonne arbitraire, voire
        // une injection, passerait dans le ORDER BY.
        $this->actingAs($this->admin())
            ->getJson('/api/admin/galleries?sort_by=access_token')
            ->assertOk();

        $this->actingAs($this->admin())
            ->getJson('/api/admin/galleries?sort_by='.urlencode('id; DROP TABLE galleries'))
            ->assertOk();

        $this->assertSame(2, Gallery::count());
    }
}
