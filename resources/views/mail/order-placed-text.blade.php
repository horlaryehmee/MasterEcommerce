{{ $forOwner ? 'New order #'.$detail['number'] : 'Thank you for your order!' }}

@if ($forOwner)
{{ $detail['customer_name'] }} placed an order on your website.
@else
Hi {{ $detail['customer_name'] }}, we've received your order #{{ $detail['number'] }}.
@endif

Order: #{{ $detail['number'] }}
Date: {{ $detail['created_at'] }}
Payment: {{ $detail['payment_method_label'] }} ({{ $detail['payment_status_label'] }})

@foreach ($items as $item)
- {{ $item['quantity'] }} x {{ $item['name'] }}{{ $item['variant'] ? ' ('.$item['variant'].')' : '' }}: {{ $item['line_total'] }}
@endforeach

Subtotal: {{ $detail['subtotal'] }}
@if ($detail['coupon_code'])
Coupon ({{ $detail['coupon_code'] }}): {{ $detail['discount'] ? '-'.$detail['discount'] : 'Applied' }}
@endif
{{ $detail['shipping_method'] ?: 'Delivery' }}: {{ $detail['shipping_cost'] }}
Total: {{ $detail['total'] }}

{{ $detail['fulfilment'] === 'pickup' ? 'Pickup from' : 'Deliver to' }}: {{ $detail['address_line'] ?: 'We will share the pickup details with you.' }}
@if ($forOwner)
Customer: {{ $detail['customer_name'] }}, {{ $detail['customer_phone'] }}{{ $detail['customer_whatsapp'] && $detail['customer_whatsapp'] !== $detail['customer_phone'] ? ', WhatsApp '.$detail['customer_whatsapp'] : '' }}{{ $detail['customer_email'] ? ', '.$detail['customer_email'] : '' }}
@endif
@if (! $forOwner && $detail['payment_status'] !== 'paid' && in_array($detail['payment_method'], ['bank_transfer', 'delivery'], true) && $settings->bank_account_number)

Bank transfer details:
{{ $settings->bank_name }}
{{ $settings->bank_account_name }}
{{ $settings->bank_account_number }}
Reference: #{{ $detail['number'] }}
@endif
@if ($detail['notes'])

Note: {{ $detail['notes'] }}
@endif

{{ $forOwner ? 'Open order in admin' : 'View your order' }}: {{ $orderUrl }}

{{ $brand['name'] }}
{{ $brand['website'] }}
