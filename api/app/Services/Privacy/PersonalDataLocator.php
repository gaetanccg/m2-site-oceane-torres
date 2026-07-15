<?php

namespace App\Services\Privacy;

use App\Models\Cart;
use App\Models\Client;
use App\Models\ClientForm;
use App\Models\ContactMessage;
use App\Models\DownloadLog;
use App\Models\Gallery;
use App\Models\GiftCard;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Résolveur central « personne concernée » (RGPD).
 *
 * À partir d'une clé (email | phone | order_number), retrouve TOUTES les lignes
 * rattachées à travers le schéma. Réutilisé par l'écran d'activité (lecture seule),
 * l'export et l'effacement. Ne modifie jamais rien.
 */
class PersonalDataLocator
{
    /** @var Collection<int, string> emails (minuscules) rattachés */
    private Collection $emails;

    /** @var Collection<int, string> téléphones rattachés */
    private Collection $phones;

    /** @var Collection<int, string> ids users rattachés */
    private Collection $userIds;

    /** @var Collection<int, string> ids clients rattachés */
    private Collection $clientIds;

    /** @var Collection<int, string> ids orders rattachés */
    private Collection $orderIds;

    /** @var Collection<int, string> ids galleries rattachées */
    private Collection $galleryIds;

    /** @var Collection<int, string> ids reservations rattachées */
    private Collection $reservationIds;

