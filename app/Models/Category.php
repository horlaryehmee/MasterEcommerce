<?php

namespace App\Models;

use App\Support\Media;
use App\Support\StorefrontCache;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'sort_order',
        'is_published',
        'is_featured',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => StorefrontCache::flush());
        static::deleted(fn () => StorefrontCache::flush());

        // A published category's URL changed: send the old URL to the new one.
        static::updated(function (Category $category) {
            if ($category->wasChanged('slug') && $category->getOriginal('is_published')) {
                Redirect::moved('/categories/'.$category->getOriginal('slug'), '/categories/'.$category->slug);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function publishedProducts(): HasMany
    {
        return $this->products()->where('status', Product::STATUS_PUBLISHED);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Compact representation used by storefront cards and navigation. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => route('categories.show', $this->slug),
            'description' => $this->description,
            'image' => Media::image($this->image_path, $this->name),
            'products_count' => $this->published_products_count ?? null,
        ];
    }
}
