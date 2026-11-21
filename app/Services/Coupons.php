<?php

namespace App\Services;

use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Checks whether a coupon can be used and works out the discount, following
 * WooCommerce's rules:
 * - minimum/maximum spend is compared with the cart subtotal before discounts;
 * - product, category and sale-item restrictions pick the items that get the
 *   discount (a coupon none of the items qualify for is refused);
 * - "limit to X items" caps how many units a percentage or per-item discount
 *   covers, most expensive first;
 * - a discount never exceeds the price of what it applies to.
 *
 * Customer checks (allowed emails, uses per customer) need the checkout
 * details, so they only run when the order is placed.
 */
class Coupons
{
    /**
     * @param  Collection<int, array{key: string, product: \App\Models\Product, variant: ?\App\Models\ProductVariant, unit_price: float, quantity: int, line_total: float}>  $lines
     * @param  array{email?: ?string, phone?: ?string}|null  $customer  null on the cart page
     * @return array{coupon: Coupon, discount: float, lines: array<string, float>, free_shipping: bool}  lines: discount per cart line key
     *
     * @throws CouponException
     */
    public function apply(Coupon $coupon, Collection $lines, ?array $customer = null): array
    {
        $subtotal = round((float) $lines->sum('line_total'), 2);

        $this->checkCoupon($coupon, $subtotal);
        $eligible = $lines->filter(fn (array $l) => $coupon->appliesTo($l['product'], $l['variant'] ?? null));
        if ($eligible->isEmpty() && ($coupon->hasProductRestrictions() || (float) $coupon->amount > 0)) {
            throw new CouponException('Sorry, this coupon doesn’t apply to the items in your cart.');
        }
        if ($customer !== null) {
            $this->checkCustomer($coupon, $customer);
        }

        $discounts = $this->discounts($coupon, $eligible);

        return [
            'coupon' => $coupon,
            'discount' => round(array_sum($discounts), 2),
            'lines' => $discounts,
            'free_shipping' => $coupon->free_shipping,
        ];
    }

    /** @throws CouponException */
    protected function checkCoupon(Coupon $coupon, float $subtotal): void
    {
        if (! $coupon->is_active) {
            throw new CouponException('This coupon is not valid.');
        }
        if (! $coupon->hasStarted()) {
            throw new CouponException('This coupon is not valid yet.');
        }
        if ($coupon->hasExpired()) {
            throw new CouponException('This coupon has expired.');
        }
        if ($coupon->usage_limit !== null && $coupon->usageCount() >= $coupon->usage_limit) {
            throw new CouponException('This coupon has reached its usage limit.');
        }
        if ($coupon->minimum_spend !== null && $subtotal < (float) $coupon->minimum_spend) {
            throw new CouponException('The minimum spend for this coupon is '.Money::format($coupon->minimum_spend).'.');
        }
        if ($coupon->maximum_spend !== null && (float) $coupon->maximum_spend > 0 && $subtotal > (float) $coupon->maximum_spend) {
            throw new CouponException('The maximum spend for this coupon is '.Money::format($coupon->maximum_spend).'.');
        }
    }

    /** @throws CouponException */
    protected function checkCustomer(Coupon $coupon, array $customer): void
    {
        $email = Str::lower(trim((string) ($customer['email'] ?? '')));
        $phone = self::phoneDigits($customer['phone'] ?? null);

        if (! empty($coupon->allowed_emails)) {
            if ($email === '') {
                throw new CouponException('Enter the email address this coupon was issued to.');
            }
            if (! $coupon->allowsEmail($email)) {
                throw new CouponException('Sorry, this coupon is not valid for your email address.');
            }
        }

        if ($coupon->usage_limit_per_customer !== null && ($email !== '' || $phone !== '')) {
            if ($this->usesBy($coupon, $email, $phone) >= $coupon->usage_limit_per_customer) {
                throw new CouponException($coupon->usage_limit_per_customer === 1
                    ? 'You have already used this coupon.'
                    : 'You have already used this coupon the maximum number of times.');
            }
        }
    }

    /** Orders by this customer (same email, or same phone number) that used the coupon. */
    public function usesBy(Coupon $coupon, string $email, string $phone): int
    {
        $orders = $coupon->countedOrders()->get(['id', 'customer_email', 'customer_phone']);

        return $orders->filter(fn (Order $o) => ($email !== '' && Str::lower((string) $o->customer_email) === $email)
            || ($phone !== '' && self::phoneDigits($o->customer_phone) === $phone))->count();
    }

    /**
     * Last 9 digits, so "0801 234 5678" and "+234 801 234 5678" are the same
     * customer. Shorter numbers are compared whole.
     */
    public static function phoneDigits(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * Discount per cart line for the eligible lines.
     *
     * @return array<string, float>
     */
    protected function discounts(Coupon $coupon, Collection $eligible): array
    {
        $amount = (float) $coupon->amount;
        if ($amount <= 0 || $eligible->isEmpty()) {
            return [];
        }

        if ($coupon->discount_type === 'fixed_cart') {
            return $this->spread(min($amount, (float) $eligible->sum('line_total')), $eligible);
        }

        // Percentage and per-item discounts: unit by unit, most expensive first, up to the item limit.
        $left = $coupon->limit_usage_to_items ?: PHP_INT_MAX;
        $discounts = [];
        foreach ($eligible->sortByDesc(fn (array $l) => $l['unit_price']) as $l) {
            if ($left <= 0) {
                break;
            }
            $units = min($l['quantity'], $left);
            $left -= $units;
            $price = (float) $l['unit_price'];
            $perUnit = $coupon->discount_type === 'percent'
                ? $price * min($amount, 100) / 100
                : min($amount, $price);
            $discounts[$l['key']] = min(round($perUnit * $units, 2), (float) $l['line_total']);
        }

        return array_filter($discounts, fn ($d) => $d > 0);
    }

    /**
     * Share a fixed amount between lines in proportion to their totals; the
     * rounding difference goes on the largest line so the parts add up exactly.
     *
     * @return array<string, float>
     */
    protected function spread(float $total, Collection $lines): array
    {
        $base = (float) $lines->sum('line_total');
        if ($total <= 0 || $base <= 0) {
            return [];
        }

        $discounts = [];
        foreach ($lines as $l) {
            $discounts[$l['key']] = round($total * (float) $l['line_total'] / $base, 2);
        }
        $largest = $lines->sortByDesc('line_total')->first()['key'];
        $discounts[$largest] = round($discounts[$largest] + round($total - array_sum($discounts), 2), 2);

        return array_filter($discounts, fn ($d) => $d > 0);
    }

    /** Coupons are offered at checkout only when the owner has at least one switched on. */
    public static function enabled(): bool
    {
        return Coupon::query()->where('is_active', true)->exists();
    }
}
