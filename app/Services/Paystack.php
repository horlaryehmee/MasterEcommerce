<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paystack online payments.
 *
 * Security rules this class follows:
 *  - The amount is always worked out on the server from the saved order, never
 *    taken from the browser.
 *  - An order is marked paid only after THIS server asks Paystack directly
 *    (GET /transaction/verify) and the reference, exact amount (in kobo),
 *    currency and live/test mode all match what we expected. A redirect or a
 *    webhook on its own is never trusted.
 *  - Webhooks must carry a valid HMAC-SHA512 signature made with the secret key,
 *    compared in constant time, and are then re-verified with the API anyway.
 *  - Settling is idempotent and done under row locks, so a callback and a
 *    webhook arriving together can't double-process a payment.
 *  - Secret keys are stored encrypted (or in .env) and never sent to the browser.
 *    Test and live keys are kept apart, and a key only works in its own mode.
 */
class Paystack
{
    /** Currencies a Paystack account can charge in. */
    public const CURRENCIES = ['NGN', 'GHS', 'ZAR', 'KES', 'USD', 'XOF', 'EGP', 'RWF'];

    public const KEY_PATTERN = '/^sk_(test|live)_[A-Za-z0-9]{20,100}$/';

    public const PUBLIC_KEY_PATTERN = '/^pk_(test|live)_[A-Za-z0-9]{20,100}$/';

    public const MODES = ['test' => 'Test mode', 'live' => 'Live mode'];

    /** Paystack's hosted checkout. We only ever redirect customers there. */
    protected const CHECKOUT_HOST = 'checkout.paystack.com';

    /** The mode in use: .env (PAYSTACK_MODE) wins over the dashboard switch. */
    public function mode(?BusinessSetting $s = null): string
    {
        $mode = config('services.paystack.mode') ?: ($s ?? BusinessSetting::current())->paystack_mode;

        return $mode === 'live' ? 'live' : 'test';
    }

    public function modeFromEnv(): bool
    {
        return in_array(config('services.paystack.mode'), ['test', 'live'], true);
    }

    /** The secret key for a mode (default: the mode in use). .env keys win over saved ones. */
    public function secretKey(?BusinessSetting $s = null, ?string $mode = null): ?string
    {
        $s ??= BusinessSetting::current();
        $mode ??= $this->mode($s);
        $key = config("services.paystack.{$mode}_secret_key") ?: $s->{"paystack_{$mode}_secret_key"};

        // A key only counts for the mode it belongs to: a test key is never used as the live key.
        return is_string($key) && preg_match(self::KEY_PATTERN, $key, $m) && $m[1] === $mode ? $key : null;
    }

    public function keyFromEnv(string $mode): bool
    {
        return filled(config("services.paystack.{$mode}_secret_key"));
    }

    /** The public key for a mode (default: the mode in use). Only accepted for its own mode. */
    public function publicKey(?BusinessSetting $s = null, ?string $mode = null): ?string
    {
        $s ??= BusinessSetting::current();
        $mode ??= $this->mode($s);
        $key = config("services.paystack.{$mode}_public_key") ?: $s->{"paystack_{$mode}_public_key"};

        return is_string($key) && preg_match(self::PUBLIC_KEY_PATTERN, $key, $m) && $m[1] === $mode ? $key : null;
    }

    public function publicKeyFromEnv(string $mode): bool
    {
        return filled(config("services.paystack.{$mode}_public_key"));
    }

    /** Like keyProblem(), for a public key pasted into the field for $mode. */
    public static function publicKeyProblem(string $key, string $mode): ?string
    {
        $key = trim($key);
        $other = $mode === 'live' ? 'test' : 'live';

        return match (true) {
            str_starts_with($key, 'sk_') => 'This is your SECRET key. Paste the public key here instead: it starts with pk_'.$mode.'_. Keep the secret key private.',
            str_starts_with($key, "pk_{$other}_") => 'This is a '.strtoupper($other).' public key. The '.$mode.' public key starts with pk_'.$mode.'_. In Paystack, switch the dashboard to '.ucfirst($mode).' Mode to copy it.',
            ! preg_match(self::PUBLIC_KEY_PATTERN, $key) => 'This doesn’t look like a Paystack public key. It starts with pk_'.$mode.'_ followed by letters and numbers, with no spaces.',
            default => null,
        };
    }

    /** Paystack is switched on and running with test keys (no real money). */
    public function inTestMode(?BusinessSetting $s = null): bool
    {
        $s ??= BusinessSetting::current();

        return (bool) $s->pay_paystack_enabled && $this->mode($s) === 'test';
    }

    /** A key for the current mode and the store's currency is one Paystack can charge. */
    public function ready(?BusinessSetting $s = null): bool
    {
        $s ??= BusinessSetting::current();

        return $this->secretKey($s) !== null && in_array(strtoupper((string) $s->currency_code), self::CURRENCIES, true);
    }

