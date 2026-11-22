<?php

namespace App\Models;

use App\Support\StorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer's review of a product. Only approved reviews are ever shown. */
class Review extends Model
{
    public const STATUSES = [
        'pending' => 'Waiting for approval',
        'approved' => 'Published',
        'rejected' => 'Rejected',
        'spam' => 'Spam',
    ];

    public const AUTO_PUBLISH = [
        'manual' => 'Approve every review myself',
        'verified' => 'Publish verified buyers’ reviews straight away',
    ];

    /** Approved 4–5 star reviews needed before the homepage section appears. */
    public const HOME_MINIMUM = 5;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['email', 'ip_hash', 'body_hash'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_verified' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the product's rating and count current, and refresh cached homepage data.
        $sync = function (Review $review) {
            $review->product?->syncRating();
            if ($review->product_id !== (int) $review->getOriginal('product_id') && $review->getOriginal('product_id')) {
                Product::query()->find($review->getOriginal('product_id'))?->syncRating();
            }
            StorefrontCache::flush();
        };
        static::saved($sync);
        static::deleted($sync);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /** What the storefront shows (no email or internal data). */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'verified' => $this->is_verified,
            'date' => $this->approved_at?->format('j M Y') ?? $this->created_at?->format('j M Y'),
        ];
    }
}
