<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Paystack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cancels online-payment orders that were never paid, after
 * Order::UNPAID_CANCEL_HOURS. Each one is checked with Paystack first, so a
 * payment that did go through (e.g. a slow bank transfer) is recorded instead.
 * Runs hourly from the scheduler.
 */
class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';

    protected $description = 'Cancel online-payment orders left unpaid for more than '.Order::UNPAID_CANCEL_HOURS.' hours';

    public function handle(Paystack $paystack): int
    {
        $cancelled = 0;

        Order::query()
            ->where('status', 'awaiting_payment')
            ->where('payment_status', 'unpaid')
            ->where('created_at', '<', now()->subHours(Order::UNPAID_CANCEL_HOURS))
            ->with('payments')
            ->lazyById(100)
            ->each(function (Order $order) use ($paystack, &$cancelled) {
                // Last check: a payment may have succeeded without us hearing (no webhook).
                $defer = $order->payments->contains('status', 'flagged');
                foreach ($order->payments->whereIn('status', ['pending', 'abandoned']) as $payment) {
                    if (! in_array($paystack->verify($payment)?->status, ['abandoned', 'failed'], true)) {
                        $defer = true;
                    }
                }
                if ($defer) {
                    return;
                }

                DB::transaction(function () use ($order, &$cancelled) {
                    $latestCheckedId = $order->payments->max('id') ?? 0;
                    $order = Order::query()->lockForUpdate()->find($order->id);
                    if (! $order || $order->status !== 'awaiting_payment' || $order->payment_status !== 'unpaid') {
                        return;
                    }
                    if ($order->payments()->where('id', '>', $latestCheckedId)->exists()) {
                        return;
                    }
                    // Quietly: the customer was never sent an order confirmation.
                    $order->update(['status' => 'cancelled', 'notes' => trim($order->notes."\n[Cancelled automatically: payment not completed]")]);
                    $cancelled++;
                });
            });

        $this->info("{$cancelled} unpaid ".($cancelled === 1 ? 'order' : 'orders').' cancelled.');

        return self::SUCCESS;
    }
}
