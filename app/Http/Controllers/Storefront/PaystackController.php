<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Paystack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Customer-facing Paystack routes. None of them trusts what the browser or the
 * webhook body says about the payment: each one asks Paystack's API (see
 * App\Services\Paystack::verify) before anything is marked paid.
 */
class PaystackController extends Controller
{
    public const REFERENCE = '/^NX\d{1,12}-[A-Z0-9]{24}$/';

    public function __construct(protected Paystack $paystack) {}

    /** "Pay now" on the order page (a new attempt for an unpaid Paystack order). */
    public function pay(Request $request, string $token): SymfonyResponse
    {
        $order = Order::query()->where('token', $token)->firstOrFail();
        if (! $order->awaitsOnlinePayment()) {
            return redirect()->route('orders.show', $token);
        }

        try {
            return Inertia::location($this->paystack->initialize($order, $request->ip()));
        } catch (RuntimeException $e) {
            return redirect()->route('orders.show', $token)->with('error', $e->getMessage());
        }
    }

    /** Paystack sends the customer back here: ?reference=… */
    public function callback(Request $request): RedirectResponse
    {
        $reference = (string) $request->query('reference', $request->query('trxref', ''));
        $payment = preg_match(self::REFERENCE, $reference) ? Payment::query()->where('reference', $reference)->with('order')->first() : null;
        abort_unless($payment && $payment->order, 404);

        $payment = $this->paystack->verify($payment);
        // The customer pressed "Cancel" on Paystack. Paystack may still list a bank
        // transfer as waiting; we treat it as not paid (a transfer that does arrive
        // later is still recorded through the webhook).
        if ($request->boolean('cancelled') && $payment->status === 'pending') {
            $payment->forceFill(['status' => 'abandoned', 'message' => 'Cancelled by the customer on Paystack.'])->save();
        }
        $order = $payment->order()->first();
        $to = redirect()->route('orders.show', $order->token);

        return match ($payment->status) {
            'success' => $to->with('success', 'Payment received. Thank you!')->with('analytics', [\App\Support\Tracking::purchase($order)]),
            'pending' => $to,
            'flagged' => $to->with('error', 'We received a payment that doesn’t match this order. We’ll check it and contact you.'),
            'abandoned' => $to->with('error', 'Payment cancelled. You haven’t been charged.'),
            default => $to->with('error', 'Your payment didn’t go through. You haven’t been charged.'),
        };
    }

    /**
     * Paystack → server notification. Signed with HMAC-SHA512 of the raw body
     * using the secret key; unsigned or forged requests are rejected.
     */
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        if (strlen($payload) > 100_000 || ! $this->paystack->validSignature($payload, $request->header('x-paystack-signature'))) {
            Log::notice('Paystack webhook rejected: bad signature', ['ip' => $request->ip()]);

            return response('Invalid signature', 401);
        }

        $event = json_decode($payload, true);
        $reference = (string) data_get($event, 'data.reference', '');

        if (data_get($event, 'event') === 'charge.success' && preg_match(self::REFERENCE, $reference)) {
            $payment = Payment::query()->where('reference', $reference)->first();
            // Don't take the webhook's word for it either: verify with the API.
            $payment && $this->paystack->verify($payment);
        }

        // Always 200 for a correctly signed event, so Paystack stops retrying it.
        return response('OK', 200);
    }
}
