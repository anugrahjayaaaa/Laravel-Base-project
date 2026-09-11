<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'plan_slug', 'user_id', 'amount', 'currency',
        'status', 'gateway', 'gateway_ref', 'invoice_no', 'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'canceled_at' => 'datetime',
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human invoice number; generated lazily if missing (dummy mode). */
    public function getInvoiceNoAttribute(?string $value): string
    {
        if ($value) {
            return $value;
        }

        // ponytail: derive from id so it is stable without a separate column write
        return 'INV-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }
}
