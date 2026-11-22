<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\Review;
use App\Services\AdminAlerts;
use App\Services\ReviewGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

/** Customers' product reviews: writing one, and loading more on the product page. */
class ReviewController extends Controller
{
    public const PER_PAGE = 6;

    public const SORTS = ['newest' => 'Newest', 'highest' => 'Highest rated', 'lowest' => 'Lowest rated'];

    public function __construct(protected ReviewGuard $guard) {}

    public function store(Request $request, string $slug): RedirectResponse
    {
        $product = $this->product($slug);

        // Bots (honeypot / too fast / forged form): a normal thank-you, nothing saved.
        if ($this->guard->isBot($request)) {
            return back()->with('review_submitted', true);
        }

        $request->merge([
            'name' => ReviewGuard::clean($request->input('name')),
            'title' => ReviewGuard::clean($request->input('title')),
            'body' => ReviewGuard::clean($request->input('body')),
            'email' => mb_strtolower(trim((string) $request->input('email'))) ?: null,
        ]);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'title' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string', 'min:10', 'max:1500'],
        ], [
            'rating.required' => 'Choose a star rating.',
            'body.min' => 'Please write a little more (at least 10 characters).',
        ], ['body' => 'review', 'name' => 'name']);

        $ip = $this->guard->checkLimits($request, $product, $data['email'] ?? null);
        $order = $this->guard->verifiedOrder($product, $data['email'] ?? null);
        [$status, $flags] = $this->guard->assess($data['title'] ?? '', $data['body'], $data['name'], $order !== null);

        $review = $product->reviews()->create([
            'order_id' => $order?->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'rating' => $data['rating'],
            'title' => $data['title'] ?: null,
            'body' => $data['body'],
            'status' => $status,
            'is_verified' => $order !== null,
            'flags' => $flags ? implode(', ', $flags) : null,
            'ip_hash' => $ip,
            'body_hash' => ReviewGuard::bodyHash($data['body']),
            'approved_at' => $status === 'approved' ? now() : null,
        ]);

        if ($status === 'pending') {
            app(AdminAlerts::class)->reviewWaiting($review);
        }

        return back()->with('review_submitted', $status === 'approved' ? 'published' : true);
    }

    /**
     * More reviews for the product page ("Show more", sorting, star filter).
     * Cursor pages: as fast on the 1,000th page as on the first, no total
     * count per request, and no repeats or gaps when new reviews arrive.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $product = $this->product($slug);
        $page = static::page($product, (string) $request->query('sort'), (int) $request->query('rating'), $request->query('cursor'));

        return response()->json([
            'reviews' => $page->getCollection()->map->toPublic()->values(),
            'next' => $page->nextCursor()?->encode(),
        ]);
    }

    /** One page of approved reviews. Unknown sorts / ratings fall back to newest / all stars. */
    protected static function page(Product $product, string $sort, int $rating, mixed $cursor): CursorPaginator
    {
        $q = $product->reviews()->approved()->when($rating >= 1 && $rating <= 5, fn ($q) => $q->where('rating', $rating));

        match ($sort) {
            'highest' => $q->orderByDesc('rating'),
            'lowest' => $q->orderBy('rating'),
            default => null,
        };

        $q->orderByDesc('created_at')->orderByDesc('id');

        // A broken, forged or mismatched cursor (e.g. from another sort) starts from the first page.
        // (Only the cursor passed in is used: the unused name stops Laravel reading one from the URL.)
        $cursor = is_string($cursor) && strlen($cursor) <= 500 ? Cursor::fromEncoded($cursor) : null;
        try {
            return (clone $q)->cursorPaginate(self::PER_PAGE, ['*'], 'review_cursor', $cursor);
        } catch (\UnexpectedValueException) {
            return $q->cursorPaginate(self::PER_PAGE, ['*'], 'review_cursor', null);
        }
    }

    protected function product(string $slug): Product
    {
        abort_unless(BusinessSetting::current()->reviews_enabled, 404);

        return Product::query()->visible()->where('slug', $slug)->firstOrFail();
    }

    /** Review data for the product page. */
    public static function forProduct(Product $product): ?array
    {
        if (! BusinessSetting::current()->reviews_enabled) {
            return null;
        }

        $first = static::page($product, 'newest', 0, null);
        $counts = $product->reviews()->approved()->toBase()->selectRaw('rating, COUNT(*) as n')->groupBy('rating')->pluck('n', 'rating');

        return [
            'average' => $product->reviews_count ? round((float) $product->rating_avg, 1) : null,
            'count' => (int) $product->reviews_count,
            'breakdown' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($r) => [$r => (int) ($counts[$r] ?? 0)]),
            'items' => $first->getCollection()->map->toPublic()->values(),
            'next' => $first->nextCursor()?->encode(),
            'sorts' => self::SORTS,
            'form_token' => ReviewGuard::formToken(),
            'submitted' => session('review_submitted'),
        ];
    }
}