    /**
     * @return array{query: array, summary: array<string,int>, categories: array<string,mixed>}
     */
    public function locate(string $type, string $value): array
    {
        $value = trim($value);
        $this->emails = collect();
        $this->phones = collect();
        $this->userIds = collect();
        $this->clientIds = collect();
        $this->orderIds = collect();
        $this->galleryIds = collect();
        $this->reservationIds = collect();

        // 1. Graines à partir de la clé d'entrée.
        match ($type) {
            'email' => $this->emails->push(mb_strtolower($value)),
            'phone' => $this->phones->push($value),
            'order_number' => $this->seedFromOrderNumber($value),
            default => null,
        };

        // 2. Résolution des entités « ancres » (élargit les ensembles).
        $users = $this->resolveUsers();
        $clients = $this->resolveClients();
        $orders = $this->resolveOrders();
        $reservations = $this->resolveReservations();
        $galleries = $this->resolveGalleries();

        // 3. Catégories dérivées.
        $carts = $this->resolveCarts();
        $contacts = $this->resolveContactMessages();
        $giftCards = $this->resolveGiftCards();
        $clientForms = $this->resolveClientForms();
        $downloadLogs = $this->downloadLogsQuery()->count();
        $orderItems = $this->orderIds->isNotEmpty() ? OrderItem::whereIn('order_id', $this->orderIds)->count() : 0;
        $payments = $this->resolvePayments();
        $invoices = $this->orderIds->isNotEmpty() ? Invoice::whereIn('order_id', $this->orderIds)->count() : 0;

        $categories = [
            'accounts' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => trim($u->first_name.' '.$u->last_name),
                'email' => $u->email,
                'phone' => $u->phone,
                'city' => $u->city,
                'created_at' => $u->created_at?->toIso8601String(),
            ])->values(),
            'clients' => $clients->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'gdpr_consent' => $c->gdpr_consent,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values(),
            'orders' => $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'total' => $o->total,
                'guest_email' => $o->guest_email,
                'created_at' => $o->created_at?->toIso8601String(),
                'retained' => true, // conservé au titre de l'obligation comptable
            ])->values(),
            'reservations' => $reservations->map(fn (Reservation $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'guest_name' => $r->guest_name,
                'guest_email' => $r->guest_email,
                'guest_phone' => $r->guest_phone,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'carts' => $carts->map(fn (Cart $c) => [
                'id' => $c->id,
                'guest_email' => $c->guest_email,
                'status' => $c->status,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values(),
            'contact_messages' => $contacts->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'phone' => $m->phone,
                'subject' => $m->subject,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),
            'galleries' => $galleries->map(fn (Gallery $g) => [
                'id' => $g->id,
                'title' => $g->title,
                'assigned_email' => $g->assigned_email,
                'created_at' => $g->created_at?->toIso8601String(),
            ])->values(),
            'gift_cards' => $giftCards->map(fn (GiftCard $g) => [
                'id' => $g->id,
                'code' => $g->code,
                'recipient_name' => $g->recipient_name,
                'recipient_email' => $g->recipient_email,
                'created_at' => $g->created_at?->toIso8601String(),
            ])->values(),
            'client_forms' => $clientForms->map(fn (ClientForm $f) => [
                'id' => $f->id,
                'fullname' => $f->fullname,
                'phone' => $f->phone,
                'created_at' => $f->created_at?->toIso8601String(),
            ])->values(),
        ];

        $summary = [
            'accounts' => $users->count(),
            'clients' => $clients->count(),
            'orders' => $orders->count(),
            'order_items' => $orderItems,
            'payments' => $payments,
            'invoices' => $invoices,
            'reservations' => $reservations->count(),
            'client_forms' => $clientForms->count(),
            'carts' => $carts->count(),
            'contact_messages' => $contacts->count(),
            'galleries' => $galleries->count(),
            'gift_cards' => $giftCards->count(),
            'download_logs' => $downloadLogs,
        ];

        return [
            'query' => ['type' => $type, 'value' => $value],
            'summary' => $summary,
            'categories' => $categories,
        ];
    }

    private function seedFromOrderNumber(string $orderNumber): void
    {
        $order = Order::where('order_number', $orderNumber)->first();
        if (! $order) {
            return;
        }
        $this->orderIds->push($order->id);
        if ($order->guest_email) {
            $this->emails->push(mb_strtolower($order->guest_email));
        }
        if ($order->user_id) {
            $this->userIds->push($order->user_id);
        }
    }

    /** @return Collection<int, User> */
    private function resolveUsers(): Collection
    {
        if ($this->emails->isEmpty() && $this->phones->isEmpty() && $this->userIds->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->where(function ($q) {
                if ($this->emails->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('lower(email)'), $this->emails->all());
                }
                if ($this->phones->isNotEmpty()) {
                    $q->orWhereIn('phone', $this->phones->all());
                }
                if ($this->userIds->isNotEmpty()) {
                    $q->orWhereIn('id', $this->userIds->all());
                }
            })
            ->get();

        $this->userIds = $this->userIds->merge($users->pluck('id'))->unique()->values();
        $this->emails = $this->emails->merge($users->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e)))->unique()->values();
        $this->phones = $this->phones->merge($users->pluck('phone')->filter())->unique()->values();

        return $users;
    }

    /** @return Collection<int, Client> */
    private function resolveClients(): Collection
    {
        if ($this->emails->isEmpty() && $this->phones->isEmpty() && $this->userIds->isEmpty()) {
            return collect();
        }

        $clients = Client::query()
            ->where(function ($q) {
                if ($this->emails->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('lower(email)'), $this->emails->all());
                }
                if ($this->phones->isNotEmpty()) {
                    $q->orWhereIn('phone', $this->phones->all());
                }
                if ($this->userIds->isNotEmpty()) {
                    $q->orWhereIn('user_id', $this->userIds->all());
                }
            })
            ->get();

        $this->clientIds = $clients->pluck('id')->unique()->values();
        $this->emails = $this->emails->merge($clients->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e)))->unique()->values();
        $this->phones = $this->phones->merge($clients->pluck('phone')->filter())->unique()->values();

        return $clients;
    }

    /** @return Collection<int, Order> */
    private function resolveOrders(): Collection
    {
        if ($this->emails->isEmpty() && $this->phones->isEmpty() && $this->userIds->isEmpty() && $this->orderIds->isEmpty()) {
            return collect();
        }

        $orders = Order::query()
            ->where(function ($q) {
                if ($this->emails->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('lower(guest_email)'), $this->emails->all());
                }
                if ($this->phones->isNotEmpty()) {
                    $q->orWhereIn('shipping_phone', $this->phones->all());
                }
                if ($this->userIds->isNotEmpty()) {
                    $q->orWhereIn('user_id', $this->userIds->all());
                }
                if ($this->orderIds->isNotEmpty()) {
                    $q->orWhereIn('id', $this->orderIds->all());
                }
            })
            ->get();

        $this->orderIds = $this->orderIds->merge($orders->pluck('id'))->unique()->values();

        return $orders;
    }

    /** @return Collection<int, Reservation> */
    private function resolveReservations(): Collection
    {
        if ($this->emails->isEmpty() && $this->phones->isEmpty() && $this->userIds->isEmpty() && $this->clientIds->isEmpty()) {
            return collect();
        }

        $reservations = Reservation::query()
            ->where(function ($q) {
                if ($this->emails->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('lower(guest_email)'), $this->emails->all());
                }
                if ($this->phones->isNotEmpty()) {
                    $q->orWhereIn('guest_phone', $this->phones->all());
                }
                if ($this->userIds->isNotEmpty()) {
                    $q->orWhereIn('user_id', $this->userIds->all());
                }
                if ($this->clientIds->isNotEmpty()) {
                    $q->orWhereIn('client_id', $this->clientIds->all());
                }
            })
            ->get();

        $this->reservationIds = $reservations->pluck('id')->unique()->values();

        return $reservations;
    }

    /** @return Collection<int, Gallery> */
    private function resolveGalleries(): Collection
    {
        if ($this->emails->isEmpty() && $this->userIds->isEmpty()) {
            return collect();
        }

        $galleries = Gallery::query()
            ->where(function ($q) {
                if ($this->emails->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('lower(assigned_email)'), $this->emails->all());
                }
                if ($this->userIds->isNotEmpty()) {
                    $q->orWhereIn('user_id', $this->userIds->all());
                }
            })
            ->get();

        $this->galleryIds = $galleries->pluck('id')->unique()->values();

        return $galleries;
    }

    /** @return Collection<int, Cart> */
    private function resolveCarts(): Collection
    {
        if ($this->emails->isEmpty() && $this->userIds->isEmpty()) {
            return collect();
        }

        return Cart::query()
            ->where(function ($q) {
                if ($this->emails->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('lower(guest_email)'), $this->emails->all());
                }
                if ($this->userIds->isNotEmpty()) {
                    $q->orWhereIn('user_id', $this->userIds->all());
                }
            })
            ->get();
    }

    /** @return Collection<int, ContactMessage> */
    private function resolveContactMessages(): Collection
    {
        if ($this->emails->isEmpty() && $this->phones->isEmpty()) {
            return collect();
        }

        return ContactMessage::query()
            ->where(function ($q) {
                if ($this->emails->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('lower(email)'), $this->emails->all());
                }
                if ($this->phones->isNotEmpty()) {
                    $q->orWhereIn('phone', $this->phones->all());
                }
            })
            ->get();
    }

    /** @return Collection<int, GiftCard> */
    private function resolveGiftCards(): Collection
    {
        if ($this->emails->isEmpty()) {
            return collect();
        }

        return GiftCard::query()
            ->whereIn(DB::raw('lower(recipient_email)'), $this->emails->all())
            ->get();
    }

    /** @return Collection<int, ClientForm> */
    private function resolveClientForms(): Collection
    {
        if ($this->reservationIds->isEmpty() && $this->phones->isEmpty()) {
            return collect();
        }

        return ClientForm::query()
            ->where(function ($q) {
                if ($this->reservationIds->isNotEmpty()) {
                    $q->orWhereIn('reservation_id', $this->reservationIds->all());
                }
                if ($this->phones->isNotEmpty()) {
                    $q->orWhereIn('phone', $this->phones->all());
                }
            })
            ->get();
    }

    private function downloadLogsQuery()
    {
        if ($this->galleryIds->isEmpty()) {
            return DownloadLog::query()->whereRaw('1 = 0');
        }

        return DownloadLog::query()->whereIn('gallery_id', $this->galleryIds->all());
    }

    private function resolvePayments(): int
    {
        if ($this->orderIds->isEmpty() && $this->reservationIds->isEmpty() && $this->userIds->isEmpty()) {
            return 0;
        }

        return Payment::query()
            ->where(function ($q) {
                if ($this->orderIds->isNotEmpty()) {
                    $q->orWhereIn('order_id', $this->orderIds->all());
                }
                if ($this->reservationIds->isNotEmpty()) {
                    $q->orWhereIn('reservation_id', $this->reservationIds->all());
                }
                if ($this->userIds->isNotEmpty()) {
                    $q->orWhereIn('user_id', $this->userIds->all());
                }
            })
            ->count();
    }
}
