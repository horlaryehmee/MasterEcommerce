<?php

namespace App\Http\Controllers\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Support\Markdown;
use App\Support\Media;
use App\Support\Seo;
use App\Support\Tracking;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Inertia\Response;

class ProductController extends StorefrontController
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $category = $filters['category']
            ? Category::query()->published()->where('slug', $filters['category'])->first()
            : null;

        $query = Product::query()->visible()
            ->when($category, fn ($q) => $q->where('category_id', $category->id));

        $title = $filters['search'] !== '' ? 'Search results for “'.$filters['search'].'”' : 'All Products';

        $seo = Seo::make()
            ->title($filters['search'] !== '' ? 'Search: '.$filters['search'] : ($category ? $category->name : 'Shop All Products'))
            ->paginatedCanonical($category ? route('categories.show', $category->slug) : route('products.index'), max(1, (int) $request->query('page', 1)))
            // Search result pages are not useful in search engines.
            ->noindex($filters['search'] !== '');

        return $this->page('Storefront/Catalog', [
            'heading' => $title,
            'products' => $this->paginate($query, $filters),
            'filters' => [...$filters, 'category' => $category?->slug ?? ''],
            'categories' => Category::query()->published()->ordered()
                ->withCount(['products as published_products_count' => fn ($q) => $q->published()])
                ->get()->map(fn (Category $c) => ['slug' => $c->slug, 'name' => $c->name, 'count' => $c->published_products_count]),
            'sorts' => $this->sortOptions(),
            'track' => $filters['search'] !== '' ? [Tracking::search($filters['search'])] : [],
        ], $seo);
    }

    public function show(Request $request, string $slug): Response
    {
        $product = Product::query()->where('slug', $slug)
            ->with(['images', 'primaryImage', 'category'])
            ->firstOrFail();

        // Drafts and products in hidden categories are only visible to the logged-in admin (preview).
        $isPublic = $product->isPublished() && (! $product->category || $product->category->is_published);
        abort_unless($isPublic || $request->user(), 404);

        $related = Product::query()->visible()
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['primaryImage', 'category:id,name'])
            ->inRandomOrder()->limit(4)->get();

        if ($related->count() < 4) {
            $related = $related->concat(
                Product::query()->visible()
                    ->whereKeyNot([$product->id, ...$related->modelKeys()])
                    ->with(['primaryImage', 'category:id,name'])
                    ->latest()->limit(4 - $related->count())->get()
            );
        }

        $crumbs = [['Home', url('/')], ['Products', route('products.index')]];
        if ($product->category) {
            $crumbs[] = [$product->category->name, route('categories.show', $product->category->slug)];
        }
        $crumbs[] = [$product->name, $product->url()];

        $seo = Seo::make()
            ->title($product->seo_title ?: $product->name)
            ->description($product->seo_description ?: $product->short_description ?: Markdown::excerpt($product->description))
            ->canonical($product->url())
            ->image(Media::url($product->primaryImage?->path))
            ->noindex(! $isPublic)
            ->product($product)
            ->breadcrumbs($crumbs);

        return $this->page('Storefront/ProductShow', [
            'product' => $product->toDetail(),
            'whatsappUrl' => WhatsApp::productLink($product),
            'related' => $related->map->toCard()->values(),
            'isPreview' => ! $isPublic,
            'track' => $isPublic ? [Tracking::viewItem($product)] : [],
        ], $seo);
    }

    /** /search?q=... is a friendly alias for the catalog search. */
    public function search(Request $request)
    {
        $q = trim(mb_substr((string) $request->query('q', $request->query('search', '')), 0, 100));

        return redirect()->route('products.index', $q !== '' ? ['search' => $q] : []);
    }
}
