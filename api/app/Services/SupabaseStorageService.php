<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    private string $supabaseUrl;
    private string $serviceKey;
    private string $bucket = 'galleries';

    public function __construct()
    {
        $this->supabaseUrl = config('services.supabase.url') ?? env('SUPABASE_URL');
        $this->serviceKey = config('services.supabase.service_key') ?? env('SUPABASE_SERVICE_KEY');
    }

    public function uploadPhoto(UploadedFile $file, string $galleryId): ?array
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = "{$galleryId}/{$filename}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceKey}",
            'Content-Type' => $file->getMimeType(),
        ])->withBody(
            file_get_contents($file->getRealPath()),
            $file->getMimeType()
        )->post("{$this->supabaseUrl}/storage/v1/object/{$this->bucket}/{$path}");

        if ($response->successful()) {
            return [
                'path' => $path,
                'url' => $this->getPublicUrl($path),
                'filename' => $filename,
            ];
        }

        return null;
    }

    public function deletePhoto(string $path): bool
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->delete("{$this->supabaseUrl}/storage/v1/object/{$this->bucket}/{$path}");

        return $response->successful();
    }

    public function deleteGalleryFolder(string $galleryId): bool
    {
        $files = $this->listFiles($galleryId);

        if (empty($files)) {
            return true;
        }

        $paths = array_map(fn($file) => $file['name'], $files);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->delete("{$this->supabaseUrl}/storage/v1/object/{$this->bucket}", [
            'prefixes' => $paths,
        ]);

        return $response->successful();
    }

    public function listFiles(string $galleryId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->post("{$this->supabaseUrl}/storage/v1/object/list/{$this->bucket}", [
            'prefix' => $galleryId . '/',
        ]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        return [];
    }

    public function getPublicUrl(string $path): string
    {
        return "{$this->supabaseUrl}/storage/v1/object/public/{$this->bucket}/{$path}";
    }

    public function getSignedUrl(string $path, int $expiresIn = 3600): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->post("{$this->supabaseUrl}/storage/v1/object/sign/{$this->bucket}/{$path}", [
            'expiresIn' => $expiresIn,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return "{$this->supabaseUrl}/storage/v1{$data['signedURL']}";
        }

        return null;
    }

    public function downloadPhoto(string $path): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceKey}",
        ])->get("{$this->supabaseUrl}/storage/v1/object/{$this->bucket}/{$path}");

        if ($response->successful()) {
            return $response->body();
        }

        return null;
    }
}
