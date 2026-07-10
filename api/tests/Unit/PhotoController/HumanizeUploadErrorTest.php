<?php

namespace Tests\Unit\PhotoController;

use App\Http\Controllers\Api\PhotoController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test pur (sans DB) du mapping message technique → message actionnable UI.
 * La méthode est privée : on l'invoque par réflexion.
 */
class HumanizeUploadErrorTest extends TestCase
{
    private function humanize(string $message): string
    {
        $method = new ReflectionMethod(PhotoController::class, 'humanizeUploadError');
        $method->setAccessible(true);

        return $method->invoke(new PhotoController, new \RuntimeException($message));
    }

    public function test_memory_error(): void
    {
        $this->assertStringContainsString('trop volumineuse', $this->humanize('Allowed memory size exhausted'));
    }

    public function test_storage_error(): void
    {
        $this->assertStringContainsString('Stockage temporairement indisponible', $this->humanize('minio connection refused'));
        $this->assertStringContainsString('Stockage temporairement indisponible', $this->humanize('curl error 7'));
    }

    public function test_mime_error(): void
    {
        $this->assertStringContainsString('Format non supporté', $this->humanize('invalid mime type'));
    }

    public function test_corrupt_image_error(): void
    {
        $this->assertStringContainsString('corrompue', $this->humanize('unable to decode image'));
    }

    public function test_gallery_error(): void
    {
        $this->assertStringContainsString('Galerie introuvable', $this->humanize('Galerie non trouvée'));
    }

    public function test_permission_error(): void
    {
        $this->assertStringContainsString('Accès refusé', $this->humanize('permission denied'));
    }

    public function test_default_fallback(): void
    {
        $this->assertStringContainsString('Réessayer', $this->humanize('some unexpected boom'));
    }
}
