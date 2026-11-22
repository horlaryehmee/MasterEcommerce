{{-- Order number / date / payment row. Needs $detail. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px 0; background-color:#fafafa; border:1px solid #f0f0f1; border-radius:10px;">
    <tr>
        <td class="stack stack-gap" width="30%" valign="top" style="padding:14px 16px; font-family:Arial, Helvetica, sans-serif;">
            <span style="display:block; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#71717a;">Order</span>
            <span style="display:block; margin-top:2px; font-size:15px; font-weight:bold; color:#18181b;">#{{ $detail['number'] }}</span>
        </td>
        <td class="stack stack-gap" width="32%" valign="top" style="padding:14px 16px; font-family:Arial, Helvetica, sans-serif;">
            <span style="display:block; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#71717a;">Date</span>
            <span style="display:block; margin-top:2px; font-size:14px; color:#18181b;">{{ $detail['created_at'] }}</span>
        </td>
        <td class="stack" width="38%" valign="top" style="padding:14px 16px; font-family:Arial, Helvetica, sans-serif;">
            <span style="display:block; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#71717a;">Payment</span>
            <span style="display:block; margin-top:2px; font-size:14px; color:#18181b;">{{ $detail['payment_method_label'] }} · {{ $detail['payment_status_label'] }}</span>
        </td>
    </tr>
</table>
