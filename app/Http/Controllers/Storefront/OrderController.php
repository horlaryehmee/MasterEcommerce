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

        return $this->page('Storefront/OrderConfirmation', [
            'order' => $order->toDetail(),
            'whatsappUrl' => WhatsApp::orderLink($order, $s),
            'bank' => $order->payment_method === 'bank_transfer' && filled($s->bank_account_number) ? [
                'name' => $s->bank_name,
                'account_name' => $s->bank_account_name,
                'account_number' => $s->bank_account_number,
            ] : null,
            'justPlaced' => in_array($token, (array) session('orders', []), true) && $order->created_at?->gt(now()->subHour()),
        ], Seo::make()->title('Order #'.$order->number)->noindex());
    }
}
