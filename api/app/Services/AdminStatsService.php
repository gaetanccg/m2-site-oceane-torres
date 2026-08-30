<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\GiftCard;
use App\Models\GiftCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

/**
 * Source unique des chiffres de synthèse de l'administration.
 *
 * Deux règles tiennent tout le fichier :
 *
 * 1. Chaque compteur est calculé en base, sur l'ensemble des lignes — jamais sur la page
 *    affichée. Un « encours de bons cadeaux » déduit de vingt lignes est un engagement
 *    financier annoncé faux.
 * 2. Chaque périmètre reprend à l'identique celui de la liste que le compteur annonce.
 *    Un chiffre qui contredit la page vers laquelle il renvoie est pire qu'aucun chiffre.
 */
class AdminStatsService
{
    public function dashboard(): array
    {
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'reservations' => $this->reservations($thisMonth, $lastMonth),
            'clients' => $this->clients($thisMonth),
            'revenue' => $this->revenue($thisMonth, $lastMonth),
            'orders' => $this->orders(),
            'galleries' => $this->galleries(),
            'gift_cards' => $this->giftCards(),
            'promo_codes' => $this->promoCodes(),
        ];
    }

    public function reservations(Carbon $thisMonth, Carbon $lastMonth): array
    {
        $row = Reservation::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'pending') as pending")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'confirmed') as confirmed")
            ->selectRaw('COUNT(*) FILTER (WHERE created_at >= ?) as this_month', [$thisMonth])
            ->selectRaw('COUNT(*) FILTER (WHERE created_at >= ? AND created_at < ?) as last_month', [$lastMonth, $thisMonth])
            ->first();

        return [
            'total' => (int) $row->total,
            'pending' => (int) $row->pending,
            'confirmed' => (int) $row->confirmed,
            'this_month' => (int) $row->this_month,
            'last_month' => (int) $row->last_month,
        ];
    }

    /** La table `clients` (le CRM), pas les comptes : c'est ce que liste /admin/clients. */
    public function clients(Carbon $thisMonth): array
    {
        $row = Client::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(*) FILTER (WHERE created_at >= ?) as new_this_month', [$thisMonth])
            ->first();

        return [
            'total' => (int) $row->total,
            'new_this_month' => (int) $row->new_this_month,
        ];
    }

    /**
     * Le chiffre d'affaires se compte sur `paid_at`, la date d'encaissement, et non sur la
     * date de création : un paiement engagé le 31 et abouti le 1er appartient au mois où
     * l'argent est entré. Source `orders` — le même montant que la facture émise.
     *
     * Contrairement aux compteurs de commandes, les ventes scolaires y sont INCLUSES : ce
     * sont des encaissements réels, et les exclure sous-estimerait le chiffre d'affaires.
     * Ce total peut donc dépasser la somme des commandes visibles dans /admin/orders.
     */
    public function revenue(Carbon $thisMonth, Carbon $lastMonth): array
    {
        $row = Order::query()
            ->paid()
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->selectRaw('COALESCE(SUM(total) FILTER (WHERE paid_at >= ?), 0) as this_month', [$thisMonth])
            ->selectRaw('COALESCE(SUM(total) FILTER (WHERE paid_at >= ? AND paid_at < ?), 0) as last_month', [$lastMonth, $thisMonth])
            ->first();

        return [
            'total' => (float) $row->total,
            'this_month' => (float) $row->this_month,
            'last_month' => (float) $row->last_month,
        ];
    }

    /**
     * Même périmètre que /admin/orders, qui masque les commandes entièrement scolaires :
     * elles se gèrent depuis les écrans de session. Un total qui les inclurait dépasserait
     * de façon inexplicable la liste que la carte ouvre.
     */
    /**
     * Les chiffres de l'ecran Commandes, agreges en base.
     *
     * `notEntirelySchool()` reprend EXACTEMENT le perimetre de la liste
     * (`Admin\OrderController::index`) : un compteur qui ne compte pas ce que la liste
     * montre est un compteur faux, et c'est tout l'objet de #63/#64.
     *
     * `to_ship` reproduit en SQL la branche `to_ship` de `Order::getDetailedStatusAttribute()`
     * — payee, au moins un tirage, pas encore expediee. La liste des types imprimes vient du
     * modele et n'est pas recopiee : la dupliquer ici la ferait diverger au premier ajout.
     */
    public function orders(): array
    {
        $typesImprimes = collect(CartItem::PRODUCT_TYPES)
            ->filter(fn (array $type) => $type['is_print'] ?? false)
            ->keys()
            ->all();

        $aUnTirage = OrderItem::query()
            ->whereColumn('order_items.order_id', 'orders.id')
            ->whereIn('product_type', $typesImprimes);

        $row = Order::query()
            ->notEntirelySchool()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'paid') as paid")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'pending') as pending")
            ->selectRaw("COALESCE(SUM(total) FILTER (WHERE status = 'paid'), 0) as revenue")
            ->selectRaw(
                "COUNT(*) FILTER (WHERE status = 'paid'"
                ." AND (print_status IS DISTINCT FROM 'shipped')"
                .' AND EXISTS ('.$aUnTirage->toBase()->selectRaw('1')->toSql().')) as to_ship',
                $aUnTirage->getBindings()
            )
            ->first();

        return [
            'total' => (int) $row->total,
            'paid' => (int) $row->paid,
            'pending' => (int) $row->pending,
            'to_ship' => (int) $row->to_ship,
            'revenue' => (float) $row->revenue,
        ];
    }

    /** Périmètres repris de GalleryController::adminIndex et EventGalleryController::adminIndex. */
    public function galleries(): array
    {
        return [
            'clients' => Gallery::where('type', '!=', 'event')->whereNull('school_session_id')->count(),
            'events' => Gallery::where('type', 'event')->topLevel()->count(),
        ];
    }

    /**
     * `outstanding` est un engagement financier : la somme des soldes encore utilisables.
     * Un bon expiré n'engage plus rien et n'y entre pas — et l'expiration se lit sur
     * `expires_at`, jamais sur `status`, que le code ne met qu'à 'active' ou 'used'.
     */
    public function giftCards(): array
    {
        $row = GiftCard::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'active' AND (expires_at IS NULL OR expires_at > now())) as active")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'used') as used")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'cancelled') as cancelled")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= now()) as expired")
            ->selectRaw("COALESCE(SUM(remaining_amount) FILTER (WHERE status = 'active' AND (expires_at IS NULL OR expires_at > now())), 0) as outstanding")
            ->first();

        return [
            'total' => (int) $row->total,
            'active' => (int) $row->active,
            'used' => (int) $row->used,
            'cancelled' => (int) $row->cancelled,
            'expired' => (int) $row->expired,
            'outstanding' => (float) $row->outstanding,
        ];
    }

    /**
     * Les états d'un code promo dérivent des dates et du quota consommé, pas d'une colonne.
     * La règle est celle de GiftCodeController::computeStatus, quota inclus : seules les
     * commandes payées le consomment.
     */
    public function promoCodes(): array
    {
        $paidUses = "(SELECT COUNT(*) FROM orders WHERE orders.gift_code_id = gift_codes.id AND orders.status = 'paid')";

        $row = GiftCode::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(*) FILTER (WHERE NOT is_active) as inactive')
            ->selectRaw('COUNT(*) FILTER (WHERE is_active AND valid_from IS NOT NULL AND valid_from > now()) as scheduled')
            ->selectRaw('COUNT(*) FILTER (WHERE is_active AND (valid_from IS NULL OR valid_from <= now()) AND valid_until IS NOT NULL AND valid_until <= now()) as expired')
            ->selectRaw("COUNT(*) FILTER (
                WHERE is_active
                  AND (valid_from IS NULL OR valid_from <= now())
                  AND (valid_until IS NULL OR valid_until > now())
                  AND max_uses IS NOT NULL
                  AND {$paidUses} >= max_uses
            ) as used_up")
            ->selectRaw("COALESCE(SUM({$paidUses}), 0) as paid_uses")
            ->first();

        $active = (int) $row->total - (int) $row->inactive - (int) $row->scheduled
            - (int) $row->expired - (int) $row->used_up;

        return [
            'total' => (int) $row->total,
            'active' => $active,
            'inactive' => (int) $row->inactive,
            'scheduled' => (int) $row->scheduled,
            'expired' => (int) $row->expired,
            'used_up' => (int) $row->used_up,
            'paid_uses' => (int) $row->paid_uses,
        ];
    }
}
