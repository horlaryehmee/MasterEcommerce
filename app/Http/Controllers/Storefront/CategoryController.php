<?php

namespace App\Http\Controllers\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Support\Media;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Response;

class CategoryController extends StorefrontController
{
    public function index(): Response
    {
        $categories = Category::query()->published()->ordered()
            ->withCount(['products as published_products_count' => fn ($q) => $q->published()])
            ->get();

        $seo = Seo::make()
            ->title('Shop by Category')
            ->breadcrumbs([['Home', url('/')], ['Categories', route('categories.index')]]);

        return $this->page('Storefront/Categories', [
            'categories' => $categories->map->toCard()->values(),
        ], $seo);
    }

    public function show(Request $request, string $slug): Response
    {
        $category = Category::query()->published()->where('slug', $slug)->firstOrFail();
        $filters = [...$this->filters($request), 'category' => $category->slug];

        $seo = Seo::make()
            ->title($category->seo_title ?: $category->name)
            ->description($category->seo_description ?: $category->description)
            ->paginatedCanonical(route('categories.show', $category->slug), max(1, (int) $request->query('page', 1)))
            ->image(Media::url($category->image_path))
            ->breadcrumbs([
                ['Home', url('/')],
                ['Categories', route('categories.index')],
                [$category->name, route('categories.show', $category->slug)],
            ]);

        return $this->page('Storefront/CategoryShow', [
            'category' => $category->toCard(),
            'products' => $this->paginate(Product::query()->published()->where('category_id', $category->id), $filters),
            'filters' => $filters,
            'sorts' => $this->sortOptions(),
        ], $seo);
    }
}
