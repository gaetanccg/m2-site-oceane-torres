<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'file_path',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function generateInvoiceNumber(): string
    {
        // Advisory lock prevents concurrent invoice number generation race condition
        DB::statement('SELECT pg_advisory_xact_lock(43)');

        $year = date('Y');
        $lastInvoice = self::where('invoice_number', 'like', "F-{$year}-%")
            ->orderByRaw("CAST(SUBSTRING(invoice_number FROM 'F-\\d{4}-(\\d+)') AS INTEGER) DESC NULLS LAST")
            ->first();

        $sequence = 1;
        if ($lastInvoice && preg_match('/F-\d{4}-(\d+)/', $lastInvoice->invoice_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('F-%s-%05d', $year, $sequence);
    }
}
