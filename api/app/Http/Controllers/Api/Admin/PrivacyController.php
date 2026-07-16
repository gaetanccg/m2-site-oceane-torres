<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Privacy\ErasePrivacyRequest;
use App\Http\Requests\Admin\Privacy\SearchPrivacyRequest;
use App\Jobs\GeneratePrivacyExportJob;
use App\Models\PrivacyAuditLog;
use App\Models\PrivacyExport;
use App\Services\Privacy\PersonalDataEraser;
use App\Services\Privacy\PersonalDataLocator;
use App\Services\Privacy\PrivacyAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gestion RGPD depuis l'admin : recherche/activité d'une personne (Lot 1)
 * et consultation du journal d'audit (Lot 0). Export et effacement viendront
 * enrichir ce contrôleur (Lots 2 & 3).
 */
class PrivacyController extends Controller
{
    public function __construct(
        private PersonalDataLocator $locator,
        private PrivacyAuditLogger $audit,
    ) {}

    /**
     * Recherche « personne concernée » par email / téléphone / n° de commande.
     * Retourne l'activité agrégée (lecture seule) et trace la consultation.
     */
    public function search(SearchPrivacyRequest $request): JsonResponse
    {
        $type = $request->input('type');
        $value = $request->input('value');

        $result = $this->locator->locate($type, $value);

        $this->audit->record('search', $type, $value, $result['summary'], $request);

        return response()->json(array_merge(['success' => true], $result));
    }

    /**
     * Lance un export global (toutes les données + PDF factures) en asynchrone.
     */
    public function exportAll(Request $request): JsonResponse
    {
        $export = PrivacyExport::create([
            'type' => 'global',
            'status' => 'pending',
            'requested_by' => $request->user()?->id,
        ]);

        GeneratePrivacyExportJob::dispatch($export->id);

        $this->audit->record('export', null, 'global', ['export_id' => $export->id], $request);

        return response()->json([
            'success' => true,
            'export' => $this->formatExport($export),
        ]);
    }

    /**
     * Lance un export ciblé : toutes les données d'UNE personne (email/tél/commande).
     */
    public function exportSubject(SearchPrivacyRequest $request): JsonResponse
    {
        $type = $request->input('type');
        $value = $request->input('value');

        $export = PrivacyExport::create([
            'type' => 'subject',
            'subject_type' => $type,
            'subject_value' => $value,
            'status' => 'pending',
            'requested_by' => $request->user()?->id,
        ]);

        GeneratePrivacyExportJob::dispatch($export->id);

        $this->audit->record('export', $type, $value, ['export_id' => $export->id], $request);

        return response()->json([
            'success' => true,
            'export' => $this->formatExport($export),
        ]);
    }

    /**
     * Statut d'un export (pour le polling front).
     */
    public function exportStatus(PrivacyExport $export): JsonResponse
    {
        return response()->json([
            'success' => true,
            'export' => $this->formatExport($export),
        ]);
    }

    /**
     * Téléchargement du ZIP généré.
     */
    public function downloadExport(PrivacyExport $export): BinaryFileResponse|JsonResponse
    {
        if ($export->status !== 'completed' || ! $export->file_path) {
            return response()->json(['success' => false, 'message' => 'Export non disponible.'], 404);
        }

        if (! Storage::disk('local')->exists($export->file_path)) {
            $export->markAs('failed', "Le fichier ZIP n'est plus disponible. Relancez l'export.");

            return response()->json([
                'success' => false,
                'message' => "Le fichier ZIP n'est plus disponible.",
                'requires_regenerate' => true,
            ], 410);
        }

        return response()->download(
            Storage::disk('local')->path($export->file_path),
            'export-rgpd-'.$export->created_at->format('Y-m-d').'.zip',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatExport(PrivacyExport $export): array
    {
        return [
            'id' => $export->id,
            'type' => $export->type,
            'status' => $export->status,
            'total_items' => $export->total_items,
            'processed_items' => $export->processed_items,
            'file_size_bytes' => $export->file_size_bytes,
            'error_message' => $export->error_message,
            'created_at' => $export->created_at?->toIso8601String(),
        ];
    }

    /**
     * Aperçu d'effacement : ce qui sera anonymisé / supprimé / conservé.
     */
    public function erasurePreview(SearchPrivacyRequest $request, PersonalDataEraser $eraser): JsonResponse
    {
        $data = $eraser->preview($request->input('type'), $request->input('value'));

        return response()->json(array_merge(['success' => true], $data));
    }

    /**
     * Exécute l'effacement / anonymisation (confirmation tapée requise) et l'audite.
     */
    public function erase(ErasePrivacyRequest $request, PersonalDataEraser $eraser): JsonResponse
    {
        $type = $request->input('type');
        $value = $request->input('value');

        $result = $eraser->erase($type, $value);

        $this->audit->record('erasure', $type, $value, $result, $request);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    /**
     * Journal d'audit RGPD (lecture seule, paginé).
     */
    public function audit(Request $request): JsonResponse
    {
        $logs = PrivacyAuditLog::with('actor')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'logs' => $logs->map(fn (PrivacyAuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_value' => $log->subject_value,
                'affected' => $log->affected,
                'ip_address' => $log->ip_address,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => trim($log->actor->first_name.' '.$log->actor->last_name),
                    'email' => $log->actor->email,
                ] : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
