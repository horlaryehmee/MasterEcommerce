<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderMailer;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim(mb_substr((string) $request->query('search', ''), 0, 100)),
            'status' => array_key_exists((string) $request->query('status'), Order::STATUSES) ? $request->query('status') : '',
        ];

        $orders = Order::query()
            ->withSum('items as item_count', 'quantity')
            ->search($filters['search'])
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->latest()->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer_name' => $o->customer_name,
                'customer_phone' => $o->customer_phone,
                'total' => Money::format($o->total),
                'item_count' => (int) $o->item_count,
                'status' => $o->status,
                'status_label' => Order::STATUSES[$o->status] ?? $o->status,
                'payment_method_label' => Order::PAYMENT_METHODS[$o->payment_method] ?? $o->payment_method,
                'payment_status' => $o->payment_status,
                'payment_status_label' => Order::PAYMENT_STATUSES[$o->payment_status] ?? $o->payment_status,
                'fulfilment_label' => Order::FULFILMENT[$o->fulfilment] ?? $o->fulfilment,
                'created_at' => $o->created_at?->format('j M Y, g:i a'),
                'created_ago' => $o->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => Order::STATUSES,
            'counts' => Order::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load('items');
        $phone = WhatsApp::normalize($order->customer_phone);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [...$order->toDetail(), 'public_url' => $order->url()],
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            // wa.me needs the international format; local numbers (0801…) get a call link only.
            'customerWhatsapp' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode("Hello {$order->customer_name}, this is about your order #{$order->number}.") : null,
        ]);
    }

    public function update(Request $request, Order $order, OrderMailer $mailer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(array_keys(Order::STATUSES))],
            'payment_status' => ['sometimes', 'required', Rule::in(array_keys(Order::PAYMENT_STATUSES))],
        ]);

        $order->update($data);
        $emailed = $mailer->updated($order);

        return back()->with('success', "Order #{$order->number} updated.".($emailed ? ' The customer has been emailed.' : ''));
    }
}
