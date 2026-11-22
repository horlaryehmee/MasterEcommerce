<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Mail\Concerns\BrandedEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the customer their order moved on: confirmed, out for delivery (or
 * ready for pickup), completed, cancelled, payment received or refunded.
 */
class OrderUpdated extends Mailable
{
    use BrandedEmail, Queueable, SerializesModels;

    /** Events that email the customer: "status.confirmed", "payment.paid", ... */
    public const EVENTS = [
        'status.confirmed', 'status.shipped', 'status.completed', 'status.cancelled',
        'payment.paid', 'payment.refunded',
    ];

    public function __construct(public Order $order, public string $event) {}

    public function envelope(): Envelope
    {
        $settings = BusinessSetting::current();

        return new Envelope(
            // Sent in the store's name (MAIL_FROM_NAME is the app name).
            from: new Address(config('mail.from.address'), $settings->business_name),
            subject: "{$this->headline()} — {$settings->business_name} order #{$this->order->number}",
            replyTo: filled($settings->email) ? [new Address($settings->email, $settings->business_name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-updated', text: 'mail.order-updated-text', with: [
            'detail' => $this->order->loadMissing('items')->toDetail(),
            'items' => $this->emailItems($this->order),
            'brand' => $this->brand(),
            'event' => $this->event,
            'settings' => BusinessSetting::current(),
            'headline' => $this->headline(),
            'intro' => $this->message(),
            'orderUrl' => $this->order->url(),
        ]);
    }

    public function headline(): string
    {
        return match ($this->event) {
            'status.confirmed' => 'Your order is confirmed',
            'status.shipped' => $this->order->isPickup() ? 'Your order is ready for pickup' : 'Your order is on its way',
            'status.completed' => 'Your order is complete',
            'status.cancelled' => 'Your order has been cancelled',
            'payment.paid' => 'Payment received',
            'payment.refunded' => 'Your payment has been refunded',
            default => 'Your order has been updated',
        };
    }

    protected function message(): string
    {
        $o = $this->order;

        return match ($this->event) {
            'status.confirmed' => "Good news — we've confirmed order #{$o->number} and are getting it ready.",
            'status.shipped' => $o->isPickup()
                ? "Order #{$o->number} is packed and waiting for you at: {$o->addressLine()}"
                : "Order #{$o->number} is out for delivery to: {$o->addressLine()}",
            'status.completed' => "Order #{$o->number} is complete. Thank you for shopping with us — we hope you love it!",
            'status.cancelled' => "Order #{$o->number} has been cancelled. If you didn't expect this or have already paid, please get in touch and we'll sort it out.",
            'payment.paid' => "We've received your payment for order #{$o->number}. Thank you!",
            'payment.refunded' => "We've refunded your payment for order #{$o->number}. Depending on your bank, it can take a few days to show.",
            default => "There's an update on order #{$o->number}.",
        };
    }
}
