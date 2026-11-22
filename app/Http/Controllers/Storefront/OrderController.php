<?php

namespace App\Http\Controllers\Storefront;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Support\Seo;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The customer's order page, reached through the private link (token) in the order email.
 *
 * Straight after checkout (or a successful online payment) the customer first lands on
 * /order/thanks: the same page without the token in the address, so ad pixels can record
 * the purchase without ever seeing the private link. It only shows the order this browser
 * just placed; afterwards it hands over to the private link.
 */
class OrderController extends StorefrontController
{
    /** How long /order/thanks keeps showing the order just placed in this browser. */
    public const THANKS_MINUTES = 120;

    public function show(string $token): Response
    {
        return $this->render(Order::query()->where('token', $token)->with('items')->firstOrFail());
    }

    public function thanks(Request $request): Response|RedirectResponse
    {
        $token = collect((array) $request->session()->get('orders', []))->last();
        $order = is_string($token) ? Order::query()->where('token', $token)->with('items')->first() : null;
        if (! $order) {
            return redirect()->route('home');
        }
        if ($order->created_at?->lt(now()->subMinutes(self::THANKS_MINUTES))) {
            return redirect()->route('orders.show', $order->token);
        }

        return $this->render($order, privateUrl: true);
    }

    protected function render(Order $order, bool $privateUrl = false): Response
    {
        $token = $order->token;
        $s = BusinessSetting::current();

        // Still confirming (bank transfer, USSD…): ask Paystack again, at most every 10 seconds per payment.
        $latest = $order->payment_method === 'paystack' && $order->payment_status !== 'paid' ? $order->payments()->first() : null;
        if ($latest?->status === 'pending' && \Illuminate\Support\Facades\Cache::add('paystack.recheck.'.$latest->id, 1, 10)) {
            app(\App\Services\Paystack::class)->verify($latest);
            $order->refresh();
        }

        return $this->page('Storefront/OrderConfirmation', [
            'order' => $order->toDetail(),
            'whatsappUrl' => WhatsApp::orderLink($order, $s),
            // Bank transfer: pay now. Pay on delivery: shown so the customer can transfer when the order arrives.
            'bank' => in_array($order->payment_method, ['bank_transfer', 'delivery'], true) && filled($s->bank_account_number) ? [
                'name' => $s->bank_name,
                'account_name' => $s->bank_account_name,
                'account_number' => $s->bank_account_number,
            ] : null,
            // Unpaid Paystack order: "Pay now" starts a fresh, server-verified payment.
            'payUrl' => $order->awaitsOnlinePayment() && app(\App\Services\Paystack::class)->ready($s) ? route('orders.pay', $order->token) : null,
            'paymentState' => $order->onlinePaymentState(),
            // Bank transfer, not yet reported as paid: the "Complete your payment" step instead of the thank-you page.
            'transferStep' => $order->needsTransferStep() && filled($s->bank_account_number) ? route('orders.payment-sent', $order->token) : null,
            'paymentReported' => $order->payment_method === 'bank_transfer' && $order->payment_status === 'unpaid' && $order->payment_reported_at !== null,
            // Bank transfer: upload a receipt, and see whether it has been checked.
            'receipts' => $order->receipts()->get()->map->toCustomer(),
            'receiptUpload' => $order->acceptsReceipts() && filled($s->bank_account_number) ? [
                'url' => route('orders.receipt', $order->token),
                'remaining' => max(0, \App\Models\PaymentReceipt::MAX_PER_ORDER - $order->receipts()->count()),
                'max_mb' => (int) (\App\Models\PaymentReceipt::MAX_KB / 1024),
                'just_uploaded' => (bool) session('receipt_uploaded'),
            ] : null,
            'justPlaced' => in_array($token, (array) session('orders', []), true) && $order->created_at?->gt(now()->subHour()),
            // On /order/thanks: the private link to keep (this address only works in this browser, for a while).
            'privateUrl' => $privateUrl ? $order->url() : null,
        ], Seo::make()->title('Order #'.$order->number)->noindex());
    }
}
