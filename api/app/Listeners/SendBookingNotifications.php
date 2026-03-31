<?php

namespace App\Listeners;

use App\Events\BookingRequested;
use App\Mail\NewReservationRequestMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingNotifications
{
    public function handle(BookingRequested $event): void
    {
        $reservation = $event->reservation;
        $prestation = $event->prestation;
        $data = $event->validatedData;

        // Notify all admins
        try {
            $admins = User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'new_reservation',
                    'title' => 'Nouvelle demande de réservation',
                    'message' => "{$data['name']} souhaite réserver une séance {$prestation->title}",
                    'data' => [
                        'reservation_id' => $reservation->id,
                        'client_name' => $data['name'],
                        'prestation' => $prestation->title,
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create admin notifications', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Send email to admin
        $adminEmail = config('mail.admin_email', config('mail.from.address'));
        if ($adminEmail) {
            try {
                Mail::to($adminEmail)->send(new NewReservationRequestMail(
                    $reservation,
                    $prestation,
                ));
            } catch (\Exception $e) {
                Log::error('Failed to send booking request email to admin', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
