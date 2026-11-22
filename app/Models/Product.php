<?php

namespace App\Models;

use App\Support\Markdown;
use App\Support\Media;
use App\Support\Money;
use App\Support\StorefrontCache;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_DRAFT => 'Draft',
    ];

    /** Availability options shown to customers. Keys are stored in the database. */
    public const AVAILABILITY = [
        'in_stock' => 'In Stock',
        'limited' => 'Limited Stock',
        'on_request' => 'Available on Request',
        'pre_order' => 'Pre-order',
        'out_of_stock' => 'Out of Stock',
    ];

    /** Schema.org ItemAvailability equivalents. */
    public const SCHEMA_AVAILABILITY = [
        'in_stock' => 'https://schema.org/InStock',
        'limited' => 'https://schema.org/LimitedAvailability',
        'on_request' => 'https://schema.org/InStoreOnly',
        'pre_order' => 'https://schema.org/PreOrder',
        'out_of_stock' => 'https://schema.org/OutOfStock',
    ];

    /** Availabilities that can be added to the cart (others are enquiry-only). */
    public const PURCHASABLE = ['in_stock', 'limited', 'pre_order'];

    public const SORTS = [
        'latest' => 'Latest',
        'oldest' => 'Oldest',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'name_asc' => 'Name: A to Z',
        'name_desc' => 'Name: Z to A',
    ];

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'compare_at_price',
        'options',
        'availability',
        'is_featured',
        'is_new_arrival',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'price_max' => 'decimal:2',
            'reviews_count' => 'integer',
            'rating_avg' => 'decimal:2',
            'options' => 'array',
            'has_variants' => 'boolean',
            'is_featured' => 'boolean',
            'is_new_arrival' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->status === self::STATUS_PUBLISHED && ! $product->published_at) {
                $product->published_at = now();
            }
        });
        static::saved(fn () => StorefrontCache::flush());
        static::deleted(fn () => StorefrontCache::flush());

        // A published product's URL changed: send the old URL to the new one.
        static::updated(function (Product $product) {
            if ($product->wasChanged('slug') && $product->getOriginal('status') === self::STATUS_PUBLISHED) {
                Redirect::moved('/products/'.$product->getOriginal('slug'), '/products/'.$product->slug);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Recalculates the approved-review count and average (saved quietly: no cache churn). */
    public function syncRating(): void
    {
        $row = $this->reviews()->approved()->toBase()->selectRaw('COUNT(*) as n, AVG(rating) as avg')->first();
        $this->forceFill([
            'reviews_count' => (int) $row->n,
            'rating_avg' => $row->n ? round((float) $row->avg, 2) : null,
        ])->saveQuietly();
    }

    /** Star rating for cards and the product page; null when reviews are off or there are none. */
    public function ratingSummary(): ?array
    {
        if (! BusinessSetting::current()->reviews_enabled || ! $this->reviews_count) {
            return null;
        }

        return ['average' => round((float) $this->rating_avg, 1), 'count' => (int) $this->reviews_count];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Options as stored: [{name, values: [{label, color}]}]. */
    public function optionList(): array
    {
        return $this->has_variants ? array_values($this->options ?? []) : [];
    }

    /** Variants have different prices, so cards say "From". */
    public function hasPriceRange(): bool
    {
        return $this->has_variants && $this->price_max !== null && (float) $this->price_max > (float) $this->price;
    }

    /**
     * Keep the product's own price and availability in step with its variants,
     * so cards, sorting, search engines and feeds keep working: price is the
     * lowest (with that variant's previous price), price_max the highest, and
     * availability the best one any enabled variant has.
     */
    public function syncFromVariants(): void
    {
        $variants = $this->variants()->get();
        $enabled = $variants->where('is_enabled', true);
        if (! $this->has_variants || $variants->isEmpty()) {
            $this->forceFill(['has_variants' => false, 'price_max' => null])->save();

            return;
        }

        $pool = $enabled->isNotEmpty() ? $enabled : $variants;
        $cheapest = $pool->sortBy(fn (ProductVariant $v) => (float) $v->price)->first();
        $best = collect(['in_stock', 'limited', 'pre_order', 'on_request'])
            ->first(fn ($a) => $enabled->contains('availability', $a)) ?? 'out_of_stock';

        $this->forceFill([
            'price' => $cheapest->price,
            'compare_at_price' => $cheapest->hasDiscount() ? $cheapest->compare_at_price : null,
            'price_max' => $pool->max(fn (ProductVariant $v) => (float) $v->price),
            'availability' => $best,
        ])->save();
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /** Only products whose category is visible (or that have no category). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->published()->where(function (Builder $q) {
            $q->whereNull('category_id')->orWhereHas('category', fn (Builder $c) => $c->where('is_published', true));
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        // Escape LIKE wildcards so user input is matched literally.
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    public function scopeSorted(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** Visible to customers and in an availability that can be ordered online. */
    public function isPurchasable(): bool
    {
        return $this->isPublished()
            && in_array($this->availability, self::PURCHASABLE, true)
            && (! $this->category_id || $this->category?->is_published);
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && (float) $this->compare_at_price > (float) $this->price;
    }

    public function discountPercent(): ?int
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        return (int) round((1 - ((float) $this->price / (float) $this->compare_at_price)) * 100);
    }

    public function url(): string
    {
        return route('products.show', $this->slug);
    }

    /** Compact representation for product cards. Requires primaryImage + category loaded. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => $this->url(),
            'price' => Money::format($this->price),
            'price_from' => $this->hasPriceRange(),
            'compare_at_price' => $this->hasDiscount() ? Money::format($this->compare_at_price) : null,
            'discount_percent' => $this->discountPercent(),
            // Options must be chosen on the product page, so cards link there instead of adding to the cart.
            'has_variants' => (bool) $this->has_variants,
            'is_featured' => $this->is_featured,
            'is_new_arrival' => $this->is_new_arrival,
            'availability' => $this->availability,
            'availability_label' => self::AVAILABILITY[$this->availability] ?? null,
            // Cards come from visible() queries, so availability decides quick add.
            'purchasable' => in_array($this->availability, self::PURCHASABLE, true),
            'category' => $this->relationLoaded('category') && $this->category ? $this->category->name : null,
            'image' => Media::image($this->primaryImage?->path, $this->primaryImage?->alt ?: $this->name),
            'rating' => $this->ratingSummary(),
        ];
    }

    /** Full representation for the product details page. */
    public function toDetail(): array
    {
        // array_merge (not +) so the detail keys below override the card's.
        return array_merge($this->toCard(), [
            'short_description' => $this->short_description,
            'description_html' => Markdown::toHtml($this->description),
            'images' => $this->images->map(fn (ProductImage $image) => Media::image($image->path, $image->alt ?: $this->name))->values(),
            'category' => $this->category ? [
                'name' => $this->category->name,
                'url' => route('categories.show', $this->category->slug),
            ] : null,
            'is_published' => $this->isPublished(),
            'purchasable' => $this->isPurchasable(),
            ...$this->variantDetail(),
        ]);
    }

    /** Options and enabled variants for the product page (empty for simple products). */
    protected function variantDetail(): array
    {
        if (! $this->has_variants) {
            return ['options' => [], 'variants' => []];
        }
        $imageIndex = $this->images->values()->mapWithKeys(fn (ProductImage $img, int $i) => [$img->id => $i])->all();
        $variants = $this->variants->where('is_enabled', true)->values();
        // Only values that some enabled variant uses.
        $used = fn (int $i) => $variants->map(fn (ProductVariant $v) => $v->values()[$i] ?? null)->filter()->unique()->all();

        return [
            'options' => collect($this->optionList())->map(fn (array $o, int $i) => [
                'name' => $o['name'],
                'values' => collect($o['values'])->filter(fn ($v) => in_array($v['label'], $used($i), true))->map(fn ($v) => [
                    'label' => $v['label'],
                    'color' => $v['color'] ?? null,
                ])->values()->all(),
            ])->values()->all(),
            'variants' => $variants->map(fn (ProductVariant $v) => $v->toStorefront($imageIndex))->all(),
        ];
    }
}
