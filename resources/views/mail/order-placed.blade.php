<x-mail::message>
@if ($forOwner)
# New order #{{ $detail['number'] }}

{{ $detail['customer_name'] }} placed an order on your website.
@else
# Thank you for your order!

Hi {{ $detail['customer_name'] }}, we've received your order **#{{ $detail['number'] }}** and will be in touch to confirm it.
@endif

<x-mail::table>
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach ($detail['items'] as $item)
| {{ $item['name'] }}{{ $item['variant'] ? ' — '.$item['variant'] : '' }} | {{ $item['quantity'] }} | {{ $item['line_total'] }} |
@endforeach
| Subtotal | | {{ $detail['subtotal'] }} |
@if ($detail['coupon_code'])
| Coupon ({{ $detail['coupon_code'] }}) | | {{ $detail['discount'] ? '−'.$detail['discount'] : 'Applied' }} |
@endif
| {{ $detail['shipping_method'] ?: 'Delivery' }} | | {{ $detail['shipping_cost'] }} |
| **Total** | | **{{ $detail['total'] }}** |
</x-mail::table>

**{{ $detail['fulfilment'] === 'pickup' ? 'Pickup from' : 'Deliver to' }}:**
{{ $detail['address_line'] ?: 'We will share the pickup details with you.' }}

**Payment:** {{ $detail['payment_method_label'] }}

@if (! $forOwner && $detail['payment_method'] === 'bank_transfer' && $settings->bank_account_number)
Please transfer **{{ $detail['total'] }}** to:

{{ $settings->bank_name }}<br>
{{ $settings->bank_account_name }}<br>
**{{ $settings->bank_account_number }}**

Use **#{{ $detail['number'] }}** as the payment reference.
@endif

@if (! $forOwner && $detail['payment_method'] === 'delivery')
Pay **{{ $detail['total'] }}** with cash or bank transfer when your order {{ $detail['fulfilment'] === 'pickup' ? 'is ready' : 'arrives' }}.
@if ($settings->bank_account_number)
To pay by transfer, use:

{{ $settings->bank_name }}<br>
{{ $settings->bank_account_name }}<br>
**{{ $settings->bank_account_number }}**

Reference: **#{{ $detail['number'] }}**
@endif
@endif

@if ($forOwner)
**Customer:** {{ $detail['customer_name'] }} · {{ $detail['customer_phone'] }}{{ $detail['customer_whatsapp'] && $detail['customer_whatsapp'] !== $detail['customer_phone'] ? ' · WhatsApp '.$detail['customer_whatsapp'] : '' }}{{ $detail['customer_email'] ? ' · '.$detail['customer_email'] : '' }}
@endif

@if ($detail['notes'])
**Note:** {{ $detail['notes'] }}
@endif

<x-mail::button :url="$orderUrl">
{{ $forOwner ? 'Open order in admin' : 'View your order' }}
</x-mail::button>

{{ $settings->business_name }}
</x-mail::message>
