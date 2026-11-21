<?php

namespace App\Models;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A coupon code (WooCommerce's coupon model):
 * - percent: a percentage off the eligible items
 * - fixed_cart: a fixed amount off the eligible items, shared between them
 * - fixed_product: a fixed amount off each eligible item (per unit)
 * Any type can also unlock free shipping methods set to require a coupon.
 *
 * Usage is counted from orders that used the coupon and were not cancelled,
 * so cancelling an order gives the use back.
 */
class Coupon extends Model
{
    public const TYPES = [
        'percent' => 'Percentage discount',
        'fixed_cart' => 'Fixed cart discount',
        'fixed_product' => 'Fixed product discount',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'scheduled' => 'Scheduled',
        'expired' => 'Expired',
        'used_up' => 'Used up',
        'disabled' => 'Disabled',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = [
        'discount_type' => 'percent',
        'amount' => 0,
        'free_shipping' => false,
        'is_active' => true,
        'exclude_sale_items' => false,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'minimum_spend' => 'decimal:2',
            'maximum_spend' => 'decimal:2',
            'free_shipping' => 'boolean',
            'is_active' => 'boolean',
            'exclude_sale_items' => 'boolean',
            'starts_on' => 'date',
            'expires_on' => 'date',
            'product_ids' => 'array',
            'excluded_product_ids' => 'array',
            'category_ids' => 'array',
            'excluded_category_ids' => 'array',
            'allowed_emails' => 'array',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'limit_usage_to_items' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon) {
            $coupon->code = self::normalizeCode($coupon->code);
        });
    }

    public static function normalizeCode(?string $code): string
    {
        return Str::upper(preg_replace('/\s+/', '', (string) $code));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = self::normalizeCode($code);

        return $code === '' ? null : self::query()->where('code', $code)->first();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Orders that count towards the usage limits. */
    public function countedOrders(): HasMany
    {
        return $this->orders()->where('status', '!=', 'cancelled');
    }

    public function usageCount(): int
    {
        return $this->countedOrders()->count();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $q) => $q->where('code', 'like', $like)->orWhere('description', 'like', $like));
    }

    /** Today in the store's timezone (coupon dates are whole days there). */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(BusinessSetting::current()->timezone ?: config('app.timezone'))->startOfDay();
    }

    public function hasStarted(): bool
    {
        return ! $this->starts_on || $this->starts_on->toDateString() <= self::today()->toDateString();
    }

    public function hasExpired(): bool
    {
        return $this->expires_on && $this->expires_on->toDateString() < self::today()->toDateString();
    }

    /** @param int|null $used pass a preloaded usage count to avoid a query */
    public function status(?int $used = null): string
    {
        return match (true) {
            ! $this->is_active => 'disabled',
            $this->hasExpired() => 'expired',
            ! $this->hasStarted() => 'scheduled',
            $this->usage_limit !== null && ($used ?? $this->usageCount()) >= $this->usage_limit => 'used_up',
            default => 'active',
        };
    }

    /** Short description of the offer, e.g. "10% off · Free shipping". */
    public function offerLabel(?BusinessSetting $settings = null): string
    {
        $amount = (float) $this->amount;
        $parts = [];
        if ($amount > 0) {
            $parts[] = match ($this->discount_type) {
                'percent' => rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.').'% off',
                'fixed_product' => Money::format($amount, $settings).' off each item',
                default => Money::format($amount, $settings).' off',
            };
        }
        if ($this->free_shipping) {
            $parts[] = 'Free shipping';
        }

        return $parts === [] ? 'No discount' : implode(' · ', $parts);
    }

    public function hasProductRestrictions(): bool
    {
        return ! empty($this->product_ids) || ! empty($this->category_ids)
            || ! empty($this->excluded_product_ids) || ! empty($this->excluded_category_ids)
            || $this->exclude_sale_items;
    }

    /** Whether a product (or one of its variations) in the cart gets the discount. */
    public function appliesTo(Product $product, ?ProductVariant $variant = null): bool
    {
        $in = fn (?array $ids, $id) => $id !== null && in_array((int) $id, array_map('intval', $ids ?? []), true);

        if ($in($this->excluded_product_ids, $product->id) || $in($this->excluded_category_ids, $product->category_id)) {
            return false;
        }
        if ($this->exclude_sale_items && ($variant ? $variant->hasDiscount() : $product->hasDiscount())) {
            return false;
        }
        $restricted = ! empty($this->product_ids) || ! empty($this->category_ids);

        return ! $restricted || $in($this->product_ids, $product->id) || $in($this->category_ids, $product->category_id);
    }

    /**
     * Allowed-email check. Entries can be full addresses or wildcards such as
     * "*@company.com". No list means anyone may use it.
     */
    public function allowsEmail(?string $email): bool
    {
        if (empty($this->allowed_emails)) {
            return true;
        }
        $email = Str::lower(trim((string) $email));
        if ($email === '') {
            return false;
        }

        foreach ($this->allowed_emails as $pattern) {
            if (Str::is(Str::lower(trim($pattern)), $email)) {
                return true;
            }
        }

        return false;
    }
}
