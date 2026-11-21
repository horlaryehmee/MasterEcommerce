<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * WooCommerce-style shipping:
 * - Selling locations decide which countries customers can choose.
 * - Zones are checked top to bottom; the first whose regions (country or
 *   state) match — and whose postcodes match, if it has any — is used.
 *   Otherwise the fallback zone ("locations not covered…") applies.
 * - The zone's enabled methods become the customer's shipping options.
 */
class Shipping
{
    protected ?Collection $zones = null;

    /** All countries, code => name. */
    public static function countries(): array
    {
        return config('countries');
    }

    public static function countryName(?string $code): ?string
    {
        return $code ? (config('countries')[$code] ?? $code) : null;
    }

    /** States for a country (code => name); empty for countries using free text. */
    public static function states(?string $country): array
    {
        return $country ? (config('country_states')[$country] ?? []) : [];
    }

    public static function stateName(?string $country, ?string $state): ?string
    {
        if (! $state) {
            return null;
        }

        return self::states($country)[$state] ?? $state;
    }

    /** Countries customers can ship to, code => name, per the selling-location settings. */
    public function allowedCountries(?BusinessSetting $s = null): array
    {
        $s ??= BusinessSetting::current();
        $all = self::countries();

        $allowed = match ($s->ship_to) {
            'all' => $all,
            'all_except' => array_diff_key($all, array_flip((array) $s->ship_except_countries)),
            default => array_intersect_key($all, array_flip((array) $s->ship_to_countries)),
        };

        // Never leave checkout without a country.
        return $allowed ?: array_intersect_key($all, [$s->default_country ?: 'NG' => true]);
    }

    /** The country pre-selected at checkout. */
    public function defaultCountry(?BusinessSetting $s = null): string
    {
        $s ??= BusinessSetting::current();
        $allowed = $this->allowedCountries($s);

        return isset($allowed[$s->default_country]) ? $s->default_country : (string) array_key_first($allowed);
    }

    /** @return Collection<int, ShippingZone> Zones in matching order (fallback last), with locations and methods. */
    public function zones(): Collection
    {
        return $this->zones ??= ShippingZone::query()->ordered()->with(['locations', 'methods'])->get();
    }

    /** False on a fresh store with no methods: checkout then says the fee will be confirmed. */
    public function isConfigured(): bool
    {
        return $this->zones()->contains(fn (ShippingZone $z) => $z->methods->contains('is_enabled', true));
    }

    public function matchZone(string $country, ?string $state = null, ?string $postcode = null): ?ShippingZone
    {
        foreach ($this->zones()->where('is_fallback', false) as $zone) {
            $regions = $zone->regionCodes();
            $postcodes = $zone->postcodes();

            $regionOk = $regions === []
                ? $postcodes !== [] // a postcode-only zone matches on postcode alone
                : in_array($country, $regions, true) || ($state && in_array($country.':'.$state, $regions, true));

            if ($regionOk && ($postcodes === [] || self::postcodeMatches($postcode, $postcodes))) {
                return $zone;
            }
        }

        return $this->zones()->firstWhere('is_fallback', true);
    }

    /**
     * Shipping options for a destination and cart.
     *
     * @return Collection<int, array{id: int, type: string, title: string, cost: float, cost_label: string, address: ?string, method: ShippingMethod}>
     */
    public function rates(string $country, ?string $state, ?string $postcode, float $subtotal, int $quantity, bool $freeShippingCoupon = false): Collection
    {
        if (! isset($this->allowedCountries()[$country])) {
            return collect();
        }

        $zone = $this->matchZone($country, $state, $postcode);

        return collect($zone?->methods ?? [])
            ->filter(fn (ShippingMethod $m) => $m->isAvailableFor($subtotal, $freeShippingCoupon))
            ->map(function (ShippingMethod $m) use ($quantity, $zone) {
                $cost = $m->cost($quantity);

                return [
                    'id' => $m->id,
                    'type' => $m->type,
                    'title' => $m->title,
                    'cost' => $cost,
                    'cost_label' => $cost > 0 ? Money::format($cost) : 'Free',
                    'address' => $m->type === 'local_pickup' ? $m->setting('address') : null,
                    'zone' => $zone->is_fallback ? null : $zone->name,
                    'method' => $m,
                ];
            })
            ->values();
    }

    /**
     * Minimum order for free shipping to the store's own country (if its zone
     * offers it), for the "Add X more for free delivery" hint in the cart.
     */
    public function freeShippingThreshold(?BusinessSetting $s = null): ?float
    {
        $zone = $this->matchZone($this->defaultCountry($s));
        // "Minimum AND coupon" isn't reachable by spending more, so it gives no hint.
        $free = $zone?->methods->first(fn (ShippingMethod $m) => $m->is_enabled && $m->type === 'free_shipping' && in_array($m->setting('requires'), ['min_amount', 'either'], true));

        return $free ? (float) $free->setting('min_amount') : null;
    }

    /**
     * WooCommerce postcode rules: exact ("100001"), wildcard prefix ("100*")
     * or numeric range ("90210...99000"). Case and spaces are ignored.
     */
    public static function postcodeMatches(?string $postcode, array $rules): bool
    {
        $code = strtoupper(preg_replace('/\s+/', '', (string) $postcode));
        if ($code === '') {
            return false;
        }

        foreach ($rules as $rule) {
            $rule = strtoupper(preg_replace('/\s+/', '', $rule));
            if (str_contains($rule, '...')) {
                [$min, $max] = explode('...', $rule, 2);
                if (ctype_digit($code) && ctype_digit($min) && ctype_digit($max) && (int) $code >= (int) $min && (int) $code <= (int) $max) {
                    return true;
                }
            } elseif (str_ends_with($rule, '*')) {
                if (str_starts_with($code, rtrim($rule, '*'))) {
                    return true;
                }
            } elseif ($code === $rule) {
                return true;
            }
        }

        return false;
    }

    /** Human summary of a zone's regions, e.g. "Lagos, Nigeria · Ghana". */
    public static function describeRegions(ShippingZone $zone): string
    {
        $parts = collect($zone->regionCodes())->map(function (string $code) {
            if (str_contains($code, ':')) {
                [$country, $state] = explode(':', $code, 2);

                return self::stateName($country, $state).', '.self::countryName($country);
            }

            return self::countryName($code);
        });

        if ($zone->postcodes() !== []) {
            $parts->push('Postcodes: '.implode(', ', array_slice($zone->postcodes(), 0, 3)).(count($zone->postcodes()) > 3 ? '…' : ''));
        }

        return $parts->implode(' · ') ?: 'No regions yet';
    }
}
