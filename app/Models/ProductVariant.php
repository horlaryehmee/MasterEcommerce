<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One combination of a variable product's options (e.g. "M / Red") with its
 * own price, availability, SKU and image.
 */
class ProductVariant extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = [
        'availability' => 'in_stock',
        'is_enabled' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    /** @return list<string> option values in the product's option order */
    public function values(): array
    {
        return array_values(array_filter([$this->option1, $this->option2, $this->option3], fn ($v) => $v !== null && $v !== ''));
    }

    /** "M / Red" */
    public function label(): string
    {
        return implode(' / ', $this->values());
    }

    public function isPurchasable(): bool
    {
        return $this->is_enabled && in_array($this->availability, Product::PURCHASABLE, true);
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && (float) $this->compare_at_price > (float) $this->price;
    }

    public function discountPercent(): ?int
    {
        return $this->hasDiscount() ? (int) round((1 - (float) $this->price / (float) $this->compare_at_price) * 100) : null;
    }

    /**
     * For the product page. `$imageIndex` maps image ids to their position in
     * the gallery so the page can show the variant's photo.
     */
    public function toStorefront(array $imageIndex = []): array
    {
        return [
            'id' => $this->id,
            'values' => $this->values(),
            'label' => $this->label(),
            'price' => (float) $this->price,
            'price_label' => Money::format($this->price),
            'compare_at_price' => $this->hasDiscount() ? Money::format($this->compare_at_price) : null,
            'discount_percent' => $this->discountPercent(),
            'availability' => $this->availability,
            'availability_label' => Product::AVAILABILITY[$this->availability] ?? null,
            'purchasable' => $this->isPurchasable(),
            'image_index' => $imageIndex[$this->product_image_id] ?? null,
            'sku' => $this->sku,
        ];
    }
}
