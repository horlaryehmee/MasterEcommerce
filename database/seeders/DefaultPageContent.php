<?php

namespace Database\Seeders;

/**
 * Starter text for the informational pages. Written generically so it suits
 * any business; placeholders such as {business_name} are filled in with the
 * business settings when the page is displayed. Edit in Admin → Website Content.
 */
class DefaultPageContent
{
    public static function for(string $slug): string
    {
        return match ($slug) {
            'about' => <<<'MD'
## Our story

{business_name} was started with a simple idea: buying quality products should be easy, honest and personal. We know how frustrating it can be to shop online and receive something that looks nothing like the picture, or to send a message and never hear back. So we decided to do things differently.

Every product in our catalogue is carefully chosen and checked before it's offered to you, and every customer gets a real conversation with a real person — not a faceless checkout page.
MD,
            'contact' => <<<'MD'
We'd love to hear from you. The fastest way to reach us is on **WhatsApp** — tap the button on this page and we'll reply as soon as possible.

You can also call, email or visit us using the details below.
MD,
            'faq' => 'Find quick answers to the questions our customers ask most often. Can\'t find what you\'re looking for? Send us a message on WhatsApp — we\'re happy to help.',
            'shipping-and-delivery' => <<<'MD'
## Delivery areas

We deliver to most locations. Delivery options, costs and timelines depend on your location and the items ordered, and are confirmed with you on WhatsApp before you pay.

## Delivery times

Orders are usually dispatched within 1–3 working days after payment is confirmed. Delivery times vary by location.

## Pick-up

Where available, you may also pick up your order from our location. Ask us for details.

## Questions?

Contact us on WhatsApp or by phone for help with any delivery.
MD,
            'privacy-policy' => <<<'MD'
This Privacy Policy explains how {business_name} handles information when you use this website.

## Information we collect

This website does not require you to create an account and does not collect payment details. When you contact us through WhatsApp, phone or email, we receive the information you choose to share, such as your name, phone number and message.

## How we use information

We use the information you share only to respond to your enquiries, process your orders and provide customer support.

## Cookies

This website uses essential cookies required for it to function securely. We do not use advertising cookies.

## Third-party services

When you tap a WhatsApp or social media link, you leave this website and that service's own privacy policy applies.

## Contact

For any privacy questions, contact us at {email}.
MD,
            'terms-and-conditions' => <<<'MD'
By using this website you agree to the following terms.

## Product information

We make every effort to display our products, prices and availability accurately. However, errors may occur and product images are for illustration. All orders are confirmed directly with you before payment.

## Prices

Prices are shown in the currency displayed on the website and may change without notice. The price confirmed at the time of your enquiry applies to your order.

## Orders

This website is a product catalogue. Orders are arranged with {business_name} directly through WhatsApp, phone or email and are subject to availability.

## Returns

Returns and exchanges are handled case by case. Please contact us promptly if you have any issue with your purchase.

## Changes

We may update these terms from time to time. The latest version will always be available on this page.
MD,
            default => '',
        };
    }

    /** Starter questions for the FAQ page. Answers support simple formatting and placeholders. */
    public static function faqItems(): array
    {
        return [
            [
                'question' => 'How do I place an order?',
                'answer' => "Find the product you like and tap **Enquire on WhatsApp** on its page. A message with the product name, price and link opens in WhatsApp — just send it.\n\nWe'll reply to confirm availability, the total cost (including any delivery) and how to pay.",
            ],
            [
                'question' => 'Do I need to create an account?',
                'answer' => 'No. You can browse every product and contact us directly — no sign-up, passwords or online checkout required.',
            ],
            [
                'question' => 'Are the prices on the website up to date?',
                'answer' => 'We keep our prices current, but prices and stock can change quickly. We always confirm the final price with you before you pay, so there are no surprises.',
            ],
            [
                'question' => 'How can I pay?',
                'answer' => "Available payment options are confirmed when you contact us. For your safety, we **never** ask for card details or passwords on this website or in a chat.",
            ],
            [
                'question' => 'Do you deliver, and how much does it cost?',
                'answer' => 'Yes, we deliver to many locations. The cost and timeline depend on where you are and what you order, and we confirm both before you pay. See our [Shipping and Delivery](/shipping-and-delivery) page for details.',
            ],
            [
                'question' => 'How long will delivery take?',
                'answer' => 'Orders are usually dispatched within 1–3 working days after payment is confirmed. Delivery times then vary by location — ask us for an estimate for your area.',
            ],
            [
                'question' => 'Can I pick up my order myself?',
                'answer' => 'Where pick-up is available, you are welcome to collect your order. Message us to arrange a convenient time.',
            ],
            [
                'question' => 'What if a product is out of stock?',
                'answer' => 'Tap **Ask about availability** on the product page. We can tell you when it will be back or suggest a similar item.',
            ],
            [
                'question' => 'Can I return or exchange an item?',
                'answer' => "Please contact us as soon as possible if there's any problem with your purchase. Returns and exchanges are handled case by case, in line with our [Terms and Conditions](/terms-and-conditions).",
            ],
            [
                'question' => 'Do you offer discounts for bulk orders?',
                'answer' => "If you'd like to buy several items or order for a business, send us a message with what you need and we'll do our best to offer a good price.",
            ],
        ];
    }
}