    /**
     * Explains what's wrong with a key pasted into the field for $mode, or null
     * if it looks right. Catches the usual mix-ups: the public key instead of
     * the secret key, or the test key in the live field (and vice versa).
     */
    public static function keyProblem(string $key, string $mode): ?string
    {
        $key = trim($key);
        $other = $mode === 'live' ? 'test' : 'live';

        return match (true) {
            str_starts_with($key, 'pk_') => 'This is your public key. Paste the secret key instead: it starts with sk_'.$mode.'_.',
            str_starts_with($key, "sk_{$other}_") => 'This is a '.strtoupper($other).' key. The '.$mode.' secret key starts with sk_'.$mode.'_. In Paystack, switch the dashboard to '.ucfirst($mode).' Mode to copy it.',
            ! preg_match(self::KEY_PATTERN, $key) => 'This doesn’t look like a Paystack secret key. It starts with sk_'.$mode.'_ followed by letters and numbers, with no spaces.',
            default => null,
        };
    }

    /**
     * Asks Paystack whether the key works. Returns null when it does, otherwise
     * a message for the admin.
     */
    public function checkKey(string $key): ?string
    {
        try {
            $response = Http::baseUrl(config('services.paystack.base_url'))->withToken($key)->acceptJson()
                ->timeout(15)->connectTimeout(8)->get('/bank', ['perPage' => 1]);
        } catch (ConnectionException) {
            return 'Couldn’t reach Paystack to check this key. Check your internet connection and try again.';
        }

        return match (true) {
            $response->successful() && $response->json('status') === true => null,
            in_array($response->status(), [401, 403], true) => 'Paystack rejected this key. It may be mistyped or revoked: copy it again from Paystack → Settings → API Keys & Webhooks.',
            default => 'Paystack couldn’t check this key right now (error '.$response->status().'). Please try again in a moment.',
        };
    }

