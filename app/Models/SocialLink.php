<?php

namespace App\Models;

use App\Support\StorefrontCache;
use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    /** Supported platforms in display order, with the host names a valid link must use. */
    public const PLATFORMS = [
        'instagram' => ['label' => 'Instagram', 'hosts' => ['instagram.com']],
        'facebook' => ['label' => 'Facebook', 'hosts' => ['facebook.com', 'fb.com', 'fb.me']],
        'tiktok' => ['label' => 'TikTok', 'hosts' => ['tiktok.com']],
        'x' => ['label' => 'X', 'hosts' => ['x.com', 'twitter.com']],
        'youtube' => ['label' => 'YouTube', 'hosts' => ['youtube.com', 'youtu.be']],
        'linkedin' => ['label' => 'LinkedIn', 'hosts' => ['linkedin.com']],
    ];

    protected $fillable = ['platform', 'url', 'sort_order'];

    protected static function booted(): void
    {
        static::saved(fn () => StorefrontCache::flush());
        static::deleted(fn () => StorefrontCache::flush());
    }
}
