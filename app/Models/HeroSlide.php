<?php

namespace App\Models;

use App\Support\Media;
use App\Support\StorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One slide of the homepage banner slider. */
class HeroSlide extends Model
{
    public const POSITIONS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    public const THEMES = ['light' => 'White text (for darker images)', 'dark' => 'Dark text (for lighter images)'];

    public const MODES = ['text' => 'Image with text and buttons', 'image' => 'Image only (my design already has text)'];

    protected $fillable = [
        'desktop_image_path', 'mobile_image_path', 'eyebrow', 'heading', 'description',
        'button_text', 'button_link', 'secondary_text', 'secondary_link',
        'content_mode', 'link', 'text_position', 'text_theme', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => StorefrontCache::flush());
        static::deleted(fn () => StorefrontCache::flush());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Storefront payload. */
    public function toSlide(): array
    {
        $button = fn ($text, $link) => filled($text) && filled($link) ? ['text' => $text, 'link' => $link] : null;
        $imageOnly = $this->content_mode === 'image';

        return [
            'id' => $this->id,
            'image' => Media::image($this->desktop_image_path, $this->heading ?: 'Banner'),
            'mobile_image' => Media::image($this->mobile_image_path, $this->heading ?: 'Banner'),
            'image_only' => $imageOnly,
            'link' => $imageOnly ? $this->link : null,
            'eyebrow' => $imageOnly ? null : $this->eyebrow,
            'heading' => $imageOnly ? null : $this->heading,
            'description' => $imageOnly ? null : $this->description,
            'primary' => $imageOnly ? null : $button($this->button_text, $this->button_link),
            'secondary' => $imageOnly ? null : $button($this->secondary_text, $this->secondary_link),
            'position' => array_key_exists($this->text_position, self::POSITIONS) ? $this->text_position : 'left',
            'theme' => $this->text_theme === 'dark' ? 'dark' : 'light',
        ];
    }
}
