<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendAccessEmailRequest;
use App\Http\Requests\Admin\SendAccessSmsRequest;
use App\Http\Requests\Admin\StoreGalleryRequest;
use App\Http\Requests\Admin\UpdateGalleryRequest;
use App\Jobs\SendSchoolSessionSmsJob;
use App\Mail\GalleryAccessMail;
use App\Models\Client;
use App\Models\Gallery;
use App\Policies\GalleryPolicy;
use App\Services\MinioStorageService;
use App\Services\SmsTemplateService;
use App\Traits\SyncsProductTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

class GalleryController extends Controller
{
    use SyncsProductTypes;

    public function index(): JsonResponse
    {
        $page = request()->get('page', 1);

        $galleries = Cache::remember("public_galleries_page_{$page}", 300, function () {
            return Gallery::public()
                ->with('photos')
                ->latest()
                ->paginate(12);
        });

        return response()->json($galleries);
    }

    public function show(Gallery $gallery): JsonResponse
    {
        if ($gallery->type === 'private') {
            return response()->json([
                'message' => 'Cette galerie est privée.',
            ], 403);
        }

        $gallery->load('photos');
        $gallery->recordView();

        return response()->json([
            'gallery' => $gallery,
        ]);
    }

    public function showByToken(string $token): JsonResponse
    {
        $gallery = Gallery::where('access_token', $token)->first();

        if (! $gallery || ! $gallery->isAccessible($token)) {
            return response()->json([
                'message' => 'Galerie non trouvée.',
            ], 404);
        }

        $gallery->load(['photos', 'galleryProductTypes.packTiers']);
        $gallery->recordView();

        return response()->json([
            'gallery' => $gallery,
            'available_product_types' => $gallery->getAvailableProductTypes(),
            'pack_pricing' => $gallery->getPackPricing(),
        ]);
    }

