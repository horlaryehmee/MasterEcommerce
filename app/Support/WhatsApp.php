<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;

/** Builds standard WhatsApp click-to-chat links (https://wa.me/...). */
class WhatsApp
{
    /** Country calling codes, to turn local numbers ("0801…") into international ones. */
    public const CALLING_CODES = [
        'NG' => '234', 'GH' => '233', 'KE' => '254', 'ZA' => '27', 'UG' => '256', 'TZ' => '255', 'RW' => '250',
        'CM' => '237', 'SN' => '221', 'CI' => '225', 'BJ' => '229', 'TG' => '228', 'ZM' => '260', 'ZW' => '263',
        'EG' => '20', 'MA' => '212', 'ET' => '251', 'GB' => '44', 'IE' => '353', 'FR' => '33', 'DE' => '49',
        'NL' => '31', 'IT' => '39', 'ES' => '34', 'AE' => '971', 'SA' => '966', 'IN' => '91', 'PK' => '92',
        'AU' => '61', 'NZ' => '64', 'US' => '1', 'CA' => '1',
    ];

    /**
     * Normalise a phone number to the international digits-only format wa.me
     * expects, e.g. "+234 (801) 234-5678" -> "2348012345678". With a country,
     * a local number is converted too ("0801 234 5678" + NG -> "2348012345678").
     */
    public static function normalize(?string $number, ?string $country = null): ?string
    {
        $raw = trim((string) $number);
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif ($country && ! str_starts_with($raw, '+') && ($code = self::CALLING_CODES[strtoupper($country)] ?? null)) {
            if (str_starts_with($digits, '0')) {
                $digits = $code.substr($digits, 1);
            } elseif ($code === '1' && strlen($digits) === 10) {
                $digits = '1'.$digits;
            }
        }

        return self::isValid($digits) ? $digits : null;
    }

    /** Link to chat with a customer (not the business). */
    public static function chatLink(?string $number, ?string $country = null, ?string $message = null): ?string
    {
        $digits = self::normalize($number, $country ?: BusinessSetting::current()->default_country);
        if (! $digits) {
            return null;
        }
        $message = trim((string) $message);

        return 'https://wa.me/'.$digits.($message === '' ? '' : '?text='.rawurlencode($message));
    }

    /** International format: country code + number, 8 to 15 digits, no leading zero. */
    public static function isValid(?string $digits): bool
    {
        return (bool) preg_match('/^[1-9]\d{7,14}$/', (string) $digits);
    }

    public static function link(?string $message = null, ?BusinessSetting $settings = null): ?string
    {
        $settings ??= BusinessSetting::current();
        $number = self::normalize($settings->whatsapp_number);
        if (! $number) {
            return null;
        }

        $url = 'https://wa.me/'.$number;
        $message = trim((string) $message);

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }

    public static function generalLink(?BusinessSetting $settings = null): ?string
    {
        $settings ??= BusinessSetting::current();

        return self::link(self::fill($settings->whatsapp_general_message ?: BusinessSetting::DEFAULT_GENERAL_MESSAGE, [], $settings), $settings);
    }

    public static function productLink(Product $product, ?BusinessSetting $settings = null): ?string
    {
        $settings ??= BusinessSetting::current();

        return self::link(self::productMessage($product, $settings), $settings);
    }

    public static function productMessage(Product $product, ?BusinessSetting $settings = null): string
    {
        $settings ??= BusinessSetting::current();

        return self::fill($settings->whatsapp_product_message ?: BusinessSetting::DEFAULT_PRODUCT_MESSAGE, [
            '{product_name}' => $product->name,
            '{product_price}' => Money::format($product->price, $settings),
            '{product_url}' => $product->url(),
        ], $settings);
    }

    /** Link that sends the full order summary to the business. Requires items loaded. */
    public static function orderLink(Order $order, ?BusinessSetting $settings = null): ?string
    {
        $settings ??= BusinessSetting::current();

        return self::link(self::orderMessage($order, $settings), $settings);
    }

    public static function orderMessage(Order $order, ?BusinessSetting $settings = null): string
    {
        $settings ??= BusinessSetting::current();
        $money = fn ($amount) => Money::format($amount, $settings);

        $lines = ["Hello {$settings->business_name}, I just placed order #{$order->number} on your website.", ''];
        foreach ($order->items as $item) {
            $name = $item->product_name.($item->variant_label ? " ({$item->variant_label})" : '');
            $lines[] = "• {$item->quantity} × {$name} — {$money($item->line_total)}";
        }
        $lines[] = '';
        $lines[] = 'Subtotal: '.$money($order->subtotal);
        if ($order->coupon_code) {
            $lines[] = "Coupon ({$order->coupon_code}): ".($order->hasDiscount() ? '−'.$money($order->discount_total) : 'applied');
        }
        $lines[] = ($order->shipping_method ?: 'Delivery').': '.$order->shippingCostLabel();
        $lines[] = 'Total: '.$money($order->total);
        $lines[] = '';
        $lines[] = 'Name: '.$order->customer_name;
        $lines[] = 'Phone: '.$order->customer_phone;
        if ($order->customer_whatsapp && $order->customer_whatsapp !== $order->customer_phone) {
            $lines[] = 'WhatsApp: '.$order->customer_whatsapp;
        }
        $lines[] = $order->isPickup()
            ? 'I will pick up my order'.($order->address ? " from {$order->address}." : '.')
            : 'Deliver to: '.$order->addressLine();
        $lines[] = 'Payment: '.(Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method);
        if (filled($order->notes)) {
            $lines[] = 'Note: '.$order->notes;
        }

        return implode("\n", $lines);
    }

    /** Replace message placeholders. Unknown placeholders are left untouched. */
    public static function fill(string $template, array $values, BusinessSetting $settings): string
    {
        return trim(strtr($template, $values + [
            '{business_name}' => $settings->business_name,
        ]));
    }
}
