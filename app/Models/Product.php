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
            'compare_at_price' => $this->hasDiscount() ? Money::format($this->compare_at_price) : null,
            'discount_percent' => $this->discountPercent(),
            'is_featured' => $this->is_featured,
            'is_new_arrival' => $this->is_new_arrival,
            'availability' => $this->availability,
            'availability_label' => self::AVAILABILITY[$this->availability] ?? null,
            // Cards come from visible() queries, so availability decides quick add.
            'purchasable' => in_array($this->availability, self::PURCHASABLE, true),
            'category' => $this->relationLoaded('category') && $this->category ? $this->category->name : null,
            'image' => Media::image($this->primaryImage?->path, $this->primaryImage?->alt ?: $this->name),
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
        ]);
    }
}
