<x-mail::message>
# {{ $headline }}

Hi {{ $detail['customer_name'] }},

{{ $message }}

<x-mail::table>
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach ($detail['items'] as $item)
| {{ $item['name'] }} | {{ $item['quantity'] }} | {{ $item['line_total'] }} |
@endforeach
| **Total** | | **{{ $detail['total'] }}** |
</x-mail::table>

**Order status:** {{ $detail['status_label'] }} · **Payment:** {{ $detail['payment_status_label'] }}

<x-mail::button :url="$orderUrl">
View your order
</x-mail::button>

@if ($settings->phone || $settings->email)
Questions? @if ($settings->email)Reply to this email @endif @if ($settings->phone){{ $settings->email ? 'or call' : 'Call' }} {{ $settings->phone }}@endif.
@endif

{{ $settings->business_name }}
</x-mail::message>
