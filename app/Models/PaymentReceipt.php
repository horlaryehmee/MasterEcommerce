<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A bank-transfer receipt a customer uploaded. The file is private (admin only). */
class PaymentReceipt extends Model
{
    public const STATUSES = [
        'pending' => 'Waiting for check',
        'approved' => 'Payment confirmed',
        'rejected' => 'Not accepted',
    ];

    /** Uploads allowed per order (re-uploads after a rejection included). */
    public const MAX_PER_ORDER = 5;

    /** Largest upload, in kilobytes. */
    public const MAX_KB = 8192;

    /** Private disk: never reachable by a public URL. */
    public const DISK = 'local';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (PaymentReceipt $r) => Storage::disk(self::DISK)->delete($r->path));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** What the customer sees on their order page. */
    public function toCustomer(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'status' => $this->status,
            'status_label' => self::STATUSES[$this->status] ?? $this->status,
            'note' => $this->status === 'rejected' ? $this->note : null,
            'date' => $this->created_at?->format('j M Y, g:i a'),
        ];
    }
}
