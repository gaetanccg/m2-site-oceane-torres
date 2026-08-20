<?php

namespace App\Services\Privacy;

use App\Models\Cart;
use App\Models\Client;
use App\Models\ClientForm;
use App\Models\ContactMessage;
use App\Models\DownloadLog;
use App\Models\Gallery;
use App\Models\GiftCard;
use App\Models\Notification;
use App\Models\PhotoUpload;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Effacement / anonymisation RGPD (droit à l'effacement, art. 17).
 *
 * Applique la carte de traitement :
 *  - CONSERVER : factures / paiements / commandes (obligation comptable ~10 ans) → intacts,
 *    purgés plus tard par la commande privacy:purge-expired ;
 *  - ANONYMISER : users (compte), gift_cards (destinataire), galleries (assigned_email) ;
 *  - SUPPRIMER : clients, réservations, formulaires, paniers, messages contact,
 *    logs de téléchargement, uploads, notifications, sessions/tokens.
 *
 * Chaque opération est gardée : sans ancre pertinente, l'opération est ignorée
 * (jamais de suppression « toute la table »).
 */
class PersonalDataEraser
{
    public function __construct(
        private PersonalDataLocator $locator,
    ) {}

    /**
     * Aperçu (lecture seule) : ce qui sera anonymisé / supprimé / conservé.
     *
     * @return array<string, mixed>
     */
    public function preview(string $type, string $value): array
    {
        $r = $this->locator->locate($type, $value);
        $keys = $r['keys'];
        $galleryIds = $keys['gallery_ids'];
        $userIds = $keys['user_ids'];

        return [
            'query' => $r['query'],
            'to_anonymize' => [
                'users' => count($r['categories']['accounts']),
                'gift_cards' => count($r['categories']['gift_cards']),
                'galleries' => count($r['categories']['galleries']),
            ],
            'to_delete' => [
                'clients' => count($r['categories']['clients']),
                'reservations' => count($r['categories']['reservations']),
                'client_forms' => count($r['categories']['client_forms']),
                'carts' => count($r['categories']['carts']),
                'contact_messages' => count($r['categories']['contact_messages']),
                'download_logs' => $galleryIds ? DownloadLog::whereIn('gallery_id', $galleryIds)->count() : 0,
                'photo_uploads' => $galleryIds ? PhotoUpload::whereIn('gallery_id', $galleryIds)->count() : 0,
                'notifications' => $userIds ? Notification::whereIn('user_id', $userIds)->count() : 0,
            ],
            'retained_legal' => [
                'orders' => $r['summary']['orders'],
                'order_items' => $r['summary']['order_items'],
                'payments' => $r['summary']['payments'],
                'invoices' => $r['summary']['invoices'],
            ],
            // Les fichiers photos des galeries ne sont PAS supprimés automatiquement
            // (galeries potentiellement partagées / scolaires) : seul l'accès est anonymisé.
            'notes' => [
                'gallery_photos' => 'conservées (accès anonymisé) — à traiter manuellement si nécessaire',
            ],
        ];
    }

    /**
     * Exécute l'effacement dans une transaction. Renvoie les compteurs réels.
     *
     * @return array<string, mixed>
     */
    public function erase(string $type, string $value): array
    {
        $r = $this->locator->locate($type, $value);
        $keys = $r['keys'];

        $emails = $keys['emails'];
        $phones = $keys['phones'];
        $userIds = $keys['user_ids'];
        $clientIds = $keys['client_ids'];
        $galleryIds = $keys['gallery_ids'];
        $reservationIds = $keys['reservation_ids'];

        // Fichiers MinIO à supprimer AVANT la transaction DB (les PDF de bons cadeaux
        // contiennent le PII du destinataire). Récupérés maintenant, supprimés après commit.
        $giftCards = $emails
            ? GiftCard::whereIn(DB::raw('lower(recipient_email)'), $emails)->get()
            : collect();

        $summary = DB::transaction(function () use ($emails, $phones, $userIds, $clientIds, $galleryIds, $reservationIds, $giftCards) {
            $out = [];

            // ---------- SUPPRESSION ----------
            $out['contact_messages'] = ($emails || $phones)
                ? ContactMessage::where(function ($q) use ($emails, $phones) {
                    if ($emails) {
                        $q->orWhereIn(DB::raw('lower(email)'), $emails);
                    }
                    if ($phones) {
                        $q->orWhereIn('phone', $phones);
                    }
                })->delete()
                : 0;

            $out['carts'] = ($emails || $userIds)
                ? Cart::where(function ($q) use ($emails, $userIds) {
                    if ($emails) {
                        $q->orWhereIn(DB::raw('lower(guest_email)'), $emails);
                    }
                    if ($userIds) {
                        $q->orWhereIn('user_id', $userIds);
                    }
                })->delete()
                : 0;

            // Réservations (cascade sur client_forms via reservation_id).
            $out['reservations'] = $reservationIds
                ? Reservation::whereIn('id', $reservationIds)->delete()
                : 0;

            // Formulaires client restants (matchés par téléphone, non rattachés).
            $out['client_forms'] = $phones
                ? ClientForm::whereIn('phone', $phones)->delete()
                : 0;

            $out['download_logs'] = $galleryIds
                ? DownloadLog::whereIn('gallery_id', $galleryIds)->delete()
                : 0;

            $out['photo_uploads'] = $galleryIds
                ? PhotoUpload::whereIn('gallery_id', $galleryIds)->delete()
                : 0;

            $out['notifications'] = $userIds
                ? Notification::whereIn('user_id', $userIds)->delete()
                : 0;

            $out['clients'] = $clientIds
                ? Client::whereIn('id', $clientIds)->delete()
                : 0;

            // Infra : sessions / tokens.
            $out['sessions'] = $userIds
                ? DB::table('sessions')->whereIn('user_id', $userIds)->delete()
                : 0;
            $out['personal_access_tokens'] = $userIds
                ? DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)->delete()
                : 0;
            $out['password_reset_tokens'] = $emails
                ? DB::table('password_reset_tokens')->whereIn('email', $emails)->delete()
                : 0;

            // ---------- ANONYMISATION ----------
            // Gift cards : on garde la ligne (lien paiement) mais on efface le destinataire + PDF.
            $out['gift_cards_anonymized'] = $giftCards->isNotEmpty()
                ? GiftCard::whereIn('id', $giftCards->pluck('id'))->update([
                    'recipient_name' => '[anonymisé]',
                    'recipient_email' => null,
                    'message' => null,
                    'pdf_path' => null,
                ])
                : 0;

            // Galeries : on retire l'email assigné (contenu métier conservé).
            $out['galleries_anonymized'] = $galleryIds
                ? Gallery::whereIn('id', $galleryIds)->update(['assigned_email' => null])
                : 0;

            // Users : anonymisation en query builder (bypass l'observer qui re-synchronise le client).
            $out['users_anonymized'] = 0;
            foreach ($userIds as $uid) {
                DB::table('users')->where('id', $uid)->update([
                    'first_name' => '[anonymisé]',
                    'last_name' => '',
                    'email' => 'deleted-'.$uid.'@anonymized.local',
                    'phone' => null,
                    'address_line1' => null,
                    'address_line2' => null,
                    'postal_code' => null,
                    'city' => null,
                ]);
                $out['users_anonymized']++;
            }

            return $out;
        });

        // Suppression des PDF de bons cadeaux sur MinIO (après commit DB).
        $summary['gift_card_pdfs_deleted'] = 0;
        $minio = Storage::disk('minio');
        foreach ($giftCards as $card) {
            if ($card->pdf_path) {
                try {
                    if ($minio->exists($card->pdf_path)) {
                        $minio->delete($card->pdf_path);
                    }
                    $summary['gift_card_pdfs_deleted']++;
                } catch (\Throwable) {
                    // Fichier introuvable / illisible : ignoré.
                }
            }
        }

        // Rappel : commandes / paiements / factures CONSERVÉS (obligation légale).
        $summary['retained_legal'] = [
            'orders' => $r['summary']['orders'],
            'payments' => $r['summary']['payments'],
            'invoices' => $r['summary']['invoices'],
        ];

        return $summary;
    }
}
