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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            'receipt' => $request->query('receipt') === 'pending' ? 'pending' : '',
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
            ->when($f['receipt'], fn ($q) => $q->paymentToCheck())
            ->when($f['customer'], fn ($q, $s) => $q->where('customer_key', $s));
        AdminList::whereDays($q, 'created_at', $f['from'], $f['to']);

        return $q;
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $query = $this->query($filters)->withSum('items as item_count', 'quantity')
            // Paid with Paystack TEST keys: no real money arrived.
            ->withExists(['payments as paid_in_test' => fn ($q) => $q->where('mode', 'test')->where('status', 'success')])
            ->withExists(['receipts as receipt_waiting' => fn ($q) => $q->where('status', 'pending')]);
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
                'paid_in_test' => (bool) $o->paid_in_test,
                // Customer says they've paid by transfer (button or receipt): check the bank.
                'payment_to_check' => $o->payment_status === 'unpaid' && $o->status !== 'cancelled' && ($o->payment_reported_at !== null || $o->receipt_waiting),
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
        $query = $this->query($filters)->withSum('items as item_count', 'quantity')->with('items:id,order_id,product_name,variant_label,quantity')
            ->when(AdminList::ids($request), fn ($q, $ids) => $q->whereIn('id', $ids));
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
            'paidAt' => $order->paid_at ? AdminList::localDate($order->paid_at, 'j M Y, g:i a') : null,
            'paymentReportedAt' => $order->payment_reported_at ? AdminList::localDate($order->payment_reported_at, 'j M Y, g:i a') : null,
            // Online payment attempts (Paystack), newest first.
            'payments' => $order->payments()->get()->map(fn (\App\Models\Payment $p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'status' => $p->status,
                'status_label' => \App\Models\Payment::STATUSES[$p->status] ?? $p->status,
                'amount' => Money::format($p->amount / 100),
                'amount_paid' => $p->amount_paid !== null && $p->amount_paid !== $p->amount ? Money::format($p->amount_paid / 100) : null,
                'channel' => $p->channel,
                'mode' => $p->mode,
                'message' => $p->message,
                'at' => AdminList::localDate($p->verified_at ?? $p->created_at, 'j M Y, g:i a'),
            ]),
            // Bank-transfer receipts the customer uploaded, newest first.
            'receipts' => $order->receipts()->get()->map(fn (\App\Models\PaymentReceipt $r) => [
                'id' => $r->id,
                'kind' => $r->kind,
                'status' => $r->status,
                'status_label' => \App\Models\PaymentReceipt::STATUSES[$r->status] ?? $r->status,
                'note' => $r->note,
                'size' => \App\Services\ImageStorage::humanBytes($r->size),
                'url' => route('admin.receipts.file', $r),
                'at' => AdminList::localDate($r->created_at, 'j M Y, g:i a'),
            ]),
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

        if (($data['payment_status'] ?? null) === 'paid' && $order->status === 'awaiting_payment' && ! isset($data['status'])) {
            $data['status'] = 'pending';
        }
        if (($data['payment_status'] ?? null) === 'paid' && ! $order->paid_at) {
            $data['paid_at'] = now();
        }
        $order->update($data);
        $emailed = $mailer->updated($order);

        return back()->with('success', "Order #{$order->number} updated.".($emailed ? ' The customer has been emailed.' : ''));
    }

    /** The order's emails as the customer (or owner) receives them. */
    public function emailPreview(Order $order, string $type): \Illuminate\Http\Response
    {
        $mail = match ($type) {
            'placed' => new \App\Mail\OrderPlaced($order),
            'owner' => new \App\Mail\OrderPlaced($order, true),
            'updated' => new \App\Mail\OrderUpdated($order, 'status.confirmed'),
        };

        return response($mail->render())->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public const BULK_ACTIONS = ['status', 'payment_status', 'delete'];

    /**
     * Bulk actions on the ticked orders. Status changes email the customer just
     * like changing one order. Paid orders are never deleted (they are your
     * sales record); cancel or refund them instead.
     */
    public function bulk(Request $request, OrderMailer $mailer): RedirectResponse
    {
        $bulk = AdminList::bulk($request, self::BULK_ACTIONS, ['nullable', 'string', 'max:20']);
        $value = (string) $bulk['value'];
        $orders = Order::query()->whereIn('id', $bulk['ids'])->get();

        if ($bulk['action'] === 'delete') {
            [$paid, $deletable] = $orders->partition(fn (Order $o) => $o->payment_status === 'paid');
            DB::transaction(fn () => $deletable->each(function (Order $o) {
                $o->items()->delete();
                $o->delete();
            }));
            $message = AdminList::count($deletable->count(), 'order').' deleted.';
            if ($paid->isNotEmpty()) {
                $message .= ' '.AdminList::count($paid->count(), 'paid order').' kept: paid orders can’t be deleted. Cancel or refund them instead.';
            }

            return back()->with($deletable->isEmpty() ? 'error' : 'success', $message);
        }

        $allowed = $bulk['action'] === 'status' ? Order::STATUSES : Order::PAYMENT_STATUSES;
        if (! array_key_exists($value, $allowed)) {
            throw ValidationException::withMessages(['value' => 'Choose a status.']);
        }

        $emailed = 0;
        foreach ($orders as $o) {
            $o->update([$bulk['action'] => $value]);
            $emailed += (int) $mailer->updated($o);
        }

        return back()->with('success', AdminList::count($orders->count(), 'order').' marked “'.$allowed[$value].'”.'.($emailed ? ' '.AdminList::count($emailed, 'customer').' emailed.' : ''));
    }
}
