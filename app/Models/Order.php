<?php

namespace App\Models;

use App\Services\Shipping;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUSES = [
        'pending' => 'New',
        'confirmed' => 'Confirmed',
        'shipped' => 'Out for delivery',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_METHODS = [
        'whatsapp' => 'Confirm on WhatsApp',
        'delivery' => 'Pay on delivery',
        'bank_transfer' => 'Bank transfer',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
    ];

    public const FULFILMENT = [
        'delivery' => 'Delivery',
        'pickup' => 'Pickup',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'fulfilment' => 'delivery',
        'delivery_fee' => 0,
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->token ??= Str::random(40);
        });
        // Short, sequential, human-friendly order numbers: #1001, #1002, ...
        static::created(function (Order $order) {
            $order->forceFill(['number' => (string) (1000 + $order->id)])->saveQuietly();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.addcslashes(ltrim($term, '#'), '%_\\').'%';

        return $query->where(fn (Builder $q) => $q
            ->where('number', 'like', $like)
            ->orWhere('customer_name', 'like', $like)
            ->orWhere('customer_phone', 'like', $like)
            ->orWhere('customer_email', 'like', $like));
    }

    public function url(): string
    {
        return route('orders.show', $this->token);
    }

    public function isPickup(): bool
    {
        return $this->fulfilment === 'pickup';
    }

    /**
     * One-line address. For delivery: the customer's address. For pickup: the
     * pickup point (stored in `address` when the order is placed).
     */
    public function addressLine(): string
    {
        $parts = $this->isPickup()
            ? [$this->address]
            : [$this->address, $this->city, $this->shipping_state, $this->postcode, Shipping::countryName($this->shipping_country)];

        return implode(', ', array_filter($parts));
    }

    /** "Free", the fee, or "To be confirmed" when no shipping method was set up. */
    public function shippingCostLabel(): string
    {
        if ((float) $this->delivery_fee > 0) {
            return Money::format($this->delivery_fee);
        }

        return $this->shipping_method ? 'Free' : 'To be confirmed';
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** Shared representation for the customer's order page, the admin and emails. */
    public function toDetail(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => self::STATUSES[$this->status] ?? $this->status,
            'payment_method' => $this->payment_method,
            'payment_method_label' => self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_status_label' => self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'fulfilment' => $this->fulfilment,
            'fulfilment_label' => self::FULFILMENT[$this->fulfilment] ?? $this->fulfilment,
            'shipping_zone' => $this->shipping_zone_name,
            'shipping_method' => $this->shipping_method,
            'shipping_method_type' => $this->shipping_method_type,
            'shipping_cost' => $this->shippingCostLabel(),
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->shipping_state,
            'postcode' => $this->postcode,
            'country' => Shipping::countryName($this->shipping_country),
            'address_line' => $this->addressLine(),
            'notes' => $this->notes,
            'subtotal' => Money::format($this->subtotal),
            'delivery_fee' => (float) $this->delivery_fee > 0 ? Money::format($this->delivery_fee) : null,
            'total' => Money::format($this->total),
            'item_count' => $this->itemCount(),
            'created_at' => $this->created_at?->format('j M Y, g:i a'),
            'items' => $this->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'name' => $i->product_name,
                'url' => $i->product_slug ? route('products.show', $i->product_slug) : null,
                'image' => Media::url($i->image_path, 'sm'),
                'unit_price' => Money::format($i->unit_price),
                'quantity' => $i->quantity,
                'line_total' => Money::format($i->line_total),
            ])->values(),
        ];
    }
}
