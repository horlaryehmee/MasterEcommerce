<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ImageStorage;
use App\Support\AdminList;
use App\Support\CsvExport;
use App\Support\Media;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    public function __construct(protected ImageStorage $images) {}

    public const VISIBILITY = ['visible' => 'Visible', 'hidden' => 'Hidden', 'homepage' => 'On homepage', 'empty' => 'No products'];

    public const SORTS = ['order' => 'Display order', 'name' => 'Name (A–Z)', 'products' => 'Most products'];

    protected function filters(Request $request): array
    {
        return [
            'search' => AdminList::search($request),
            'visibility' => AdminList::choice($request, 'visibility', self::VISIBILITY),
            'sort' => AdminList::sort($request, self::SORTS),
            'per_page' => AdminList::perPage($request),
        ];
    }

    protected function listQuery(array $f)
    {
        $like = '%'.addcslashes($f['search'], '%_\\').'%';
        $q = Category::query()->withCount('products')
            ->when($f['search'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like)->orWhere('description', 'like', $like)));

        match ($f['visibility']) {
            'visible' => $q->where('is_published', true),
            'hidden' => $q->where('is_published', false),
            'homepage' => $q->where('is_featured', true),
            'empty' => $q->doesntHave('products'),
            default => null,
        };
        match ($f['sort']) {
            'name' => $q->orderBy('name')->orderBy('id'),
            'products' => $q->orderByDesc('products_count')->orderBy('id'),
            default => $q->ordered(),
        };

        return $q;
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $categories = $this->listQuery($filters)->paginate($filters['per_page'])->withQueryString()
            ->through(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'url' => route('categories.show', $c->slug),
                'image' => Media::url($c->image_path, 'sm'),
                'sort_order' => $c->sort_order,
                'is_published' => $c->is_published,
                'is_featured' => $c->is_featured,
                'products_count' => $c->products_count,
            ]);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'filters' => $filters,
            'visibilities' => self::VISIBILITY,
            'sorts' => self::SORTS,
            // For "move the products to…" when deleting.
            'allCategories' => Category::query()->ordered()->get(['id', 'name']),
            'total' => Category::query()->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->listQuery($this->filters($request));

        $rows = (function () use ($query) {
            foreach ($query->lazy(200) as $c) {
                yield [$c->id, $c->name, $c->slug, $c->products_count, (bool) $c->is_published, (bool) $c->is_featured, $c->sort_order, route('categories.show', $c->slug)];
            }
        })();

        return CsvExport::download('categories', ['ID', 'Name', 'Slug', 'Products', 'Visible', 'On homepage', 'Display order', 'Link'], $rows);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => [
                'is_published' => true,
                'sort_order' => (int) Category::query()->max('sort_order') + 1,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $category = new Category($data);
        $category->slug = Slug::unique(Category::class, $data['slug'] ?: $data['name']);
        $category->image_path = $this->storeImage($request);
        $category->save();

        return redirect()->route('admin.categories.index')->with('success', $this->savedMessage('Category created.'));
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => [
                ...$category->only('id', 'name', 'slug', 'description', 'sort_order', 'is_published', 'is_featured', 'seo_title', 'seo_description'),
                'url' => route('categories.show', $category->slug),
                'image' => Media::url($category->image_path, 'sm'),
            ],
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $oldImage = $category->image_path;

        $category->fill($data);
        $category->slug = Slug::unique(Category::class, $data['slug'] ?: $data['name'], $category->id);

        if ($request->hasFile('image')) {
            $category->image_path = $this->storeImage($request);
        } elseif ($request->boolean('remove_image')) {
            $category->image_path = null;
        }
        $category->save();

        if ($oldImage && $oldImage !== $category->image_path) {
            $this->images->delete($oldImage);
        }

        return redirect()->route('admin.categories.index')->with('success', $this->savedMessage('Category saved.'));
    }

    /**
     * Categories that still contain products cannot be deleted silently:
     * the admin must either move the products to another category or
     * explicitly confirm they become "uncategorised".
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'products_action' => ['nullable', Rule::in(['reassign', 'uncategorize'])],
            'reassign_to' => ['nullable', 'required_if:products_action,reassign', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$category->id])],
        ], [
            'reassign_to.required_if' => 'Choose the category the products should be moved to.',
            'reassign_to.not_in' => 'Choose a different category.',
        ]);

        $count = $category->products()->count();
        if ($count > 0 && empty($data['products_action'])) {
            throw ValidationException::withMessages([
                'products_action' => "This category still contains {$count} ".Str::plural('product', $count).'. Move them to another category or confirm they should become uncategorised.',
            ]);
        }

        DB::transaction(function () use ($category, $data) {
            if (($data['products_action'] ?? null) === 'reassign') {
                $category->products()->update(['category_id' => $data['reassign_to']]);
            } else {
                $category->products()->update(['category_id' => null]);
            }
            $category->delete();
        });

        $this->images->delete($category->image_path);

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    protected function validated(Request $request, ?Category $category = null): array
    {
        $request->merge([
            'slug' => $request->filled('slug') ? Str::slug((string) $request->input('slug')) : null,
            'is_published' => $request->boolean('is_published'),
            'is_featured' => $request->boolean('is_featured'),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('catalog.max_upload_kb'), 'dimensions:max_width=8000,max_height=8000'],
            'remove_image' => ['nullable', 'boolean'],
        ], [
            'slug.unique' => 'Another category already uses this URL slug.',
            'image.mimes' => 'The image must be a JPEG, PNG or WebP file.',
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['image'], $data['remove_image']);

        return $data;
    }

    protected function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        try {
            return $this->images->store($request->file('image'), 'categories');
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['image' => $e->getMessage()]);
        }
    }
}
