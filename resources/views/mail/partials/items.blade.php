{{-- Order lines with thumbnails, then the totals. Needs $items, $detail; $showTotals (default true). --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px 0;">
    @foreach ($items as $item)
        <tr>
            <td width="72" valign="top" style="padding:14px 14px 14px 0; border-bottom:1px solid #f0f0f1;">
                @if ($item['email_image'])
                    <img src="{{ $item['email_image'] }}" alt="" width="64" height="64" style="display:block; width:64px; height:64px; object-fit:cover; border-radius:8px; background-color:#f4f4f5;">
                @else
                    <div style="width:64px; height:64px; border-radius:8px; background-color:#f4f4f5;"></div>
                @endif
            </td>
            <td valign="top" style="padding:14px 8px 14px 0; border-bottom:1px solid #f0f0f1; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#18181b;">
                @if ($item['url'])
                    <a href="{{ $item['url'] }}" target="_blank" style="color:#18181b; text-decoration:none; font-weight:bold;">{{ $item['name'] }}</a>
                @else
                    <strong>{{ $item['name'] }}</strong>
                @endif
                @if ($item['variant'])
                    <br><span style="color:#71717a; font-size:13px;">{{ $item['variant'] }}</span>
                @endif
                <br><span style="color:#71717a; font-size:13px;">Qty {{ $item['quantity'] }} × {{ $item['unit_price'] }}</span>
            </td>
            <td valign="top" align="right" style="padding:14px 0; border-bottom:1px solid #f0f0f1; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#18181b; white-space:nowrap; font-weight:bold;">
                {{ $item['line_total'] }}
            </td>
        </tr>
    @endforeach
</table>

@if ($showTotals ?? true)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#3f3f46;">
    <tr>
        <td style="padding:6px 0;">Subtotal</td>
        <td align="right" style="padding:6px 0;">{{ $detail['subtotal'] }}</td>
    </tr>
    @if ($detail['coupon_code'])
        <tr>
            <td style="padding:6px 0;">Coupon ({{ $detail['coupon_code'] }})</td>
            <td align="right" style="padding:6px 0; color:#15803d;">{{ $detail['discount'] ? '−'.$detail['discount'] : 'Applied' }}</td>
        </tr>
    @endif
    <tr>
        <td style="padding:6px 0;">{{ $detail['shipping_method'] ?: ($detail['fulfilment'] === 'pickup' ? 'Pickup' : 'Delivery') }}</td>
        <td align="right" style="padding:6px 0;">{{ $detail['shipping_cost'] }}</td>
    </tr>
    <tr>
        <td style="padding:12px 0 0 0; border-top:2px solid #18181b; font-size:16px; font-weight:bold; color:#18181b;">Total</td>
        <td align="right" style="padding:12px 0 0 0; border-top:2px solid #18181b; font-size:18px; font-weight:bold; color:#18181b;">{{ $detail['total'] }}</td>
    </tr>
</table>
@endif
