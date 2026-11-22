<?php

namespace App\Http\Controllers\Storefront;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Support\Seo;
use App\Support\WhatsApp;
use Inertia\Response;

/** The customer's order page, reached through the private link (token) shown after checkout and in the email. */
class OrderController extends StorefrontController
{
    public function show(string $token): Response
    {
        $order = Order::query()->where('token', $token)->with('items')->firstOrFail();
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
            'justPlaced' => in_array($token, (array) session('orders', []), true) && $order->created_at?->gt(now()->subHour()),
        ], Seo::make()->title('Order #'.$order->number)->noindex());
    }
}
