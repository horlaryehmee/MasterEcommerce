@extends('mail.layouts.brand')

@php
    $tone = match (true) {
        str_ends_with($event, 'cancelled') || str_ends_with($event, 'refunded') => ['#fef2f2', '#991b1b'],
        $event === 'payment.paid' || $event === 'status.completed' => ['#f0fdf4', '#166534'],
        default => ['#eff6ff', '#1e40af'],
    };
@endphp

@section('preheader'){{ $intro }}@endsection

@section('footnote')
    You received this email because of your order #{{ $detail['number'] }} on our website.
@endsection

@section('content')
    <span style="display:inline-block; margin:0 0 14px 0; padding:4px 12px; border-radius:999px; background-color:{{ $tone[0] }}; color:{{ $tone[1] }}; font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:bold; letter-spacing:0.4px; text-transform:uppercase;">
        {{ $detail['status_label'] }} · {{ $detail['payment_status_label'] }}
    </span>
    <h1 class="h1" style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:26px; line-height:34px; font-weight:bold; color:#18181b;">{{ $headline }}</h1>
    <p style="margin:0 0 6px 0;">Hi {{ $detail['customer_name'] }},</p>
    <p style="margin:0;">{{ $intro }}</p>

    @include('mail.partials.meta')

    <h2 style="margin:28px 0 8px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; letter-spacing:0.6px; text-transform:uppercase; color:#71717a; font-weight:bold;">Your order</h2>
    @include('mail.partials.items')

    @include('mail.partials.button', ['url' => $orderUrl, 'label' => 'View your order'])

    @if ($brand['phone'] || $brand['email'])
        <p style="margin:24px 0 0 0; font-size:13px; line-height:20px; color:#71717a; text-align:center;">
            Questions? {{ $brand['email'] ? 'Reply to this email' : '' }}{{ $brand['email'] && $brand['phone'] ? ' or call ' : ($brand['phone'] ? 'Call ' : '') }}{{ $brand['phone'] }}.
        </p>
    @endif
@endsection
