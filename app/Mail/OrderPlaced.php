<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Order confirmation: one version for the customer, one for the business owner. */
class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public bool $forOwner = false) {}

    public function envelope(): Envelope
    {
        $settings = BusinessSetting::current();
        $business = $settings->business_name;
        // The owner replies straight to the customer; the customer replies to the business.
        $replyTo = $this->forOwner
            ? (filled($this->order->customer_email) ? [new Address($this->order->customer_email, $this->order->customer_name)] : [])
            : (filled($settings->email) ? [new Address($settings->email, $business)] : []);

        return new Envelope(
            // Sent in the store's name (MAIL_FROM_NAME is the app name).
            from: new Address(config('mail.from.address'), $settings->business_name),
            replyTo: $replyTo,
            subject: $this->forOwner
                ? "New order #{$this->order->number} from {$this->order->customer_name}"
                : "Your {$business} order #{$this->order->number}",
        );
    }

    public function content(): Content
    {
        $settings = BusinessSetting::current();

        return new Content(markdown: 'mail.order-placed', with: [
            'detail' => $this->order->loadMissing('items')->toDetail(),
            'settings' => $settings,
            'orderUrl' => $this->forOwner ? route('admin.orders.show', $this->order) : $this->order->url(),
        ]);
    }
}
