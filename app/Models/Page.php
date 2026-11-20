<?php

namespace App\Models;

use App\Support\StorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    /** The informational pages every installation ships with: slug => default title. */
    public const DEFAULTS = [
        'about' => 'About Us',
        'contact' => 'Contact Us',
        'faq' => 'Frequently Asked Questions',
        'shipping-and-delivery' => 'Shipping and Delivery',
        'privacy-policy' => 'Privacy Policy',
        'terms-and-conditions' => 'Terms and Conditions',
    ];

    protected $fillable = ['slug', 'title', 'content', 'image_path', 'faq_items', 'is_published', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'faq_items' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => StorefrontCache::flush());
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function url(): string
    {
        return url('/'.$this->slug);
    }
}
