<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Services\OrderMailer;
use App\Services\Shipping;
use App\Support\AdminList;
use App\Support\CsvExport;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public const SORTS = [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'total_desc' => 'Total (highest)',
        'total_asc' => 'Total (lowest)',
    ];

    protected function filters(Request $request): array
    {
        return [
            'search' => AdminList::search($request),
            'status' => AdminList::choice($request, 'status', Order::STATUSES),
            'payment_status' => AdminList::choice($request, 'payment_status', Order::PAYMENT_STATUSES),
            'payment_method' => AdminList::choice($request, 'payment_method', Order::PAYMENT_METHODS),
            'fulfilment' => AdminList::choice($request, 'fulfilment', Order::FULFILMENT),
            'customer' => preg_match('/^[pe][0-9a-f]{4,39}$/', (string) $request->query('customer')) ? (string) $request->query('customer') : '',
            'from' => AdminList::date($request, 'from'),
            'to' => AdminList::date($request, 'to'),
            'sort' => AdminList::sort($request, self::SORTS),
            'per_page' => AdminList::perPage($request),
        ];
    }

    /** Everything except the status (the chips show a count per status). */
    protected function query(array $f, bool $withStatus = true): Builder
    {
        $q = Order::query()
            ->search($f['search'])
            ->when($withStatus && $f['status'], fn ($q) => $q->where('status', $f['status']))
            ->when($f['payment_status'], fn ($q, $s) => $q->where('payment_status', $s))
            ->when($f['payment_method'], fn ($q, $s) => $q->where('payment_method', $s))
            ->when($f['fulfilment'], fn ($q, $s) => $q->where('fulfilment', $s))
            ->when($f['customer'], fn ($q, $s) => $q->where('customer_key', $s));
        AdminList::whereDays($q, 'created_at', $f['from'], $f['to']);

        return $q;
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $query = $this->query($filters)->withSum('items as item_count', 'quantity');
        match ($filters['sort']) {
            'oldest' => $query->oldest()->oldest('id'),
            'total_desc' => $query->orderByDesc('total')->latest('id'),
            'total_asc' => $query->orderBy('total')->latest('id'),
            default => $query->latest()->latest('id'),
        };

        $orders = $query
            ->paginate($filters['per_page'])
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
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'fulfilments' => Order::FULFILMENT,
            'sorts' => self::SORTS,
            'counts' => $this->query($filters, false)->reorder()->selectRaw('status, COUNT(*) as orders_count')->groupBy('status')->pluck('orders_count', 'status'),
            'customerName' => $filters['customer'] ? Order::query()->where('customer_key', $filters['customer'])->latest('id')->value('customer_name') : null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $code = BusinessSetting::current()->currency_code;
        $query = $this->query($filters)->withSum('items as item_count', 'quantity')->with('items:id,order_id,product_name,variant_label,quantity');
        // Streamed in batches so large shops don't run out of memory.
        $orders = $filters['sort'] === 'oldest' ? $query->lazyById(200) : $query->lazyByIdDesc(200);

        $rows = (function () use ($orders) {
            foreach ($orders as $o) {
                yield [
                    $o->number,
                    AdminList::localDate($o->created_at),
                    Order::STATUSES[$o->status] ?? $o->status,
                    Order::PAYMENT_METHODS[$o->payment_method] ?? $o->payment_method,
                    Order::PAYMENT_STATUSES[$o->payment_status] ?? $o->payment_status,
                    $o->customer_name,
                    $o->customer_phone,
                    $o->customer_whatsapp,
                    $o->customer_email,
                    Order::FULFILMENT[$o->fulfilment] ?? $o->fulfilment,
                    $o->shipping_method,
                    $o->address,
                    $o->city,
                    $o->shipping_state ? (Shipping::stateName($o->shipping_country, $o->shipping_state) ?? $o->shipping_state) : '',
                    Shipping::countryName($o->shipping_country) ?? '',
                    (int) $o->item_count,
                    $o->items->map(fn ($i) => $i->quantity.' × '.$i->product_name.($i->variant_label ? " ({$i->variant_label})" : ''))->implode('; '),
                    $o->coupon_code,
                    CsvExport::amount($o->subtotal),
                    CsvExport::amount($o->discount_total),
                    CsvExport::amount($o->delivery_fee),
                    CsvExport::amount($o->total),
                    $o->notes,
                ];
            }
        })();

        return CsvExport::download('orders', [
            'Order', 'Date', 'Status', 'Payment method', 'Payment status', 'Customer', 'Phone', 'WhatsApp', 'Email', 'Delivery or pickup', 'Shipping method',
            'Address', 'City', 'State', 'Country', 'Items', 'Products', 'Coupon', "Subtotal ({$code})", "Discount ({$code})", "Delivery ({$code})", "Total ({$code})", 'Notes',
        ], $rows);
    }

    public function show(Order $order): Response
    {
        $order->load('items');

        return Inertia::render('Admin/Orders/Show', [
            'order' => [...$order->toDetail(), 'public_url' => $order->url()],
            'customer' => $order->customer_key ? [
                'url' => route('admin.customers.show', $order->customer_key),
                'orders_count' => Order::query()->where('customer_key', $order->customer_key)->count(),
            ] : null,
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            // wa.me needs the international format; local numbers (0801…) get a call link only.
            'customerWhatsapp' => $order->customerWhatsappLink("Hello {$order->customer_name}, this is about your order #{$order->number}."),
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
