<?php

namespace App\Services;

use App\Mail\OrderPlaced;
use App\Mail\OrderUpdated;
use App\Models\BusinessSetting;
use App\Models\Order;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Automatic order emails. Sent after the response so a slow mail server never
 * holds up checkout or the admin, and a mail failure never loses an order.
 */
class OrderMailer
{
    /** New order: a confirmation for the customer and a notice for the business. */
    public function placed(Order $order): void
    {
        $s = BusinessSetting::current();

        if ($s->email_owner_new_order && ($to = $s->orderNotificationAddress())) {
            $this->send($to, new OrderPlaced($order, true), $order, 'owner');
        }
        if ($s->email_customer_new_order && filled($order->customer_email)) {
            $this->send($order->customer_email, new OrderPlaced($order), $order, 'customer');
        }
    }

    /**
     * After the admin changes an order: one email to the customer for the most
     * important change (status first, then payment). Moves back to "New" or
     * "Unpaid" are corrections and stay silent. Returns whether an email was sent.
     */
    public function updated(Order $order): bool
    {
        if (! BusinessSetting::current()->email_customer_updates || blank($order->customer_email)) {
            return false;
        }

        $event = collect(['status', 'payment_status'])
            ->filter(fn ($field) => $order->wasChanged($field))
            ->map(fn ($field) => ($field === 'status' ? 'status.' : 'payment.').$order->{$field})
            ->first(fn ($event) => in_array($event, OrderUpdated::EVENTS, true));

        if (! $event) {
            return false;
        }
        $this->send($order->customer_email, new OrderUpdated($order, $event), $order, 'customer');

        return true;
    }

    protected function send(string $to, Mailable $mail, Order $order, string $recipient): void
    {
        defer(function () use ($to, $mail, $order, $recipient) {
            try {
                Mail::to($to)->send($mail);
            } catch (Throwable $e) {
                Log::warning('Order email failed', ['order' => $order->number, 'to' => $recipient, 'mail' => class_basename($mail), 'error' => $e->getMessage()]);
            }
        });
    }
}