    /** 12345.6 / "12345.60" → 1234560 (kobo), exactly — no floating-point rounding. */
    public static function toSubunit(string|int|float $amount): int
    {
        $text = is_string($amount) ? trim($amount) : number_format((float) $amount, 2, '.', '');
        if (! preg_match('/^(\d{1,15})(?:\.(\d{1,2})\d*)?$/', $text, $m)) {
            throw new RuntimeException('Invalid amount.');
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /**
     * Start a payment for the order and return Paystack's checkout URL to send
     * the customer to.
     */
    public function initialize(Order $order, ?string $ip = null): string
    {
        if (! $order->awaitsOnlinePayment()) {
            throw new RuntimeException('This order can’t be paid online.');
        }
        if (blank($order->customer_email)) {
            throw new RuntimeException('An email address is needed to pay online.');
        }

        $s = BusinessSetting::current();
        $currency = strtoupper((string) ($order->currency_code ?: $s->currency_code));
        $amount = self::toSubunit($order->total);
        if ($amount < 100) {
            throw new RuntimeException('The order total is too small to pay online.');
        }

        $payment = $order->payments()->create([
            'provider' => 'paystack',
            // Unguessable, unique, and tied to the order number for easy support lookups.
            'reference' => 'NX'.$order->number.'-'.Str::upper(Str::random(24)),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'mode' => $this->mode($s),
            'ip_address' => $ip,
        ]);

        try {
            $response = $this->send($order, $payment, $amount, $currency);
        } catch (ConnectionException $e) {
            $payment->forceFill(['status' => 'failed', 'message' => 'Paystack could not be reached.'])->save();
            Log::warning('Paystack unreachable', ['order' => $order->number, 'error' => $e->getMessage()]);

            throw new RuntimeException('We couldn’t reach the payment provider just now. Please try again in a moment.');
        }

        $url = (string) $response->json('data.authorization_url');
        if (! $response->successful() || ! $response->json('status') || ! $this->isCheckoutUrl($url)) {
            $payment->forceFill(['status' => 'failed', 'message' => Str::limit((string) $response->json('message', 'Could not start the payment.'), 250)])->save();
            Log::warning('Paystack initialize failed', ['order' => $order->number, 'http' => $response->status(), 'message' => $response->json('message')]);

            throw new RuntimeException('We couldn’t start the payment just now. Please try again in a moment.');
        }

        return $url;
    }

    protected function send(Order $order, Payment $payment, int $amount, string $currency): \Illuminate\Http\Client\Response
    {
        return $this->request()->post('/transaction/initialize', [
            'email' => $order->customer_email,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $payment->reference,
            'callback_url' => route('payments.paystack.callback'),
            'metadata' => [
                'order_number' => $order->number,
                // "Cancel payment" on Paystack comes back through the callback, which checks
                // the real status, so the customer is told clearly that nothing was paid.
                'cancel_action' => route('payments.paystack.callback', ['reference' => $payment->reference, 'cancelled' => 1]),
                'custom_fields' => [
                    ['display_name' => 'Order', 'variable_name' => 'order_number', 'value' => '#'.$order->number],
                ],
            ],
        ]);
    }

    /**
     * Ask Paystack for the transaction's real outcome and record it.
     * Returns the payment (fresh), whatever its status.
     */
    public function verify(Payment $payment): Payment
    {
        if ($payment->status === 'success') {
            return $payment;
        }

        try {
            // Checked with the key of the mode it was made in (a test payment stays a test payment).
            $response = $this->request($payment->mode)->get('/transaction/verify/'.rawurlencode($payment->reference));
        } catch (ConnectionException $e) {
            Log::warning('Paystack verify unreachable', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

            return $payment;
        }

        // Paystack has no transaction with this reference: the payment page was never completed.
        if (in_array($response->status(), [400, 404], true) && str_contains(strtolower((string) $response->json('message')), 'not found')) {
            $payment->forceFill(['status' => 'abandoned', 'message' => 'Payment page closed before paying.', 'verified_at' => now()])->save();

            return $payment;
        }

        if (! $response->successful() || ! $response->json('status') || ! is_array($response->json('data'))) {
            Log::warning('Paystack verify failed', ['reference' => $payment->reference, 'http' => $response->status(), 'message' => $response->json('message')]);

            return $payment;
        }

        return $this->settle($payment, $response->json('data'));
    }

    /**
     * Record a verified transaction. Only marks the order paid when every
     * detail matches what we asked for; anything odd is flagged for review.
     */
    protected function settle(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'success') {
                return $payment;
            }
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            $status = (string) ($data['status'] ?? '');
            $paid = (int) ($data['amount'] ?? 0);
            $payment->forceFill([
                'gateway_id' => isset($data['id']) ? (string) $data['id'] : null,
                'channel' => isset($data['channel']) ? Str::limit((string) $data['channel'], 40, '') : null,
                'amount_paid' => $paid ?: null,
                'message' => Str::limit((string) ($data['gateway_response'] ?? ''), 250) ?: null,
                'verified_at' => now(),
            ]);

            if ($status !== 'success') {
                $payment->status = in_array($status, ['failed', 'abandoned'], true) ? $status : 'pending';
                $payment->save();

                return $payment;
            }

            $problems = array_filter([
                ($data['reference'] ?? null) !== $payment->reference ? 'reference' : null,
                $paid !== $payment->amount ? 'amount' : null,
                strtoupper((string) ($data['currency'] ?? '')) !== $payment->currency ? 'currency' : null,
                $payment->mode && isset($data['domain']) && $data['domain'] !== $payment->mode ? 'mode' : null,
            ]);

            if ($problems) {
                $payment->status = 'flagged';
                $payment->message = 'Mismatch: '.implode(', ', $problems).'. Not marked as paid; check in your Paystack dashboard.';
                $payment->save();
                Log::critical('Paystack payment mismatch', ['order' => $order->number, 'reference' => $payment->reference, 'problems' => $problems, 'expected' => $payment->amount, 'paid' => $paid]);
                DB::afterCommit(fn () => app(AdminAlerts::class)->paymentFlagged($payment->setRelation('order', $order)));

                return $payment;
            }

            $payment->status = 'success';
            $payment->save();

            if ($order->payment_status !== 'paid') {
                $wasAwaiting = in_array($order->status, ['awaiting_payment', 'cancelled'], true);
                $order->payment_status = 'paid';
                $order->paid_at = now();
                // Now a real order to fulfil. (Even one auto-cancelled while unpaid: the money arrived.)
                if ($wasAwaiting) {
                    $order->status = 'pending';
                }
                $order->save();
                // First confirmation for the customer and the owner, now that it's paid.
                DB::afterCommit(function () use ($order, $wasAwaiting) {
                    if ($wasAwaiting) {
                        app(OrderMailer::class)->placed($order->load('items'));
                        app(AdminAlerts::class)->newOrder($order);
                    } else {
                        app(OrderMailer::class)->updated($order);
                    }
                });
            }

            return $payment;
        });
    }

    /** Constant-time check of the x-paystack-signature header against the raw request body. */
    public function validSignature(string $payload, ?string $signature): bool
    {
        if (! is_string($signature) || ! preg_match('/^[a-f0-9]{128}$/i', $signature)) {
            return false;
        }
        // Paystack signs with the key of the mode the event happened in.
        foreach (array_keys(self::MODES) as $mode) {
            $secret = $this->secretKey(null, $mode);
            if ($secret && hash_equals(hash_hmac('sha512', $payload, $secret), strtolower($signature))) {
                return true;
            }
        }

        return false;
    }

    protected function isCheckoutUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === self::CHECKOUT_HOST;
    }

    protected function request(?string $mode = null): PendingRequest
    {
        $secret = $this->secretKey(null, in_array($mode, ['test', 'live'], true) ? $mode : null) ?? throw new RuntimeException('Paystack is not set up.');

        return Http::baseUrl(config('services.paystack.base_url'))
            ->withToken($secret)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(10)
            // TLS certificates are always verified (Laravel's default); never turn that off.
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
    }
}
