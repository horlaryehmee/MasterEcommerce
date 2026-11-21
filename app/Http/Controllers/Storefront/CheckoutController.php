<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\CouponException;
use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\Order;
use App\Services\Cart;
use App\Services\Coupons;
use App\Services\OrderMailer;
use App\Services\Shipping;
use App\Services\Stats\Collector;
use App\Support\Seo;
use App\Support\Tracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class CheckoutController extends StorefrontController
{
    public function __construct(protected Cart $cart, protected Shipping $shipping, protected OrderMailer $mailer) {}

    public function show(): Response|RedirectResponse
    {
        if ($this->cart->lines()->isEmpty()) {
            return redirect()->route('cart.show');
        }

        $s = BusinessSetting::current();
        $countries = $this->shipping->allowedCountries($s);
        $default = $this->shipping->defaultCountry($s);

        $coupon = $this->cart->coupon();

        return $this->page('Storefront/Checkout', [
            'items' => $this->cart->toArray(),
            'subtotal' => $this->cart->subtotal(),
            'discount' => $this->cart->discount(),
            'coupon' => $coupon ? Cart::couponArray($coupon, $s) : null,
            'couponsEnabled' => $coupon !== null || Coupons::enabled(),
            'couponNotice' => $this->cart->takeCouponNotice(),
            'countries' => collect($countries)->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values(),
            // Dropdown states for the allowed countries that have them.
            'states' => collect($countries)->keys()->mapWithKeys(fn ($c) => [$c => Shipping::states($c)])->filter()->all(),
            'defaultCountry' => $default,
            'shippingConfigured' => $this->shipping->isConfigured(),
            'initialRates' => $this->publicRates($this->ratesFor($default, null, null)),
            'paymentMethods' => collect($s->paymentMethods())->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'money' => $s->moneyFormat(),
            'track' => [Tracking::beginCheckout($this->cart->lines(), $this->cart->total(), $coupon['coupon']->code ?? null)],
        ], Seo::make()->title('Checkout')->noindex());
    }

    /** Live shipping options for the address typed so far (JSON for the checkout page). */
    public function rates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:20'],
            'postcode' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json([
            'rates' => $this->publicRates($this->ratesFor(strtoupper($data['country']), $data['state'] ?? null, $data['postcode'] ?? null)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $s = BusinessSetting::current();
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.show')->with('error', 'Your cart is empty — the items may no longer be available.');
        }

        $countries = $this->shipping->allowedCountries($s);
        $states = Shipping::states((string) $request->input('country'));
        $configured = $this->shipping->isConfigured();

        // Pickup needs no delivery address; find out before validating.
        $rates = isset($countries[$request->input('country')])
            ? $this->ratesFor($request->input('country'), $request->input('state'), $request->input('postcode'))
            : collect();
        $rate = $rates->firstWhere('id', (int) $request->input('shipping_method'));
        $pickup = $rate && $rate['type'] === 'local_pickup';

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9()\-\s.]{7,40}$/'],
            // Ticked "same as my phone number" (the default) or a separate WhatsApp number.
            'whatsapp_same' => ['nullable', 'boolean'],
            'customer_whatsapp' => [Rule::requiredIf(! $request->boolean('whatsapp_same', true)), 'nullable', 'string', 'max:40', 'regex:/^\+?[0-9()\-\s.]{7,40}$/'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'country' => ['required', Rule::in(array_keys($countries))],
            'state' => $states !== [] ? ['required', Rule::in(array_keys($states))] : ['nullable', 'string', 'max:80'],
            'city' => [Rule::requiredIf(! $pickup), 'nullable', 'string', 'max:120'],
            'address' => [Rule::requiredIf(! $pickup), 'nullable', 'string', 'max:500'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'shipping_method' => [Rule::requiredIf($configured), 'nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(array_keys($s->paymentMethods()))],
        ], [
            'customer_phone.regex' => 'Enter a valid phone number.',
            'customer_whatsapp.required' => 'Enter your WhatsApp number, or tick “Same as my phone number”.',
            'customer_whatsapp.regex' => 'Enter a valid WhatsApp number.',
            'country.in' => 'We don’t deliver to this country.',
            'state.required' => 'Choose your state.',
            'address.required' => 'Enter your street address.',
            'city.required' => 'Enter your town or city.',
            'shipping_method.required' => 'Choose a delivery option.',
            'payment_method.in' => 'Choose how you would like to pay.',
        ], [
            'customer_name' => 'name',
            'customer_phone' => 'phone number',
            'customer_email' => 'email',
        ]);

        // The coupon may have stopped applying since the page loaded (expired, used up…).
        // Don't quietly charge the full price: say so and let the customer decide.
        if ($this->cart->couponCode() && ! $this->cart->coupon()) {
            throw ValidationException::withMessages(['coupon' => $this->cart->takeCouponNotice() ?? 'Your coupon can no longer be used.']);
        }

        if ($configured && ! $rate) {
            throw ValidationException::withMessages(['shipping_method' => $rates->isEmpty()
                ? 'Sorry, we don’t deliver to this address yet. Check your country and state, or message us.'
                : 'Choose a delivery option.']);
        }

        $subtotal = $this->cart->subtotal();
        $fee = $rate['cost'] ?? 0.0;
        $couponId = $this->cart->coupon()['coupon']->id ?? null;

        $order = DB::transaction(function () use ($data, $rate, $pickup, $states, $subtotal, $fee, $lines, $s, $couponId) {
            // Lock the coupon so two orders can't both take its last use, then check it
            // again with the customer's details (allowed emails, uses per customer).
            $applied = null;
            if ($couponId) {
                $coupon = Coupon::query()->lockForUpdate()->find($couponId);
                try {
                    $applied = $coupon ? app(Coupons::class)->apply($coupon, $lines, [
                        'email' => $data['customer_email'] ?? null,
                        'phone' => $data['customer_phone'],
                    ]) : throw new CouponException('This coupon is not valid.');
                } catch (CouponException $e) {
                    throw ValidationException::withMessages(['coupon' => $e->getMessage()]);
                }
            }
            $discount = $applied['discount'] ?? 0.0;

            $order = Order::query()->create([
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_whatsapp' => ($data['whatsapp_same'] ?? true) || blank($data['customer_whatsapp'] ?? null) ? $data['customer_phone'] : $data['customer_whatsapp'],
                'customer_email' => $data['customer_email'] ?? null,
                'fulfilment' => $pickup ? 'pickup' : 'delivery',
                'shipping_country' => $data['country'],
                // Stored as the readable name ("Lagos"), so orders read well everywhere.
                'shipping_state' => $states[$data['state'] ?? ''] ?? ($data['state'] ?? null),
                // For pickup, `address` holds the pickup point the customer chose.
                'address' => $pickup ? $rate['address'] : $data['address'],
                'city' => $pickup ? null : $data['city'],
                'postcode' => $data['postcode'] ?? null,
                'shipping_zone_name' => $rate['zone'] ?? null,
                'shipping_method' => $rate['title'] ?? null,
                'shipping_method_type' => $rate['type'] ?? null,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'coupon_id' => $applied['coupon']->id ?? null,
                'coupon_code' => $applied['coupon']->code ?? null,
                'discount_total' => $discount,
                'delivery_fee' => $fee,
                'total' => round(max(0, $subtotal - $discount) + $fee, 2),
                'currency_code' => $s->currency_code,
            ]);

            $order->items()->createMany($lines->map(fn (array $l) => [
                'product_id' => $l['product']->id,
                'product_variant_id' => $l['variant']?->id,
                'product_name' => $l['product']->name,
                'variant_label' => $l['variant']?->label(),
                'sku' => $l['variant']?->sku,
                'product_slug' => $l['product']->slug,
                'image_path' => ($l['variant']?->image ?? $l['product']->primaryImage)?->path,
                'unit_price' => $l['unit_price'],
                'quantity' => $l['quantity'],
                'line_total' => $l['line_total'],
                'discount' => $applied['lines'][$l['key']] ?? 0,
            ])->all());

            return $order;
        });

        $this->cart->clear();
        Collector::linkOrder($request, $order);
        // Let this browser reopen its own orders.
        $request->session()->push('orders', $order->token);
        $this->mailer->placed($order->load('items'));

        return redirect()->route('orders.show', $order->token)->with('analytics', [Tracking::purchase($order)]);
    }

    protected function ratesFor(?string $country, ?string $state, ?string $postcode): Collection
    {
        if (! $country) {
            return collect();
        }

        return $this->shipping->rates($country, $state, $postcode, $this->cart->total(), $this->cart->count(), $this->cart->hasFreeShippingCoupon());
    }

    protected function publicRates(Collection $rates): array
    {
        return $rates->map(fn (array $r) => collect($r)->except('method')->all())->values()->all();
    }
}
