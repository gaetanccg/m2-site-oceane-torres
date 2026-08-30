<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendAccessEmailRequest;
use App\Http\Requests\Admin\SendAccessSmsRequest;
use App\Http\Requests\Admin\StoreGalleryRequest;
use App\Http\Requests\Admin\UpdateGalleryRequest;
use App\Jobs\SendSmsJob;
use App\Mail\GalleryAccessMail;
use App\Models\Client;
use App\Models\ClientGallery;
use App\Models\Gallery;
use App\Models\OrderItem;
use App\Services\MinioStorageService;
use App\Services\SmsTemplateService;
use App\Traits\SyncsProductTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Les galeries, cote administration.
 *
 * ⚠️ Le parametre reste `Gallery`, PAS `ClientGallery`, et c'est mesure : le gestionnaire de
 * photos (`PhotosManager.vue`) est monte aussi bien depuis l'ecran des galeries clientes que
 * depuis le detail d'une session SCOLAIRE, et il appelle `GET /admin/galleries/{id}`.
 * Restreindre la nature ici rendrait 404 sur toutes les galeries d'eleves.
 *
 * `index()` en revanche se restreint : ses deux filtres — `type != 'event'` et
 * `school_session_id IS NULL` — decrivaient exactement la nature cliente.
 *
 * Le pendant public — acces par code de partage, par jeton, et le ZIP — est dans
 * `Api\GalleryController`. Ce sont des chemins d'acces par CREDENTIAL : ils n'ont rien a
 * faire dans le meme fichier que les ecritures d'administration.
 */
class GalleryController extends Controller
{
    use SyncsProductTypes;

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
            'data' => $gallery->load('galleryProductTypes.packTiers')->withPrivateFields(),
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
            'data' => $gallery->fresh()->load('galleryProductTypes.packTiers')->withPrivateFields(),
            'message' => 'Galerie mise à jour avec succès.',
        ]);
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $vendues = OrderItem::whereIn('photo_id', $gallery->photos()->select('id'))->count();

        if ($vendues > 0) {
            throw new BusinessException(
                "Cette galerie contient {$vendues} photo(s) achetee(s) : la supprimer priverait "
                .'les clients de leur telechargement. Supprimer les photos non vendues une a une.',
                409
            );
        }

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

        SendSmsJob::dispatch($validated['phone'], $content);

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
            'gallery' => $gallery->fresh()->withPrivateFields(),
            'message' => 'Token régénéré avec succès.',
        ]);
    }

    public function regenerateShareCode(Gallery $gallery): JsonResponse
    {
        $newCode = $gallery->regenerateShareCode();

        return response()->json([
            'gallery' => $gallery->fresh()->withPrivateFields(),
            'share_code' => $newCode,
            'message' => 'Code de partage régénéré avec succès.',
        ]);
    }

    public function index(): JsonResponse
    {
        // `ClientGallery` porte exactement le perimetre que ces deux filtres decrivaient —
        // verifie : meme liste d'identifiants, aux memes 2 galeries pres.
        $galleries = ClientGallery::with(['user:id,first_name,last_name,email', 'galleryProductTypes.packTiers'])
            ->withCount([
                'photos',
                'photos as downloadable_count' => function ($query) {
                    $query->where('is_downloadable', true);
                },
                'photos as liked_photos_count' => function ($query) {
                    $query->where('is_liked', true);
                },
                'photos as downloaded_photos_count' => function ($query) {
                    $query->where('downloads_count', '>', 0);
                },
            ])
            ->withSum('photos', 'downloads_count');

        $galleries = $this->paginated(
            $this->sorted($galleries, request(), ['created_at', 'title', 'event_date']),
            request()
        );

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

            return $gallery->withPrivateFields();
        });

        return response()->json($galleries);
    }

    public function show(Gallery $gallery): JsonResponse
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
            'data' => $gallery->withPrivateFields(),
        ]);
    }
}
