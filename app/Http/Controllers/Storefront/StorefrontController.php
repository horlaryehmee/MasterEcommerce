<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

abstract class StorefrontController extends Controller
{
    /** Render a storefront page with server-side SEO tags. */
    protected function page(string $component, array $props, Seo $seo): Response
    {
        $meta = $seo->toArray();

        return Inertia::render($component, $props + ['seo' => ['title' => $meta['title']]])
            ->withViewData(['seo' => $meta]);
    }

    /** Read and sanitise catalog filters from the query string. */
    protected function filters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'latest');
        $search = trim(mb_substr((string) $request->query('search', ''), 0, 100));
        $category = (string) $request->query('category', '');

        return [
            'search' => $search,
            'sort' => array_key_exists($sort, Product::SORTS) ? $sort : 'latest',
            'category' => preg_match('/^[a-z0-9-]{1,140}$/', $category) ? $category : '',
        ];
    }

    /** Paginated product cards for the given base query and filters. */
    protected function paginate(Builder $query, array $filters): array
    {
        $paginator = $query
            ->with(['primaryImage', 'category:id,name,slug'])
            ->search($filters['search'])
            ->sorted($filters['sort'])
            ->paginate(config('catalog.per_page', 12))
            ->withQueryString()
            ->through(fn (Product $p) => $p->toCard());

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'links' => $paginator->linkCollection()->map(fn ($l) => [
                'url' => $l['url'],
                'label' => strip_tags(html_entity_decode($l['label'])),
                'active' => $l['active'],
            ])->all(),
        ];
    }

    protected function sortOptions(): array
    {
        return collect(Product::SORTS)->map(fn ($label, $value) => compact('value', 'label'))->values()->all();
    }
}
