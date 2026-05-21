<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class BackfillOrderCustomerInfo extends Command
{
    protected $signature = 'orders:backfill-customer-info {--dry-run : List affected orders without updating}';

    protected $description = 'Backfill guest_email / guest_first_name / guest_last_name on orders where user_id is set but those columns are empty.';

    public function handle(): int
    {
        $orders = Order::with('user')
            ->whereNotNull('user_id')
            ->where(function ($q) {
                $q->whereNull('guest_email')->orWhere('guest_email', '')
                    ->orWhereNull('guest_first_name')->orWhere('guest_first_name', '')
                    ->orWhereNull('guest_last_name')->orWhere('guest_last_name', '');
            })
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No orders to backfill.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $user = $order->user;

            if (! $user) {
                $skipped++;
                $this->warn("Skip {$order->order_number} — user introuvable (id={$order->user_id}).");

                continue;
            }

            $updates = [];
            if (empty($order->guest_email) && $user->email) {
                $updates['guest_email'] = $user->email;
            }
            if (empty($order->guest_first_name) && $user->first_name) {
                $updates['guest_first_name'] = $user->first_name;
            }
            if (empty($order->guest_last_name) && $user->last_name) {
                $updates['guest_last_name'] = $user->last_name;
            }

            if (empty($updates)) {
                $skipped++;

                continue;
            }

            $this->line("{$order->order_number} → ".json_encode($updates, JSON_UNESCAPED_UNICODE));

            if (! $dryRun) {
                $order->update($updates);
            }

            $updated++;
        }

        $verb = $dryRun ? 'would be updated' : 'updated';
        $this->info("{$updated} order(s) {$verb}, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
