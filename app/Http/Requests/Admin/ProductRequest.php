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

    protected function prepareForValidation(): void
    {
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
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:price', 'max:9999999999'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'availability' => ['required', Rule::in(array_keys(Product::AVAILABILITY))],
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
        ];
    }

    public function messages(): array
    {
        return [
            'compare_at_price.gt' => 'The previous price must be higher than the current price.',
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
