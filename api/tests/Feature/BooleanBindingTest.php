<?php

namespace Tests\Feature;

use App\Database\PostgresConnection;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BooleanBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_postgres_connection_override_is_active(): void
    {
        $this->assertInstanceOf(PostgresConnection::class, DB::connection());
    }

    public function test_booleans_are_bound_as_postgres_literals(): void
    {
        $this->assertSame(['true', 'false'], DB::connection()->prepareBindings([true, false]));
    }

    public function test_a_false_boolean_can_be_written_by_the_query_builder(): void
    {
        $photo = Photo::factory()->create();

        DB::table('photos')->where('id', $photo->id)->update(['is_downloadable' => false]);

        $this->assertFalse($photo->fresh()->is_downloadable);
    }

    public function test_a_true_boolean_can_be_written_by_the_query_builder(): void
    {
        $photo = Photo::factory()->create();

        DB::table('photos')->where('id', $photo->id)->update(['is_downloadable' => true]);

        $this->assertTrue($photo->fresh()->is_downloadable);
    }

    public function test_native_where_filters_on_booleans(): void
    {
        Photo::factory()->downloadable()->count(3)->create();
        Photo::factory()->notDownloadable()->count(2)->create();

        $this->assertSame(3, Photo::where('is_downloadable', true)->count());
        $this->assertSame(2, Photo::where('is_downloadable', false)->count());
    }

    public function test_booleans_are_read_back_as_native_php_booleans(): void
    {
        $photo = Photo::factory()->downloadable()->create();

        $this->assertIsBool($photo->fresh()->is_downloadable);
        $this->assertTrue($photo->fresh()->is_downloadable === true);
    }

    public function test_scopes_agree_with_literal_sql(): void
    {
        Photo::factory()->downloadable()->count(4)->create();
        Photo::factory()->notDownloadable()->count(6)->create();

        $sql = DB::selectOne("SELECT count(*) c FROM photos WHERE is_downloadable = 'true'")->c;

        $this->assertSame((int) $sql, Photo::downloadable()->count());
    }

    public function test_a_model_save_round_trips_false(): void
    {
        $gallery = Gallery::factory()->create(['is_published' => true]);

        $gallery->is_published = false;
        $gallery->save();

        $this->assertFalse($gallery->fresh()->is_published);
    }
}
