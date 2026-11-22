<?php

namespace App\Mail\Concerns;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Support\Color;
use App\Support\EmailImage;

/** The store's branding and the order's items, ready for the email templates. */
trait BrandedEmail
{
    protected function brand(): array
    {
        $s = BusinessSetting::current();
        $color = Color::sanitize($s->primary_color, '#111827');

        return [
            'name' => $s->business_name,
            'logo' => EmailImage::url($s->logo_path, 180, true),
            'color' => $color,
            'on_color' => Color::contrast($color),
            'email' => $s->email,
            'phone' => $s->phone,
            'address' => $s->address,
            'website' => rtrim(config('app.url'), '/'),
            'website_label' => preg_replace('#^https?://(www\.)?#i', '', rtrim(config('app.url'), '/')),
        ];
    }

    /** Order lines with email-safe product thumbnails. */
    protected function emailItems(Order $order): array
    {
        $detail = $order->loadMissing('items')->toDetail();

        return collect($detail['items'])->map(function (array $item) use ($order) {
            $path = $order->items->firstWhere('id', $item['id'])?->image_path;

            return $item + ['email_image' => EmailImage::url($path, 72)];
        })->all();
    }
}
