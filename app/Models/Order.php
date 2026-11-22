<?php

namespace App\Models;

use App\Services\Shipping;
use App\Support\Media;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUSES = [
        // Online payment started but not completed: not a sale, nothing to ship.
        'awaiting_payment' => 'Awaiting payment',
        'pending' => 'New',
        'confirmed' => 'Confirmed',
        'shipped' => 'Out for delivery',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses that don't count as sales (revenue, customer spend, analytics). */
    public const NOT_SALES = ['cancelled', 'awaiting_payment'];

    /** Unpaid online orders are cancelled after this many hours (frees any coupon use they hold). */
    public const UNPAID_CANCEL_HOURS = 24;

    public const PAYMENT_METHODS = [
        'whatsapp' => 'Confirm on WhatsApp',
        'delivery' => 'Pay on delivery',
        'bank_transfer' => 'Bank transfer',
        'paystack' => 'Pay online (Paystack)',
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
        'discount_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->token ??= Str::random(40);
        });
        static::saving(function (Order $order) {
            if ($order->isDirty(['customer_phone', 'customer_email']) || ! $order->customer_key) {
                $order->customer_key = self::customerKey($order->customer_phone, $order->customer_email);
            }
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    /** Paystack order still waiting for its online payment (and still payable). */
    public function awaitsOnlinePayment(): bool
    {
        return $this->payment_method === 'paystack' && $this->payment_status === 'unpaid' && $this->status !== 'cancelled';
    }

    /**
     * What the customer should be told about an online payment:
     * paid | confirming | not_paid | review | cancelled | null (not an online order).
     */
    public function onlinePaymentState(): ?string
    {
        if ($this->payment_method !== 'paystack') {
            return null;
        }
        if ($this->payment_status === 'paid') {
            return 'paid';
        }
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        $latest = $this->payments()->first();

        return match (true) {
            // A bank transfer can take a few minutes; after 15 the customer may pay again.
            $latest?->status === 'pending' && $latest->created_at?->gt(now()->subMinutes(15)) => 'confirming',
            $latest?->status === 'flagged' => 'review',
            default => 'not_paid',
        };
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Groups a guest's orders into one customer. Phones match on their last 10
     * digits, so "0801 234 5678" and "+234 801 234 5678" are the same person;
     * without a usable phone, the email is used. URL-safe ("p8012345678").
     */
    public static function customerKey(?string $phone, ?string $email): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) >= 7) {
            return 'p'.substr($digits, -10);
        }
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' ? 'e'.substr(md5($email), 0, 24) : null;
    }

    /** wa.me link to message the customer (their WhatsApp number, else their phone). */
    public function customerWhatsappLink(?string $message = null): ?string
    {
        return WhatsApp::chatLink($this->customer_whatsapp ?: $this->customer_phone, $this->shipping_country, $message);
    }

    public function hasDiscount(): bool
    {
        return (float) $this->discount_total > 0;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        // Fast paths that use indexes (instant even with 100,000+ orders):
        // an order number ("#1042", "1042") or a phone number ("0801 234 5678").
        $digits = preg_replace('/[\s()+\-.#]/', '', $term);
        if (ctype_digit($digits) && $digits !== '') {
            return $query->where(fn (Builder $q) => $q
                ->where('number', 'like', $digits.'%')
                ->when(strlen($digits) >= 7, fn ($q) => $q->orWhere('customer_key', 'p'.substr($digits, -10))));
        }

        $like = '%'.addcslashes(ltrim($term, '#'), '%_\\').'%';

        return $query->where(fn (Builder $q) => $q
            ->where('number', 'like', $like)
            ->orWhere('customer_name', 'like', $like)
            ->orWhere('customer_phone', 'like', $like)
            ->orWhere('customer_email', 'like', $like)
            ->orWhere('coupon_code', 'like', $like));
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
            'customer_whatsapp' => $this->customer_whatsapp,
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
            'coupon_code' => $this->coupon_code,
            'discount' => $this->hasDiscount() ? Money::format($this->discount_total) : null,
            'delivery_fee' => (float) $this->delivery_fee > 0 ? Money::format($this->delivery_fee) : null,
            'total' => Money::format($this->total),
            'item_count' => $this->itemCount(),
            'created_at' => $this->created_at?->format('j M Y, g:i a'),
            'items' => $this->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'name' => $i->product_name,
                'variant' => $i->variant_label,
                'sku' => $i->sku,
                'url' => $i->product_slug ? route('products.show', $i->product_slug).($i->product_variant_id ? '?variant='.$i->product_variant_id : '') : null,
                'image' => Media::url($i->image_path, 'sm'),
                'unit_price' => Money::format($i->unit_price),
                'quantity' => $i->quantity,
                'line_total' => Money::format($i->line_total),
            ])->values(),
        ];
    }
}
