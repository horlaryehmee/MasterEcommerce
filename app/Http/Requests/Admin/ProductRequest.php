<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Most variants a product can have (3 options can multiply quickly). */
    public const MAX_VARIANTS = 250;

    /** The form sends options only when the product has variations. */
    public function hasOptions(): bool
    {
        return is_array($this->input('options')) && $this->input('options') !== [];
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasOptions()) {
            $this->merge([
                'options' => collect($this->input('options'))->map(fn ($o) => [
                    'name' => trim((string) ($o['name'] ?? '')),
                    'values' => collect($o['values'] ?? [])->map(fn ($v) => [
                        'label' => trim((string) ($v['label'] ?? '')),
                        'color' => filled($v['color'] ?? null) ? strtoupper((string) $v['color']) : null,
                    ])->values()->all(),
                ])->values()->all(),
                'variants' => collect($this->input('variants', []))->map(fn ($v) => [
                    'id' => filled($v['id'] ?? null) ? $v['id'] : null,
                    'values' => collect($v['values'] ?? [])->map(fn ($x) => trim((string) $x))->values()->all(),
                    'price' => $v['price'] ?? null,
                    'compare_at_price' => filled($v['compare_at_price'] ?? null) ? $v['compare_at_price'] : null,
                    'sku' => filled($v['sku'] ?? null) ? trim((string) $v['sku']) : null,
                    'availability' => $v['availability'] ?? 'in_stock',
                    'image' => filled($v['image'] ?? null) ? $v['image'] : null,
                    'is_enabled' => filter_var($v['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ])->values()->all(),
            ]);
        }

        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
            'compare_at_price' => $this->filled('compare_at_price') ? $this->input('compare_at_price') : null,
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'is_featured' => $this->boolean('is_featured'),
            'is_new_arrival' => $this->boolean('is_new_arrival'),
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $maxKb = config('catalog.max_upload_kb');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash', Rule::unique('products', 'slug')->ignore($product?->id)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            // With variations, prices and availability are set per variation instead.
            'price' => [$this->hasOptions() ? 'nullable' : 'required', 'numeric', 'min:0', 'max:9999999999'],
            'compare_at_price' => ['nullable', 'numeric', ...($this->hasOptions() ? [] : ['gt:price']), 'max:9999999999'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'availability' => [$this->hasOptions() ? 'nullable' : 'required', Rule::in(array_keys(Product::AVAILABILITY))],

            'options' => ['nullable', 'array', 'max:3'],
            'options.*.name' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:50'],
            'options.*.values.*.label' => ['required', 'string', 'max:60'],
            'options.*.values.*.color' => ['nullable', 'regex:/^#[0-9A-F]{6}$/'],
            'variants' => [Rule::requiredIf($this->hasOptions()), 'array', 'max:'.self::MAX_VARIANTS],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.values' => ['required', 'array', 'max:3'],
            'variants.*.values.*' => ['required', 'string', 'max:60'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.availability' => ['required', Rule::in(array_keys(Product::AVAILABILITY))],
            'variants.*.image' => ['nullable', 'string', 'regex:/^(existing|new):\d+$/'],
            'variants.*.is_enabled' => ['boolean'],
            'status' => ['required', Rule::in(array_keys(Product::STATUSES))],
            'is_featured' => ['boolean'],
            'is_new_arrival' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],

            'images' => ['nullable', 'array', 'max:'.config('catalog.max_product_images')],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb, 'dimensions:max_width=8000,max_height=8000'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            // "existing:{id}" or "new:{index}" — which image is the main one.
            'primary_image' => ['nullable', 'string', 'regex:/^(existing|new):\d+$/'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $product = $this->route('product');
                $existing = $product ? $product->images()->whereNotIn('id', $this->input('remove_images', []))->count() : 0;
                $total = $existing + count($this->file('images', []));

                if ($total > config('catalog.max_product_images')) {
                    $validator->errors()->add('images', 'A product can have at most '.config('catalog.max_product_images').' images.');
                }
            },
            fn (Validator $validator) => $this->hasOptions() && ! $validator->errors()->hasAny(['options*', 'variants']) ? $this->checkVariants($validator) : null,
        ];
    }

    /** Values unique per option, every variant a real and unique combination, at least one on sale. */
    protected function checkVariants(Validator $validator): void
    {
        $options = $this->input('options');
        foreach ($options as $i => $option) {
            $labels = array_map(fn ($v) => mb_strtolower($v['label']), $option['values']);
            if (count($labels) !== count(array_unique($labels))) {
                $validator->errors()->add("options.{$i}.values", "Each {$option['name']} value can only be added once.");
            }
        }

        $seen = [];
        foreach ($this->input('variants', []) as $i => $variant) {
            $values = $variant['values'];
            $valid = count($values) === count($options);
            foreach ($options as $o => $option) {
                $valid = $valid && in_array(mb_strtolower($values[$o] ?? ''), array_map(fn ($v) => mb_strtolower($v['label']), $option['values']), true);
            }
            if (! $valid) {
                $validator->errors()->add("variants.{$i}.values", 'This variation doesn’t match the options above. Refresh the variations list.');

                continue;
            }
            $key = mb_strtolower(implode("\x1f", $values));
            if (isset($seen[$key])) {
                $validator->errors()->add("variants.{$i}.values", implode(' / ', $values).' is listed twice.');
            }
            $seen[$key] = true;
            if ($variant['compare_at_price'] !== null && (float) $variant['compare_at_price'] <= (float) $variant['price']) {
                $validator->errors()->add("variants.{$i}.compare_at_price", 'The previous price must be higher than the price.');
            }
        }

        if (! collect($this->input('variants', []))->contains('is_enabled', true)) {
            $validator->errors()->add('variants', 'Switch on at least one variation, or remove the options.');
        }
    }

    public function messages(): array
    {
        return [
            'compare_at_price.gt' => 'The previous price must be higher than the current price.',
            'options.*.name.required' => 'Name this option, e.g. Size or Colour.',
            'options.*.name.distinct' => 'Two options have the same name.',
            'options.*.values.required' => 'Add at least one value, e.g. Small.',
            'options.*.values.min' => 'Add at least one value, e.g. Small.',
            'variants.required' => 'Add at least one variation.',
            'variants.max' => 'A product can have at most '.self::MAX_VARIANTS.' variations. Remove some option values.',
            'variants.*.price.required' => 'Enter a price.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Images must be JPEG, PNG or WebP files.',
            'images.*.max' => 'Each image must be smaller than '.round(config('catalog.max_upload_kb') / 1024).' MB.',
            'images.*.dimensions' => 'Images must be at most 8000 × 8000 pixels.',
            'slug.alpha_dash' => 'The URL slug may only contain letters, numbers and dashes.',
            'slug.unique' => 'Another product already uses this URL slug.',
        ];
    }

    public function attributes(): array
    {
        return [
            'compare_at_price' => 'previous price',
            'category_id' => 'category',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
        ];
    }
}
