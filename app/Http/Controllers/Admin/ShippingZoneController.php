<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\Shipping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Shipping zones (listed in Website Settings → Shipping) and their methods. */
class ShippingZoneController extends Controller
{
    public function create(): Response
    {
        return $this->form(new ShippingZone(['name' => '']));
    }

    public function store(Request $request): RedirectResponse
    {
        $zone = DB::transaction(function () use ($request) {
            $zone = ShippingZone::query()->create(['name' => '', 'sort_order' => (int) ShippingZone::query()->where('is_fallback', false)->max('sort_order') + 1]);
            $this->save($request, $zone);

            return $zone;
        });

        return redirect()->route('admin.shipping-zones.edit', $zone)->with('success', 'Shipping zone created.');
    }

    public function edit(ShippingZone $shippingZone): Response
    {
        return $this->form($shippingZone->load(['locations', 'methods']));
    }

    public function update(Request $request, ShippingZone $shippingZone): RedirectResponse
    {
        DB::transaction(fn () => $this->save($request, $shippingZone));

        return redirect()->route('admin.shipping-zones.edit', $shippingZone)->with('success', 'Shipping zone saved.');
    }

    public function destroy(ShippingZone $shippingZone): RedirectResponse
    {
        abort_if($shippingZone->is_fallback, 403);
        $shippingZone->delete();

        return redirect()->route('admin.settings.edit', ['tab' => 'shipping'])->with('success', 'Shipping zone deleted.');
    }

    /** Zones are matched top to bottom, so their order matters. */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                ShippingZone::query()->whereKey($id)->where('is_fallback', false)->update(['sort_order' => $i]);
            }
        });

        return back()->with('success', 'Zone order saved.');
    }

    protected function form(ShippingZone $zone): Response
    {
        return Inertia::render('Admin/Shipping/ZoneForm', [
            'zone' => [
                'id' => $zone->id,
                'name' => $zone->is_fallback ? ShippingZone::FALLBACK_NAME : $zone->name,
                'is_fallback' => (bool) $zone->is_fallback,
                'regions' => $zone->exists ? $zone->regionCodes() : [],
                'postcodes' => $zone->exists ? implode("\n", $zone->postcodes()) : '',
                'methods' => $zone->exists ? $zone->methods->map(fn (ShippingMethod $m) => [
                    'id' => $m->id,
                    'type' => $m->type,
                    'title' => $m->title,
                    'is_enabled' => $m->is_enabled,
                    'cost' => $m->setting('cost', ''),
                    'per_item' => $m->setting('per_item', ''),
                    'requires' => $m->setting('requires', 'none'),
                    'min_amount' => $m->setting('min_amount', ''),
                    'address' => $m->setting('address', ''),
                ])->values() : [],
            ],
            'regionOptions' => $this->regionOptions(),
            'methodTypes' => ShippingMethod::TYPES,
            'currencySymbol' => BusinessSetting::current()->currency_symbol ?: BusinessSetting::current()->currency_code,
        ]);
    }

    /** Countries, each followed by its states: [{value: "NG", label: "Nigeria"}, {value: "NG:LA", label: "Lagos, Nigeria"}, …]. */
    protected function regionOptions(): array
    {
        $options = [];
        foreach (Shipping::countries() as $code => $name) {
            $options[] = ['value' => $code, 'label' => $name];
            foreach (Shipping::states($code) as $stateCode => $stateName) {
                $options[] = ['value' => "{$code}:{$stateCode}", 'label' => "{$stateName}, {$name}", 'state' => true];
            }
        }

        return $options;
    }

    protected function save(Request $request, ShippingZone $zone): void
    {
        $fallback = $zone->is_fallback;
        $data = $request->validate([
            'name' => [Rule::requiredIf(! $fallback), 'nullable', 'string', 'max:120'],
            'regions' => ['array', 'max:500'],
            'regions.*' => ['string', function ($attr, $value, $fail) {
                [$country, $state] = array_pad(explode(':', (string) $value, 2), 2, null);
                $valid = isset(Shipping::countries()[$country]) && ($state === null || isset(Shipping::states($country)[$state]));
                if (! $valid) {
                    $fail('Unknown region.');
                }
            }],
            'postcodes' => ['nullable', 'string', 'max:10000'],
            'methods' => ['array', 'max:20'],
            'methods.*.id' => ['nullable', 'integer'],
            'methods.*.type' => ['required', Rule::in(array_keys(ShippingMethod::TYPES))],
            'methods.*.title' => ['required', 'string', 'max:120'],
            'methods.*.is_enabled' => ['boolean'],
            'methods.*.cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'methods.*.per_item' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'methods.*.requires' => ['nullable', Rule::in(array_keys(ShippingMethod::FREE_SHIPPING_REQUIRES))],
            'methods.*.min_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'methods.*.address' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Give the zone a name, e.g. "Lagos" or "West Africa".',
            'methods.*.title.required' => 'Enter the name customers see.',
        ]);

        foreach ($data['methods'] ?? [] as $i => $m) {
            if ($m['type'] === 'free_shipping' && in_array($m['requires'] ?? 'none', ['min_amount', 'either', 'both'], true) && ! is_numeric($m['min_amount'] ?? null)) {
                throw ValidationException::withMessages(["methods.{$i}.min_amount" => 'Enter the minimum order amount.']);
            }
        }

        if (! $fallback) {
            $zone->update(['name' => trim($data['name'])]);

            $zone->locations()->delete();
            $postcodes = collect(preg_split('/[\r\n,]+/', (string) ($data['postcodes'] ?? '')))->map(fn ($p) => trim($p))->filter()->unique()->take(500);
            $zone->locations()->createMany([
                ...collect($data['regions'] ?? [])->unique()->map(fn ($code) => ['type' => str_contains($code, ':') ? 'state' : 'country', 'code' => $code])->all(),
                ...$postcodes->map(fn ($p) => ['type' => 'postcode', 'code' => mb_substr($p, 0, 40)])->all(),
            ]);
        }

        $keep = [];
        foreach (array_values($data['methods'] ?? []) as $i => $m) {
            $attrs = [
                'type' => $m['type'],
                'title' => trim($m['title']),
                'is_enabled' => (bool) ($m['is_enabled'] ?? true),
                'sort_order' => $i,
                'settings' => match ($m['type']) {
                    'flat_rate' => ['cost' => (float) ($m['cost'] ?? 0), 'per_item' => (float) ($m['per_item'] ?? 0)],
                    'free_shipping' => ['requires' => $m['requires'] ?? 'none', 'min_amount' => (float) ($m['min_amount'] ?? 0)],
                    'local_pickup' => ['cost' => (float) ($m['cost'] ?? 0), 'address' => $m['address'] ?? null],
                },
            ];
            $method = ! empty($m['id']) ? $zone->methods()->whereKey($m['id'])->first() : null;
            if ($method) {
                $method->update($attrs);
            } else {
                $method = $zone->methods()->create($attrs);
            }
            $keep[] = $method->id;
        }
        $zone->methods()->whereNotIn('id', $keep)->delete();
    }
}
