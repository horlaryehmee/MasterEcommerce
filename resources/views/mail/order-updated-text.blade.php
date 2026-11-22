{{ $headline }}

Hi {{ $detail['customer_name'] }},

{{ $intro }}

Order: #{{ $detail['number'] }}
Status: {{ $detail['status_label'] }}
Payment: {{ $detail['payment_status_label'] }}

@foreach ($items as $item)
- {{ $item['quantity'] }} x {{ $item['name'] }}{{ $item['variant'] ? ' ('.$item['variant'].')' : '' }}: {{ $item['line_total'] }}
@endforeach
Total: {{ $detail['total'] }}

View your order: {{ $orderUrl }}

{{ $brand['name'] }}
{{ $brand['website'] }}