    public function myGalleries(Request $request): JsonResponse
    {
        $user = $request->user();

        $galleries = Gallery::where('user_id', $user->id)
            ->orWhere('assigned_email', $user->email)
            ->with('photos')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'galleries' => $galleries,
        ]);
    }

    public function store(StoreGalleryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $productTypes = $validated['product_types'] ?? null;
        unset($validated['product_types']);

        $validated['type'] = 'private';
        $validated['access_token'] = Str::random(64);
        $validated['share_code'] = Gallery::generateUniqueShareCode();

        if (! empty($validated['client_id'])) {
            $client = Client::find($validated['client_id']);
            $validated['user_id'] = $client?->user_id;
            $validated['assigned_email'] = null;
        }
        unset($validated['client_id']);

        $gallery = Gallery::create($validated);

        if ($productTypes !== null) {
            $this->syncProductTypes($gallery, $productTypes);
        }

        return response()->json([
            'success' => true,
            'data' => $gallery->load('galleryProductTypes.packTiers'),
            'message' => 'Galerie créée avec succès.',
        ], 201);
    }

    public function update(UpdateGalleryRequest $request, Gallery $gallery): JsonResponse
    {
        $validated = $request->validated();

        $productTypes = $validated['product_types'] ?? null;
        unset($validated['product_types']);

        if (array_key_exists('client_id', $validated)) {
            if (! empty($validated['client_id'])) {
                $client = Client::find($validated['client_id']);
                $validated['user_id'] = $client?->user_id;
                $validated['assigned_email'] = null;
            } else {
                $validated['user_id'] = null;
            }
            unset($validated['client_id']);
        }

        if (! empty($validated['assigned_email'])) {
            $validated['user_id'] = null;
        }

        $gallery->update($validated);

        if ($productTypes !== null) {
            $this->syncProductTypes($gallery, $productTypes);
        }

        return response()->json([
            'success' => true,
            'data' => $gallery->fresh()->load('galleryProductTypes.packTiers'),
            'message' => 'Galerie mise à jour avec succès.',
        ]);
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $galleryId = $gallery->id;

        // Suppression DB d'abord (rapide, < 100 ms). Le cleanup MinIO peut être lent
        // (5-30 s pour une grosse galerie via Cloudflare tunnel) — on le défère après
        // l'envoi de la réponse pour éviter le timeout côté client.
        $gallery->delete();

        app()->terminating(function () use ($galleryId) {
            try {
                app(MinioStorageService::class)->deleteGalleryFolder($galleryId);
            } catch (\Exception $e) {
                \Log::warning('Failed to cleanup gallery files from storage', [
                    'gallery_id' => $galleryId,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Galerie supprimée avec succès.',
        ]);
    }

    public function sendAccessEmail(SendAccessEmailRequest $request, Gallery $gallery): JsonResponse
    {
        $validated = $request->validated();

        if (! $gallery->share_code) {
            return response()->json([
                'success' => false,
                'message' => 'Cette galerie n\'a pas de code de partage.',
            ], 400);
        }

        $galleryUrl = config('app.frontend_url', 'https://oceanetorresphotographie.fr').'/gallery';

        try {
            Mail::to($validated['email'])->send(
                new GalleryAccessMail(
                    gallery: $gallery,
                    recipientName: $validated['recipient_name'],
                    galleryUrl: $galleryUrl,
                    shareCode: $gallery->share_code,
                )
            );

            return response()->json([
                'success' => true,
                'message' => 'Email envoyé avec succès à '.$validated['email'],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to send gallery access email', [
                'gallery_id' => $gallery->id,
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "L'envoi de l'email a échoué. Veuillez réessayer plus tard.",
            ], 500);
        }
    }

    public function sendAccessSms(SendAccessSmsRequest $request, Gallery $gallery, SmsTemplateService $smsTemplate): JsonResponse
    {
        $validated = $request->validated();

        if (! $gallery->share_code) {
            return response()->json([
                'success' => false,
                'message' => 'Cette galerie n\'a pas de code de partage.',
            ], 400);
        }

        $frontendUrl = config('app.frontend_url', 'https://oceanetorresphotographie.fr');
        $directUrl = $frontendUrl.'/gallery/'.$gallery->share_code;

        // School session galleries inherit the session template; standalone galleries use their own.
        $gallery->loadMissing('schoolSession:id,sms_template');
        $template = $gallery->schoolSession?->sms_template ?? $gallery->sms_template;

        $content = $smsTemplate->build(
            $template,
            $validated['recipient_name'],
            $directUrl,
            $gallery->share_code,
        );

        SendSchoolSessionSmsJob::dispatch($validated['phone'], $content);

        return response()->json([
            'success' => true,
            'message' => 'SMS mis en file d\'envoi pour '.$validated['phone'],
        ]);
    }

    public function regenerateToken(Gallery $gallery): JsonResponse
    {
        $gallery->update([
            'access_token' => Str::random(64),
        ]);

        return response()->json([
            'gallery' => $gallery->fresh(),
            'message' => 'Token régénéré avec succès.',
        ]);
    }

    public function showByShareCode(string $code): JsonResponse
    {
        $gallery = Gallery::byShareCode($code)->first();

        if (! $gallery) {
            return response()->json([
                'message' => 'Code invalide.',
            ], 404);
        }

        $gallery->recordView();

        $gallery->load([
            'photos' => function ($query) {
                $query->ordered();
            },
            'galleryProductTypes',
            'schoolSession:id,gallery_message,closed_at',
        ]);

        return response()->json([
            'gallery' => $gallery,
            'mode' => 'protected',
            'available_product_types' => $gallery->getAvailableProductTypes(),
            'pack_pricing' => $gallery->getPackPricing(),
            'school_message' => $gallery->schoolSession?->gallery_message,
            'school_closed' => (bool) $gallery->schoolSession?->isClosed(),
        ]);
    }

    public function showDownloadableByToken(string $token): JsonResponse
    {
        $gallery = Gallery::byAccessToken($token)->first();

        if (! $gallery) {
            return response()->json([
                'message' => 'Lien invalide.',
            ], 404);
        }

        $gallery->recordView();

        $gallery->load([
            'photos' => function ($query) {
                $query->downloadable()->ordered();
            },
        ]);

        return response()->json([
            'gallery' => $gallery,
            'mode' => 'download',
        ]);
    }

    public function regenerateShareCode(Gallery $gallery): JsonResponse
    {
        $newCode = $gallery->regenerateShareCode();

        return response()->json([
            'gallery' => $gallery->fresh(),
            'share_code' => $newCode,
            'message' => 'Code de partage régénéré avec succès.',
        ]);
    }

    /**
     * Stream a ZIP of every downloadable photo directly to the browser.
     *
     * The archive is streamed on the fly (bounded memory, first bytes sent
     * immediately) instead of being built entirely in memory — the previous
     * addFromString() approach exhausted PHP's memory_limit on large galleries,
     * killing the worker before HandleCors could attach headers (surfacing as a
     * CORS error client-side). The frontend downloads this via a direct
     * navigation, so the request is not an XHR and CORS never applies.
     */
    public function downloadZip(Gallery $gallery, Request $request): StreamedResponse
    {
        $token = $request->query('token');
        $user = $request->user();

        abort_unless(
            app(GalleryPolicy::class)->download($user, $gallery, $token),
            403,
            'Accès non autorisé.'
        );

        $photos = $gallery->photos()->downloadable()->limit(500)->get();

        abort_if($photos->isEmpty(), 404, 'Aucune photo téléchargeable.');

        $zipName = 'galerie_'.(Str::slug($gallery->title) ?: 'photos').'.zip';
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        return response()->streamDownload(function () use ($photos, $ip, $userAgent) {
            $zip = new ZipStream(
                sendHttpHeaders: false,
                defaultCompressionMethod: CompressionMethod::STORE,
            );

            $usedNames = [];

            foreach ($photos as $index => $photo) {
                $stream = Storage::disk('minio')->readStream($photo->resolved_storage_path);
                if ($stream === null) {
                    continue;
                }

                $extension = pathinfo($photo->file_path, PATHINFO_EXTENSION) ?: 'jpg';
                $base = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $photo->title ?: 'photo_'.($index + 1));
                $entryName = $base.'.'.$extension;

                // Ensure unique entry names inside the archive
                $suffix = 1;
                while (isset($usedNames[$entryName])) {
                    $entryName = $base.'_'.$suffix.'.'.$extension;
                    $suffix++;
                }
                $usedNames[$entryName] = true;

                $zip->addFileFromStream($entryName, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $photo->recordDownload($ip, $userAgent);
            }

            $zip->finish();
        }, $zipName, [
            'Content-Type' => 'application/zip',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function downloadFile(Gallery $gallery, Request $request)
    {
        $filename = $request->query('file');
        $filePath = storage_path('app/temp/'.$filename);

        if (! file_exists($filePath)) {
            return response()->json([
                'message' => 'Fichier non trouvé.',
            ], 404);
        }

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function adminIndex(): JsonResponse
    {
        $galleries = Gallery::with(['user:id,first_name,last_name,email', 'galleryProductTypes.packTiers'])
            ->where('type', '!=', 'event')
            ->whereNull('school_session_id')
            ->withCount([
                'photos',
                'photos as downloadable_count' => function ($query) {
                    $query->whereRaw('is_downloadable = true');
                },
                'photos as liked_photos_count' => function ($query) {
                    $query->whereRaw('is_liked = true');
                },
                'photos as downloaded_photos_count' => function ($query) {
                    $query->where('downloads_count', '>', 0);
                },
            ])
            ->withSum('photos', 'downloads_count')
            ->latest()
            ->paginate(20);

        // Batch-load client_ids for all galleries with a user_id (avoids N+1)
        $userIds = $galleries->getCollection()->pluck('user_id')->filter()->unique()->values();
        $clientMap = $userIds->isNotEmpty()
            ? Client::whereIn('user_id', $userIds)->pluck('id', 'user_id')
            : collect();

        $galleries->getCollection()->transform(function ($gallery) use ($clientMap) {
            $gallery->total_downloads_count = $gallery->photos_sum_downloads_count ?? 0;
            // Compute download_status from withCount data (avoids N+1)
            $downloadable = $gallery->downloadable_count ?? 0;
            $downloaded = $gallery->downloaded_photos_count ?? 0;
            $gallery->download_status = $downloadable === 0 || $downloaded === 0
                ? 'none'
                : ($downloaded >= $downloadable ? 'complete' : 'partial');
            // Resolve client_id from batch-loaded map (avoids N+1)
            $gallery->client_id = $gallery->user_id ? ($clientMap[$gallery->user_id] ?? null) : null;
            unset($gallery->photos_sum_downloads_count);

            return $gallery;
        });

        return response()->json($galleries);
    }

    public function adminShow(Gallery $gallery): JsonResponse
    {
        $gallery->load([
            'photos' => function ($query) {
                $query->ordered();
            },
            'user',
            'galleryProductTypes.packTiers',
        ]);

        $gallery->downloadable_count = $gallery->photos->where('is_downloadable', true)->count();
        $gallery->liked_photos_count = $gallery->photos->where('is_liked', true)->count();
        $gallery->total_downloads_count = $gallery->photos->sum('downloads_count');
        $downloadedCount = $gallery->photos->where('downloads_count', '>', 0)->where('is_downloadable', true)->count();
        $gallery->downloaded_photos_count = $gallery->photos->where('downloads_count', '>', 0)->count();
        $gallery->download_status = $gallery->downloadable_count === 0 || $downloadedCount === 0
            ? 'none'
            : ($downloadedCount >= $gallery->downloadable_count ? 'complete' : 'partial');
        $gallery->client_id = $gallery->user_id
            ? Client::where('user_id', $gallery->user_id)->value('id')
            : null;

        return response()->json([
            'success' => true,
            'data' => $gallery,
        ]);
    }
}
