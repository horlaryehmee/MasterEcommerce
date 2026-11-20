<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shipping zone: the regions it covers (countries, states, optional
 * postcodes) and the shipping methods offered there. Zones are matched top to
 * bottom; the fallback zone covers everywhere else.
 */
class ShippingZone extends Model
{
    public const FALLBACK_NAME = 'Locations not covered by your other zones';

    protected $fillable = ['name', 'sort_order', 'is_fallback'];

    protected function casts(): array
    {
        return ['is_fallback' => 'boolean', 'sort_order' => 'integer'];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ShippingZoneLocation::class);
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('is_fallback')->orderBy('sort_order')->orderBy('id');
    }

    public static function fallback(): self
    {
        return static::query()->firstOrCreate(['is_fallback' => true], ['name' => self::FALLBACK_NAME, 'sort_order' => 9999]);
    }

    /** Region codes ("NG", "NG:LA"). Requires locations loaded. */
    public function regionCodes(): array
    {
        return $this->locations->whereIn('type', ['country', 'state'])->pluck('code')->values()->all();
    }

    /** @return string[] Requires locations loaded. */
    public function postcodes(): array
    {
        return $this->locations->where('type', 'postcode')->pluck('code')->values()->all();
    }
}
