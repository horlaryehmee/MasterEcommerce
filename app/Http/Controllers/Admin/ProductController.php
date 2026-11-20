<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageStorage;
use App\Support\Media;
use App\Support\Money;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(protected ImageStorage $images) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim(mb_substr((string) $request->query('search', ''), 0, 100)),
            'status' => in_array($request->query('status'), array_keys(Product::STATUSES), true) ? $request->query('status') : '',
            'category' => ctype_digit((string) $request->query('category')) ? (int) $request->query('category') : '',
            'featured' => $request->boolean('featured'),
        ];

        $products = Product::query()
            ->with(['primaryImage', 'category:id,name'])
            ->search($filters['search'])
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['category'], fn ($q, $c) => $q->where('category_id', $c))
            ->when($filters['featured'], fn ($q) => $q->where('is_featured', true))
            ->latest('updated_at')->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'url' => $p->url(),
                'price' => Money::format($p->price),
                'compare_at_price' => $p->hasDiscount() ? Money::format($p->compare_at_price) : null,
                'status' => $p->status,
                'availability' => Product::AVAILABILITY[$p->availability] ?? $p->availability,
                'is_featured' => $p->is_featured,
                'is_new_arrival' => $p->is_new_arrival,
                'category' => $p->category?->name,
                'thumb' => Media::url($p->primaryImage?->path, 'sm'),
                'updated_at' => $p->updated_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'statuses' => Product::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', $this->formProps(new Product([
            'availability' => 'in_stock',
            'status' => Product::STATUS_PUBLISHED,
        ])));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = new Product($this->attributes($request));
            $product->slug = Slug::unique(Product::class, $request->input('slug') ?: $request->input('name'));
            $product->save();
            $this->syncImages($product, $request);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('success', $this->savedMessage('Product created.'));
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', $this->formProps($product->load('images')));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $product->fill($this->attributes($request));
            $product->slug = Slug::unique(Product::class, $request->input('slug') ?: $request->input('name'), $product->id);
            $product->save();
            $this->syncImages($product, $request);
        });

        return back()->with('success', $this->savedMessage('Product saved.'));
    }

    /** Quick actions from the product list: publish/unpublish, feature/unfeature. */
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['status', 'is_featured', 'is_new_arrival'])],
        ]);

        match ($data['field']) {
            'status' => $product->status = $product->isPublished() ? Product::STATUS_DRAFT : Product::STATUS_PUBLISHED,
            'is_featured' => $product->is_featured = ! $product->is_featured,
            'is_new_arrival' => $product->is_new_arrival = ! $product->is_new_arrival,
        };
        $product->save();

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $paths = $product->images()->pluck('path');
        $product->delete();
        // Remove files only after the database row is gone.
        $paths->each(fn ($path) => $this->images->delete($path));

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    protected function attributes(ProductRequest $request): array
    {
        return $request->safe()->only([
            'name', 'short_description', 'description', 'price', 'compare_at_price', 'category_id',
            'availability', 'status', 'is_featured', 'is_new_arrival', 'seo_title', 'seo_description',
        ]);
    }

    /** Remove, add and choose the primary image according to the submitted form. */
    protected function syncImages(Product $product, ProductRequest $request): void
    {
        $removeIds = array_map('intval', $request->input('remove_images', []));
        $removed = $product->images()->whereIn('id', $removeIds)->get();
        foreach ($removed as $image) {
            $image->delete();
            DB::afterCommit(fn () => $this->images->delete($image->path));
        }

        $nextOrder = (int) $product->images()->max('sort_order') + 1;
        $newImages = [];
        foreach ($request->file('images', []) as $index => $file) {
            try {
                $path = $this->images->store($file, 'products');
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(["images.{$index}" => $e->getMessage()]);
            }
            // If the transaction fails later, don't leave orphaned files behind.
            DB::afterRollBack(fn () => $this->images->delete($path));

            $newImages[$index] = $product->images()->create([
                'path' => $path,
                'alt' => $product->name,
                'sort_order' => $nextOrder++,
            ]);
        }

        // Resolve the chosen primary image; fall back to the current one, then the first.
        [$type, $ref] = array_pad(explode(':', (string) $request->input('primary_image')), 2, null);
        $primary = match ($type) {
            'new' => $newImages[(int) $ref] ?? null,
            'existing' => $product->images()->find((int) $ref),
            default => null,
        };
        $primary ??= $product->images()->where('is_primary', true)->first()
            ?? $product->images()->orderBy('sort_order')->first();

        $product->images()->update(['is_primary' => false]);
        $primary?->update(['is_primary' => true]);
    }

    protected function formProps(Product $product): array
    {
        return [
            'product' => $product->exists ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'url' => $product->url(),
                'short_description' => $product->short_description,
                'description' => $product->description,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'category_id' => $product->category_id,
                'availability' => $product->availability,
                'status' => $product->status,
                'is_featured' => $product->is_featured,
                'is_new_arrival' => $product->is_new_arrival,
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'images' => $product->images->map(fn (ProductImage $i) => [
                    'id' => $i->id,
                    'url' => Media::url($i->path, 'sm'),
                    'is_primary' => $i->is_primary,
                ]),
            ] : [
                'availability' => $product->availability,
                'status' => $product->status,
            ],
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'availabilityOptions' => Product::AVAILABILITY,
            'statuses' => Product::STATUSES,
            'maxImages' => config('catalog.max_product_images'),
            'maxUploadMb' => round(config('catalog.max_upload_kb') / 1024),
        ];
    }
}
