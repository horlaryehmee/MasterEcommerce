<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A URL redirect (/old-path → /new-path). Created by the admin, or
 * automatically when a product or category slug changes, so old links and
 * search results keep working.
 */
class Redirect extends Model
{
    public const STATUS_CODES = [301 => 'Permanent (301)', 302 => 'Temporary (302)'];

    protected const CACHE_KEY = 'redirects.map';

    protected $fillable = ['from_path', 'to_path', 'status_code', 'is_automatic'];

    protected function casts(): array
    {
        return ['is_automatic' => 'boolean', 'status_code' => 'integer', 'hits' => 'integer', 'last_hit_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** "/Old-Page/?a=1" → "/old-page"; full URLs on this site are reduced to their path. */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https?://#i', $path)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }
        $path = '/'.trim(strtok($path, '?#') ?: '', '/');

        return mb_strtolower(rawurldecode($path));
    }

    /** from_path => [id, to_path, status], cached until a redirect changes. */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get(['id', 'from_path', 'to_path', 'status_code'])
            ->mapWithKeys(fn (self $r) => [$r->from_path => [$r->id, $r->to_path, $r->status_code]])
            ->all());
    }

    /**
     * Record that a page moved. Existing redirects to the old path are pointed
     * straight at the new one (no chains), and a redirect away from the new
     * path is removed (no loops).
     */
    public static function moved(string $from, string $to): void
    {
        $from = self::normalizePath($from);
        $to = self::normalizePath($to);
        if ($from === $to) {
            return;
        }

        static::query()->where('from_path', $to)->delete();
        static::query()->where('to_path', $from)->get()->each(fn (self $r) => $r->update(['to_path' => $to]));
        static::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301, 'is_automatic' => true]);
    }
}
