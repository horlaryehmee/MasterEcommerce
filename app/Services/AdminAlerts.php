<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PushSubscription;
use App\Support\AppIcon;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phone/desktop notifications to the admin. Sent after the response (defer),
 * so customers never wait for them, and failures never break checkout.
 */
class AdminAlerts
{
    public function __construct(protected WebPush $push) {}

    /** A real order to fulfil: placed (pay on delivery, transfer…) or paid online. */
    public function newOrder(Order $order): void
    {
        $this->later(fn () => [
            'title' => "New order #{$order->number} · ".Money::format($order->total),
            'body' => $order->customer_name.' · '.$order->itemCount().' '.($order->itemCount() === 1 ? 'item' : 'items').' · '.(Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method).($order->payment_status === 'paid' ? ' (paid)' : ''),
            'url' => route('admin.orders.show', $order, false),
            'tag' => 'order-'.$order->id,
        ]);
    }

    /** Paystack reported a payment that doesn't match the order. */
    public function paymentFlagged(Payment $payment): void
    {
        $order = $payment->order;
        $this->later(fn () => [
            'title' => "Check payment for order #{$order->number}",
            'body' => 'The amount or currency Paystack reported doesn’t match the order. It was not marked as paid.',
            'url' => route('admin.orders.show', $order, false),
            'tag' => 'payment-'.$payment->id,
        ]);
    }

    public function test(PushSubscription $subscription): bool
    {
        return $this->push->send([$subscription], $this->payload([
            'title' => 'Notifications are on',
            'body' => 'You’ll get an alert like this for every new order.',
            'url' => '/admin/orders',
            'tag' => 'test',
        ])) === 1;
    }

    protected function later(callable $make): void
    {
        if (! PushSubscription::query()->exists()) {
            return;
        }
        defer(function () use ($make) {
            try {
                $this->push->notify($this->payload($make()));
            } catch (Throwable $e) {
                Log::warning('Admin notification failed', ['error' => $e->getMessage()]);
            }
        });
    }

    /** Adds the admin app icon and the number for the app-icon badge (new orders waiting). */
    protected function payload(array $data): array
    {
        return $data + [
            'icon' => AppIcon::url('admin', 192) ?? route('pwa.icon', ['app' => 'admin']),
            'badge' => Order::query()->where('status', 'pending')->count(),
        ];
    }
}
