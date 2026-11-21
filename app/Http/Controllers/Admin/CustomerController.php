<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Shipping;
use App\Support\AdminList;
use App\Support\CsvExport;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customers are built from orders (checkout is guest-only): every order with
 * the same phone number (or email) belongs to one customer. Their name, email
 * and address are taken from their most recent order.
 */
class CustomerController extends Controller
{
    public const SEGMENTS = [
        'new' => 'One order',
        'returning' => 'Returning',
        'inactive' => 'No order in 90 days',
    ];

    public const SORTS = [
        'recent' => 'Last order (newest)',
        'spent' => 'Total spent (highest)',
        'orders' => 'Most orders',
        'newest' => 'Newest customers',
        'name' => 'Name (A–Z)',
    ];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $customers = $this->query($filters)
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn ($c) => $this->row($c));

        // Counts for the segment chips (ignoring the segment itself).
        $counts = DB::query()->fromSub($this->aggregate(), 'c')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN orders_count = 1 THEN 1 ELSE 0 END) as n_new')
            ->selectRaw('SUM(CASE WHEN orders_count > 1 THEN 1 ELSE 0 END) as n_returning')
            ->selectRaw('SUM(CASE WHEN last_order_at < ? THEN 1 ELSE 0 END) as n_inactive', [now()->subDays(90)])
            ->first();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => $filters,
            'segments' => self::SEGMENTS,
            'sorts' => self::SORTS,
            'counts' => ['' => (int) $counts->total, 'new' => (int) $counts->n_new, 'returning' => (int) $counts->n_returning, 'inactive' => (int) $counts->n_inactive],
            'states' => $this->states(),
        ]);
    }

    public function show(string $key): Response
    {
        $orders = Order::query()->where('customer_key', $key)->latest()->latest('id')->get();
        abort_if($orders->isEmpty(), 404);

        $latest = $orders->first();
        $counted = $orders->where('status', '!=', 'cancelled');
        $spent = $counted->sum(fn ($o) => (float) $o->total);

        $products = OrderItem::query()
            ->whereIn('order_id', $counted->pluck('id'))
            ->selectRaw('product_name, MAX(product_slug) as product_slug, SUM(quantity) as quantity')
            ->groupBy('product_name')
            ->orderByDesc('quantity')->limit(6)->get();

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'key' => $key,
                'name' => $latest->customer_name,
                'phone' => $latest->customer_phone,
                'whatsapp_number' => $latest->customer_whatsapp && $latest->customer_whatsapp !== $latest->customer_phone ? $latest->customer_whatsapp : null,
                'email' => $orders->firstWhere('customer_email')?->customer_email,
                'location' => $this->location($latest->city, $latest->shipping_state, $latest->shipping_country),
                'address' => $latest->isPickup() ? null : $latest->addressLine(),
                'orders_count' => $orders->count(),
                'cancelled_count' => $orders->count() - $counted->count(),
                'spent' => Money::format($spent),
                'average' => $counted->count() ? Money::format($spent / $counted->count()) : null,
                'first_order' => AdminList::localDate($orders->last()->created_at, 'j M Y'),
                'last_order' => AdminList::localDate($latest->created_at, 'j M Y'),
                'last_order_ago' => $latest->created_at?->diffForHumans(),
                'other_names' => $orders->pluck('customer_name')->unique()->reject(fn ($n) => $n === $latest->customer_name)->values(),
                'whatsapp' => $latest->customerWhatsappLink("Hello {$latest->customer_name}, "),
                'call' => 'tel:'.preg_replace('/[^\d+]/', '', (string) $latest->customer_phone),
            ],
            'orders' => $orders->take(50)->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'total' => Money::format($o->total),
                'status' => $o->status,
                'status_label' => Order::STATUSES[$o->status] ?? $o->status,
                'payment_status' => $o->payment_status,
                'payment_status_label' => Order::PAYMENT_STATUSES[$o->payment_status] ?? $o->payment_status,
                'created_at' => AdminList::localDate($o->created_at, 'j M Y, g:i a'),
            ])->values(),
            'more_orders' => max(0, $orders->count() - 50),
            'products' => $products->map(fn ($p) => [
                'name' => $p->product_name,
                'quantity' => (int) $p->quantity,
                'url' => $p->product_slug ? route('products.show', $p->product_slug) : null,
            ]),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $code = \App\Models\BusinessSetting::current()->currency_code;

        $rows = (function () use ($filters) {
            foreach ($this->query($filters)->cursor() as $c) {
                yield [
                    $c->customer_name,
                    $c->customer_phone,
                    $c->customer_whatsapp,
                    $c->customer_email ?: $c->any_email,
                    $c->city,
                    $c->shipping_state ? (Shipping::stateName($c->shipping_country, $c->shipping_state) ?? $c->shipping_state) : '',
                    Shipping::countryName($c->shipping_country) ?? '',
                    (int) $c->orders_count,
                    CsvExport::amount($c->spent),
                    (int) $c->paid_count ? CsvExport::amount($c->spent / max(1, (int) $c->paid_count)) : 0,
                    AdminList::localDate($c->first_order_at),
                    AdminList::localDate($c->last_order_at),
                ];
            }
        })();

        return CsvExport::download('customers', [
            'Name', 'Phone', 'WhatsApp', 'Email', 'City', 'State', 'Country', 'Orders', "Total spent ({$code})", "Average order ({$code})", 'First order', 'Last order',
        ], $rows);
    }

    /* ------------------------------------------------------------------ */

    protected function filters(Request $request): array
    {
        return [
            'search' => AdminList::search($request),
            'segment' => AdminList::choice($request, 'segment', self::SEGMENTS),
            'state' => mb_substr((string) $request->query('state', ''), 0, 80),
            'contact' => AdminList::choice($request, 'contact', ['email' => 1, 'no_email' => 1]),
            'min_spent' => is_numeric($request->query('min_spent')) && $request->query('min_spent') > 0 ? (string) (float) $request->query('min_spent') : '',
            'from' => AdminList::date($request, 'from'),
            'to' => AdminList::date($request, 'to'),
            'sort' => AdminList::sort($request, self::SORTS),
            'per_page' => AdminList::perPage($request),
        ];
    }

    /** One row per customer key with their order totals. */
    protected function aggregate(): \Illuminate\Database\Eloquent\Builder
    {
        return Order::query()
            ->whereNotNull('customer_key')
            ->select('customer_key')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("SUM(CASE WHEN status <> 'cancelled' THEN total ELSE 0 END) as spent")
            ->selectRaw('MIN(created_at) as first_order_at')
            ->selectRaw('MAX(created_at) as last_order_at')
            ->selectRaw('MAX(id) as last_order_id')
            ->groupBy('customer_key');
    }

    protected function query(array $f): Builder
    {
        $q = DB::query()->fromSub($this->aggregate(), 'c')
            ->join('orders as o', 'o.id', '=', 'c.last_order_id')
            ->select('c.*', 'o.customer_name', 'o.customer_phone', 'o.customer_whatsapp', 'o.customer_email', 'o.city', 'o.shipping_state', 'o.shipping_country')
            // Earlier orders may have the email when the latest one doesn't.
            ->selectSub(Order::query()->toBase()->from('orders as e')->whereColumn('e.customer_key', 'c.customer_key')->whereNotNull('e.customer_email')->where('e.customer_email', '<>', '')->orderByDesc('e.id')->limit(1)->select('e.customer_email'), 'any_email');

        if ($f['search'] !== '') {
            $q->whereIn('c.customer_key', Order::query()->search($f['search'])->whereNotNull('customer_key')->select('customer_key'));
        }
        if ($f['from'] || $f['to']) {
            $q->whereIn('c.customer_key', AdminList::whereDays(Order::query()->whereNotNull('customer_key'), 'created_at', $f['from'], $f['to'])->select('customer_key'));
        }

        match ($f['segment']) {
            'new' => $q->where('c.orders_count', 1),
            'returning' => $q->where('c.orders_count', '>', 1),
            'inactive' => $q->where('c.last_order_at', '<', now()->subDays(90)),
            default => null,
        };

        $q->when($f['state'] !== '', fn ($q) => $q->where('o.shipping_state', $f['state']))
            // Cast: SQLite's driver binds floats as text, which never compares as a number.
            ->when($f['min_spent'] !== '', fn ($q) => $q->whereRaw('c.spent >= CAST(? AS DECIMAL(12,2))', [(float) $f['min_spent']]))
            ->when($f['contact'] === 'email', fn ($q) => $q->whereExists(fn ($e) => $e->from('orders as m')->whereColumn('m.customer_key', 'c.customer_key')->whereNotNull('m.customer_email')->where('m.customer_email', '<>', '')))
            ->when($f['contact'] === 'no_email', fn ($q) => $q->whereNotExists(fn ($e) => $e->from('orders as m')->whereColumn('m.customer_key', 'c.customer_key')->whereNotNull('m.customer_email')->where('m.customer_email', '<>', '')));

        match ($f['sort']) {
            'spent' => $q->orderByDesc('c.spent'),
            'orders' => $q->orderByDesc('c.orders_count')->orderByDesc('c.spent'),
            'newest' => $q->orderByDesc('c.first_order_at'),
            'name' => $q->orderBy('o.customer_name'),
            default => $q->orderByDesc('c.last_order_at'),
        };

        return $q->orderByDesc('c.last_order_id');
    }

    protected function row(object $c): array
    {
        $average = (int) $c->paid_count ? (float) $c->spent / (int) $c->paid_count : 0;

        return [
            'key' => $c->customer_key,
            'name' => $c->customer_name,
            'phone' => $c->customer_phone,
            'email' => $c->customer_email ?: $c->any_email,
            'location' => $this->location($c->city, $c->shipping_state, $c->shipping_country),
            'orders_count' => (int) $c->orders_count,
            'spent' => Money::format($c->spent ?? 0),
            'average' => $average ? Money::format($average) : null,
            'first_order' => AdminList::localDate($c->first_order_at, 'j M Y'),
            'last_order' => AdminList::localDate($c->last_order_at, 'j M Y'),
            'last_order_ago' => \Illuminate\Support\Carbon::parse($c->last_order_at)->diffForHumans(),
        ];
    }

    protected function location(?string $city, ?string $state, ?string $country): string
    {
        $stateName = $state ? (Shipping::stateName($country, $state) ?? $state) : null;

        // "Enugu, Enugu" reads oddly: show the state only when it adds something.
        return implode(', ', array_unique(array_filter([$city, $stateName]), SORT_REGULAR));
    }

    /** States customers' latest orders were sent to, for the filter. */
    protected function states(): array
    {
        return Order::query()->whereNotNull('shipping_state')->where('shipping_state', '<>', '')
            ->select('shipping_country', 'shipping_state')->distinct()->get()
            ->map(fn ($o) => ['value' => $o->shipping_state, 'label' => Shipping::stateName($o->shipping_country, $o->shipping_state) ?? $o->shipping_state])
            ->unique('value')->sortBy('label')->values()->all();
    }
}
