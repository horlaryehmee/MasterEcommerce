<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Review;
use App\Support\AdminList;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Moderating customer reviews, and the review settings. */
class ReviewController extends Controller
{
    public const SORTS = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'lowest' => 'Lowest rating', 'highest' => 'Highest rating'];

    public const BULK_ACTIONS = ['approve', 'reject', 'spam', 'delete'];

    protected function filters(Request $request): array
    {
        $rating = (int) $request->query('rating');

        return [
            'search' => AdminList::search($request),
            // Default tab: what needs attention.
            'status' => AdminList::choice($request, 'status', Review::STATUSES + ['all' => 'All']) ?: 'pending',
            'rating' => $rating >= 1 && $rating <= 5 ? (string) $rating : '',
            'verified' => $request->boolean('verified') ? '1' : '',
            'sort' => AdminList::sort($request, self::SORTS),
            'per_page' => AdminList::perPage($request),
        ];
    }

    protected function query(array $f, bool $withStatus = true)
    {
        $like = '%'.addcslashes($f['search'], '%_\\').'%';

        $q = Review::query()
            ->when($withStatus && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when($f['rating'], fn ($q, $r) => $q->where('rating', (int) $r))
            ->when($f['verified'], fn ($q) => $q->where('is_verified', true))
            ->when($f['search'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('body', 'like', $like)->orWhere('title', 'like', $like)
                ->orWhere('email', 'like', $like)->orWhereHas('product', fn ($p) => $p->where('name', 'like', $like))));

        match ($f['sort']) {
            'oldest' => $q->oldest()->oldest('id'),
            'lowest' => $q->orderBy('rating')->latest('id'),
            'highest' => $q->orderByDesc('rating')->latest('id'),
            default => $q->latest()->latest('id'),
        };

        return $q;
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $s = BusinessSetting::current();

        $reviews = $this->query($filters)->with(['product:id,name,slug', 'product.primaryImage'])
            ->paginate($filters['per_page'])->withQueryString()
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'status' => $r->status,
                'status_label' => Review::STATUSES[$r->status] ?? $r->status,
                'verified' => $r->is_verified,
                'flags' => $r->flags,
                'date' => $r->created_at?->format('j M Y, g:i a'),
                'ago' => $r->created_at?->diffForHumans(),
                'product' => $r->product ? ['name' => $r->product->name, 'url' => $r->product->url(), 'image' => Media::url($r->product->primaryImage?->path, 'sm')] : null,
                'order_url' => $r->order_id ? route('admin.orders.show', $r->order_id) : null,
            ]);

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $filters,
            'statuses' => Review::STATUSES,
            'sorts' => self::SORTS,
            'counts' => $this->query($filters, false)->reorder()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'settings' => [
                'reviews_enabled' => $s->reviews_enabled,
                'reviews_home_enabled' => $s->reviews_home_enabled,
                'reviews_auto_publish' => $s->reviews_auto_publish,
            ],
            'autoPublishOptions' => Review::AUTO_PUBLISH,
            'home' => [
                'minimum' => Review::HOME_MINIMUM,
                'eligible' => Review::query()->approved()->where('rating', '>=', 4)->count(),
            ],
        ]);
    }

    /** Approve / reject / mark as spam one review. */
    public function update(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Review::STATUSES))]]);
        $this->setStatus($review, $data['status']);

        return back()->with('success', match ($data['status']) {
            'approved' => 'Review published.',
            'rejected' => 'Review rejected.',
            'spam' => 'Marked as spam.',
            default => 'Review updated.',
        });
    }

    public function bulk(Request $request): RedirectResponse
    {
        $bulk = AdminList::bulk($request, self::BULK_ACTIONS);
        $reviews = Review::query()->whereIn('id', $bulk['ids'])->with('product')->get();
        $n = AdminList::count($reviews->count(), 'review');

        foreach ($reviews as $review) {
            match ($bulk['action']) {
                'approve' => $this->setStatus($review, 'approved'),
                'reject' => $this->setStatus($review, 'rejected'),
                'spam' => $this->setStatus($review, 'spam'),
                'delete' => $review->delete(),
            };
        }

        return back()->with('success', $n.match ($bulk['action']) {
            'approve' => ' published.',
            'reject' => ' rejected.',
            'spam' => ' marked as spam.',
            'delete' => ' deleted.',
        });
    }

    public function settings(Request $request): RedirectResponse
    {
        $request->merge(['reviews_enabled' => $request->boolean('reviews_enabled'), 'reviews_home_enabled' => $request->boolean('reviews_home_enabled')]);
        $data = $request->validate([
            'reviews_enabled' => ['boolean'],
            'reviews_home_enabled' => ['boolean'],
            'reviews_auto_publish' => ['required', Rule::in(array_keys(Review::AUTO_PUBLISH))],
        ]);
        BusinessSetting::current()->update($data);

        return back()->with('success', 'Review settings saved.');
    }

    protected function setStatus(Review $review, string $status): void
    {
        $review->update([
            'status' => $status,
            'approved_at' => $status === 'approved' ? ($review->approved_at ?? now()) : null,
        ]);
    }
}
