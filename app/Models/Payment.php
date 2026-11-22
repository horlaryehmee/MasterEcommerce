<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One online payment attempt for an order (see App\Services\Paystack). */
class Payment extends Model
{
    public const STATUSES = [
        'pending' => 'Waiting for payment',
        'success' => 'Paid',
        'failed' => 'Failed',
        'abandoned' => 'Not completed',
        'flagged' => 'Needs review',
    ];

    protected $fillable = ['provider', 'reference', 'amount', 'currency', 'status', 'mode', 'ip_address'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_paid' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
