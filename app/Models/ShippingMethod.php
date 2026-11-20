<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shipping method inside a zone (WooCommerce's three core methods):
 * - flat_rate: cost + optional cost per item
 * - free_shipping: always, or only when the order reaches a minimum amount
 * - local_pickup: optional cost, plus the pickup address shown to customers
 */
class ShippingMethod extends Model
{
    public const TYPES = [
        'flat_rate' => 'Flat rate',
        'free_shipping' => 'Free shipping',
        'local_pickup' => 'Local pickup',
    ];

    protected $fillable = ['type', 'title', 'is_enabled', 'sort_order', 'settings'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'sort_order' => 'integer', 'settings' => 'array'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /** Whether the method can be offered for an order of this size. */
    public function isAvailableFor(float $subtotal): bool
    {
        if (! $this->is_enabled) {
            return false;
        }
        if ($this->type === 'free_shipping' && $this->setting('requires') === 'min_amount') {
            return $subtotal >= (float) $this->setting('min_amount', 0);
        }

        return true;
    }

    public function cost(int $quantity): float
    {
        return match ($this->type) {
            'flat_rate' => round((float) $this->setting('cost', 0) + (float) $this->setting('per_item', 0) * $quantity, 2),
            'local_pickup' => round((float) $this->setting('cost', 0), 2),
            default => 0.0,
        };
    }

    /** Short description for admin lists, e.g. "Flat rate · ₦2,500". */
    public function summary(): string
    {
        $type = self::TYPES[$this->type] ?? $this->type;

        return match ($this->type) {
            'flat_rate' => $type.' · '.Money::format($this->setting('cost', 0)).((float) $this->setting('per_item', 0) > 0 ? ' + '.Money::format($this->setting('per_item')).'/item' : ''),
            'free_shipping' => $this->setting('requires') === 'min_amount' ? $type.' over '.Money::format($this->setting('min_amount', 0)) : $type,
            'local_pickup' => $type.((float) $this->setting('cost', 0) > 0 ? ' · '.Money::format($this->setting('cost')) : ''),
            default => $type,
        };
    }
}
