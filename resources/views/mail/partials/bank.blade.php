{{-- Bank account box. Needs $settings, $detail. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:12px 0 0 0; border:1px solid #e4e4e7; border-radius:10px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px;">
    @if ($settings->bank_name)
        <tr><td style="padding:10px 16px; color:#71717a; border-bottom:1px solid #f0f0f1;">Bank</td><td align="right" style="padding:10px 16px; color:#18181b; border-bottom:1px solid #f0f0f1;">{{ $settings->bank_name }}</td></tr>
    @endif
    @if ($settings->bank_account_name)
        <tr><td style="padding:10px 16px; color:#71717a; border-bottom:1px solid #f0f0f1;">Account name</td><td align="right" style="padding:10px 16px; color:#18181b; border-bottom:1px solid #f0f0f1;">{{ $settings->bank_account_name }}</td></tr>
    @endif
    <tr><td style="padding:10px 16px; color:#71717a; border-bottom:1px solid #f0f0f1;">Account number</td><td align="right" style="padding:10px 16px; color:#18181b; font-size:17px; font-weight:bold; letter-spacing:1px; border-bottom:1px solid #f0f0f1;">{{ $settings->bank_account_number }}</td></tr>
    <tr><td style="padding:10px 16px; color:#71717a;">Reference</td><td align="right" style="padding:10px 16px; color:#18181b; font-weight:bold;">#{{ $detail['number'] }}</td></tr>
</table>
