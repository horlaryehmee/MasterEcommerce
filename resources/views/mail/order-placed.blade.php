@extends('mail.layouts.brand')

@php
    $h2 = 'margin:28px 0 8px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; letter-spacing:0.6px; text-transform:uppercase; color:#71717a; font-weight:bold;';
    $box = 'margin:20px 0 0 0; padding:16px 18px; border-radius:10px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px;';
    $customerContact = $detail['customer_phone']
        .($detail['customer_whatsapp'] && $detail['customer_whatsapp'] !== $detail['customer_phone'] ? ' · WhatsApp '.$detail['customer_whatsapp'] : '')
        .($detail['customer_email'] ? ' · '.$detail['customer_email'] : '');
@endphp

@section('preheader')
    @if ($forOwner)
        New order #{{ $detail['number'] }} from {{ $detail['customer_name'] }}: {{ $detail['total'] }}, {{ $detail['item_count'] }} {{ $detail['item_count'] === 1 ? 'item' : 'items' }}.
    @else
        We’ve received your order #{{ $detail['number'] }} ({{ $detail['total'] }}). Here’s your summary.
    @endif
@endsection

@section('footnote')
    @if ($forOwner)
        Sent to you because new-order emails are switched on in Admin → Settings → Shop.
    @else
        You received this email because you placed order #{{ $detail['number'] }} on our website. Keep it for your records.
    @endif
@endsection

@section('content')
    <h1 class="h1" style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:26px; line-height:34px; font-weight:bold; color:#18181b;">
        {{ $forOwner ? 'New order #'.$detail['number'] : 'Thank you for your order!' }}
    </h1>
    <p style="margin:0;">
        @if ($forOwner)
            <strong style="color:#18181b;">{{ $detail['customer_name'] }}</strong> placed an order on your website.
        @else
            Hi {{ $detail['customer_name'] }}, we’ve received your order <strong style="color:#18181b;">#{{ $detail['number'] }}</strong>
            @if ($detail['payment_status'] === 'paid')
                and your payment. We’ll let you know when it’s on its way.
            @else
                and will be in touch to confirm it.
            @endif
        @endif
    </p>

    @include('mail.partials.meta')

    <h2 style="{{ $h2 }}">Order summary</h2>
    @include('mail.partials.items')

    {{-- How to pay (customer) --}}
    @if (! $forOwner && $detail['payment_status'] !== 'paid')
        @if ($detail['payment_method'] === 'paystack')
            <div style="{{ $box }} background-color:#fffbeb; color:#78350f;">
                <strong>Payment pending.</strong> If you didn’t finish paying, open your order and tap “Pay now” to pay {{ $detail['total'] }} securely with Paystack.
            </div>
        @elseif ($detail['payment_method'] === 'bank_transfer' && $settings->bank_account_number)
            <div style="{{ $box }} background-color:#f4f4f5; color:#3f3f46;">
                <strong style="color:#18181b;">Complete your payment.</strong> Transfer <strong style="color:#18181b;">{{ $detail['total'] }}</strong> to the account below, using <strong style="color:#18181b;">#{{ $detail['number'] }}</strong> as the reference.
                @include('mail.partials.bank')
            </div>
        @elseif ($detail['payment_method'] === 'delivery')
            <div style="{{ $box }} background-color:#f4f4f5; color:#3f3f46;">
                Pay <strong style="color:#18181b;">{{ $detail['total'] }}</strong> with cash or bank transfer when your order {{ $detail['fulfilment'] === 'pickup' ? 'is ready' : 'arrives' }}.
                @if ($settings->bank_account_number)
                    To pay by transfer, use:
                    @include('mail.partials.bank')
                @endif
            </div>
        @elseif ($detail['payment_method'] === 'whatsapp')
            <div style="{{ $box }} background-color:#f0fdf4; color:#14532d;">
                We’ll confirm your order and agree payment with you on WhatsApp.
            </div>
        @endif
    @endif

    {{-- Delivery & customer --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0 0;">
        <tr>
            <td class="stack stack-gap" width="50%" valign="top" style="padding-right:12px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#3f3f46;">
                <span style="display:block; margin-bottom:6px; font-size:13px; letter-spacing:0.6px; text-transform:uppercase; color:#71717a; font-weight:bold;">{{ $detail['fulfilment'] === 'pickup' ? 'Pickup from' : 'Deliver to' }}</span>
                {{ $detail['customer_name'] }}<br>
                {{ $detail['address_line'] ?: 'We will share the pickup details with you.' }}
                @if ($detail['shipping_method'])
                    <br><span style="color:#71717a;">{{ $detail['shipping_method'] }}</span>
                @endif
            </td>
            <td class="stack" width="50%" valign="top" style="padding-left:12px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#3f3f46;">
                <span style="display:block; margin-bottom:6px; font-size:13px; letter-spacing:0.6px; text-transform:uppercase; color:#71717a; font-weight:bold;">{{ $forOwner ? 'Customer' : 'Contact' }}</span>
                @if ($forOwner)
                    {{ $detail['customer_name'] }}<br>{{ $customerContact }}
                @else
                    {{ $detail['customer_phone'] }}
                    @if ($detail['customer_email'])
                        <br>{{ $detail['customer_email'] }}
                    @endif
                @endif
            </td>
        </tr>
    </table>

    @if ($detail['notes'])
        <div style="{{ $box }} background-color:#fffbeb; color:#78350f;"><strong>Note:</strong> {{ $detail['notes'] }}</div>
    @endif

    @include('mail.partials.button', ['url' => $orderUrl, 'label' => $forOwner ? 'Open order in admin' : ($detail['payment_method'] === 'paystack' && $detail['payment_status'] !== 'paid' ? 'View order & pay' : 'View your order')])

    @unless ($forOwner)
        <p style="margin:24px 0 0 0; font-size:13px; line-height:20px; color:#71717a; text-align:center;">
            Questions about your order? {{ $brand['email'] ? 'Just reply to this email' : '' }}{{ $brand['email'] && $brand['phone'] ? ' or call ' : ($brand['phone'] ? 'Call ' : '') }}{{ $brand['phone'] }}.
        </p>
    @endunless
@endsection
