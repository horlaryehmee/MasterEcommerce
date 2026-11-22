<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Support\AdminList;
use App\Support\CsvExport;
use App\Support\Money;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CouponController extends Controller
{
    public const SORTS = ['newest' => 'Newest first', 'most_used' => 'Most used', 'code' => 'Code (A–Z)', 'expiring' => 'Expiring soonest'];

    protected function filters(Request $request): array
    {
        return [
            'search' => AdminList::search($request),
            'status' => AdminList::choice($request, 'status', Coupon::STATUSES),
            'type' => AdminList::choice($request, 'type', Coupon::TYPES + ['free_shipping' => 1]),
            'sort' => AdminList::sort($request, self::SORTS),
            'per_page' => AdminList::perPage($request),
        ];
    }

    /** Status depends on today's date and usage, so it is worked out here; shops have tens of coupons, not thousands. */
    protected function listRows(array $f)
    {
        $q = Coupon::query()
            ->search($f['search'])
            ->when($f['type'] === 'free_shipping', fn ($q) => $q->where('free_shipping', true))
            ->when($f['type'] && $f['type'] !== 'free_shipping', fn ($q) => $q->where('discount_type', $f['type']))
            ->withCount(['orders as used' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->withSum(['orders as discount_given' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'discount_total');

        match ($f['sort']) {
            'most_used' => $q->orderByDesc('used')->latest('id'),
            'code' => $q->orderBy('code'),
            'expiring' => $q->orderByRaw('expires_on IS NULL')->orderBy('expires_on')->latest('id'),
            default => $q->latest()->latest('id'),
        };

        return $q->get()->map(fn (Coupon $c) => ['model' => $c, 'row' => $this->row($c)]);
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $all = $this->listRows($filters);
        $rows = ($filters['status'] ? $all->where('row.status', $filters['status']) : $all)->pluck('row')->values();

        $page = max(1, (int) $request->query('page', 1));
        $coupons = new LengthAwarePaginator($rows->forPage($page, $filters['per_page'])->values(), $rows->count(), $filters['per_page'], $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
            'counts' => $all->pluck('row')->countBy('status'),
            'statuses' => Coupon::STATUSES,
            'types' => Coupon::TYPES + ['free_shipping' => 'Free shipping'],
            'sorts' => self::SORTS,
            'filters' => $filters,
            'total' => Coupon::query()->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $all = $this->listRows($filters);
        $ids = AdminList::ids($request);
        $rows = ($filters['status'] ? $all->where('row.status', $filters['status']) : $all)
            ->when($ids, fn ($rows) => $rows->filter(fn ($r) => in_array($r['model']->id, $ids, true)))
            ->map(fn ($r) => [
            $r['row']['code'],
            $r['row']['description'],
            $r['row']['offer'],
            $r['row']['status_label'],
            $r['model']->minimum_spend !== null ? CsvExport::amount($r['model']->minimum_spend) : '',
            $r['model']->usage_limit ?? '',
            $r['row']['used'],
            CsvExport::amount($r['model']->discount_given ?? 0),
            $r['model']->starts_on?->format('Y-m-d') ?? '',
            $r['model']->expires_on?->format('Y-m-d') ?? '',
            AdminList::localDate($r['model']->created_at),
        ]);
        $code = BusinessSetting::current()->currency_code;

        return CsvExport::download('coupons', ['Code', 'Description', 'Offer', 'Status', "Minimum spend ({$code})", 'Usage limit', 'Times used', "Discount given ({$code})", 'Starts', 'Expires', 'Created'], $rows);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Coupons/Form', [
            'coupon' => ['code' => $this->suggestCode()],
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coupon = Coupon::query()->create($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('success', "Coupon {$coupon->code} created.");
    }

    public function edit(Coupon $coupon): Response
    {
        $used = $coupon->usageCount();

        return Inertia::render('Admin/Coupons/Form', [
            'coupon' => [
                ...$coupon->only(
                    'id', 'code', 'description', 'discount_type', 'free_shipping', 'is_active', 'exclude_sale_items',
                    'usage_limit', 'usage_limit_per_customer', 'limit_usage_to_items',
                ),
                'amount' => $this->number($coupon->amount),
                'minimum_spend' => $this->number($coupon->minimum_spend),
                'maximum_spend' => $this->number($coupon->maximum_spend),
                'starts_on' => $coupon->starts_on?->toDateString(),
                'expires_on' => $coupon->expires_on?->toDateString(),
                'product_ids' => $this->ids($coupon->product_ids),
                'excluded_product_ids' => $this->ids($coupon->excluded_product_ids),
                'category_ids' => $this->ids($coupon->category_ids),
                'excluded_category_ids' => $this->ids($coupon->excluded_category_ids),
                'allowed_emails' => implode(', ', $coupon->allowed_emails ?? []),
                'status' => $coupon->status($used),
                'status_label' => Coupon::STATUSES[$coupon->status($used)],
                'used' => $used,
                'discount_given' => Money::format($coupon->countedOrders()->sum('discount_total')),
                'orders' => $coupon->orders()->latest()->latest('id')->limit(10)->get()->map(fn (Order $o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'customer_name' => $o->customer_name,
                    'discount' => Money::format($o->discount_total),
                    'total' => Money::format($o->total),
                    'status' => $o->status,
                    'status_label' => Order::STATUSES[$o->status] ?? $o->status,
                    'created_at' => $o->created_at?->format('j M Y'),
                ]),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon));

        return back()->with('success', "Coupon {$coupon->code} saved.");
    }

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', "Coupon {$coupon->code} ".($coupon->is_active ? 'switched on.' : 'switched off.'));
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        // Orders keep the code and discount they were placed with.
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', "Coupon {$coupon->code} deleted.");
    }

    public const BULK_ACTIONS = ['activate', 'deactivate', 'delete'];

    public function bulk(Request $request): RedirectResponse
    {
        $bulk = AdminList::bulk($request, self::BULK_ACTIONS);
        $coupons = Coupon::query()->whereIn('id', $bulk['ids'])->get();
        $n = AdminList::count($coupons->count(), 'coupon');

        match ($bulk['action']) {
            'activate' => $coupons->each->update(['is_active' => true]),
            'deactivate' => $coupons->each->update(['is_active' => false]),
            // Orders keep the code and discount they were placed with.
            'delete' => $coupons->each->delete(),
        };

        return back()->with('success', $n.match ($bulk['action']) {
            'activate' => ' switched on.',
            'deactivate' => ' switched off.',
            'delete' => ' deleted.',
        });
    }

    protected function row(Coupon $c): array
    {
        $status = $c->status((int) $c->used);

        return [
            'id' => $c->id,
            'code' => $c->code,
            'description' => $c->description,
            'offer' => $c->offerLabel(),
            'type_label' => Coupon::TYPES[$c->discount_type] ?? $c->discount_type,
            'is_active' => $c->is_active,
            'status' => $status,
            'status_label' => Coupon::STATUSES[$status],
            'used' => (int) $c->used,
            'usage_limit' => $c->usage_limit,
            'discount_given' => Money::format($c->discount_given ?? 0),
            'starts_on' => $c->starts_on?->format('j M Y'),
            'expires_on' => $c->expires_on?->format('j M Y'),
            'minimum_spend' => $c->minimum_spend !== null ? Money::format($c->minimum_spend) : null,
        ];
    }

    protected function formOptions(): array
    {
        return [
            'types' => collect(Coupon::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name'])->map(fn (Product $p) => ['value' => (string) $p->id, 'label' => $p->name]),
            'categories' => Category::query()->ordered()->get(['id', 'name'])->map(fn (Category $c) => ['value' => (string) $c->id, 'label' => $c->name]),
            'currencySymbol' => BusinessSetting::current()->currency_symbol ?: BusinessSetting::current()->currency_code,
            // Free shipping coupons only work if a zone has a free shipping method that accepts them.
            'freeShippingReady' => ShippingMethod::query()->where('type', 'free_shipping')->where('is_enabled', true)->get()
                ->contains(fn (ShippingMethod $m) => in_array($m->setting('requires'), ['coupon', 'either', 'both'], true)),
        ];
    }

    protected function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => Coupon::normalizeCode($request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon)],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in(array_keys(Coupon::TYPES))],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'free_shipping' => ['boolean'],
            'is_active' => ['boolean'],
            'starts_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'minimum_spend' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'maximum_spend' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'exclude_sale_items' => ['boolean'],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'excluded_product_ids' => ['array'],
            'excluded_product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'excluded_category_ids' => ['array'],
            'excluded_category_ids.*' => ['integer', 'exists:categories,id'],
            'allowed_emails' => ['nullable', 'string', 'max:5000'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'limit_usage_to_items' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], [
            'code.regex' => 'Use letters, numbers, dashes and underscores only.',
            'code.unique' => 'Another coupon already uses this code.',
            'expires_on.after_or_equal' => 'The expiry date must be on or after the start date.',
        ], [
            'amount' => 'coupon amount',
            'starts_on' => 'start date',
            'expires_on' => 'expiry date',
        ]);

        if ($data['discount_type'] === 'percent' && (float) $data['amount'] > 100) {
            throw ValidationException::withMessages(['amount' => 'A percentage discount can’t be more than 100%.']);
        }
        if ((float) $data['amount'] <= 0 && ! ($data['free_shipping'] ?? false)) {
            throw ValidationException::withMessages(['amount' => 'Enter the discount, or allow free shipping.']);
        }
        if (isset($data['minimum_spend'], $data['maximum_spend']) && (float) $data['maximum_spend'] > 0 && (float) $data['maximum_spend'] < (float) $data['minimum_spend']) {
            throw ValidationException::withMessages(['maximum_spend' => 'The maximum spend must be more than the minimum spend.']);
        }

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['allowed_emails'] ?? '')))->map(fn ($e) => Str::lower(trim($e)))->filter()->unique()->values();
        foreach ($emails as $email) {
            // Full address, or a wildcard such as *@company.com.
            if (! filter_var(str_replace('*', 'x', $email), FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['allowed_emails' => "“{$email}” is not a valid email address."]);
            }
        }

        $ids = fn (string $key) => collect($data[$key] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all() ?: null;

        return [
            ...collect($data)->only('code', 'description', 'discount_type', 'amount', 'starts_on', 'expires_on', 'minimum_spend', 'maximum_spend', 'usage_limit', 'usage_limit_per_customer', 'limit_usage_to_items')->all(),
            'free_shipping' => (bool) ($data['free_shipping'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'exclude_sale_items' => (bool) ($data['exclude_sale_items'] ?? false),
            'product_ids' => $ids('product_ids'),
            'excluded_product_ids' => $ids('excluded_product_ids'),
            'category_ids' => $ids('category_ids'),
            'excluded_category_ids' => $ids('excluded_category_ids'),
            'allowed_emails' => $emails->all() ?: null,
        ];
    }

    /** A random, easy-to-read code (no 0/O or 1/I). */
    protected function suggestCode(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)])->implode('');
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }

    protected function ids(?array $ids): array
    {
        return array_map('strval', $ids ?? []);
    }

    protected function number(mixed $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
