<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        $this->product = Product::factory()->create(['name' => 'Blue Kettle', 'status' => 'published']);
    }

    /** A form token from a page shown $secondsAgo seconds ago. */
    protected function token(int $secondsAgo = 30): string
    {
        return Crypt::encryptString((string) (time() - $secondsAgo));
    }

    protected function review(array $overrides = [], ?string $ip = null): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip ?? '10.0.0.'.random_int(1, 250)])
            ->from($this->product->url())
            ->post("/products/{$this->product->slug}/reviews", $overrides + [
                'rating' => 5,
                'name' => 'Ada Obi',
                'email' => '',
                'title' => 'Boils fast',
                'body' => 'Really happy with this kettle. It boils quickly and looks great.',
                'website' => '',
                'form_token' => $this->token(),
            ]);
    }

    protected function settings(array $values): void
    {
        BusinessSetting::current()->update($values);
        BusinessSetting::forgetResolved();
    }

    public function test_a_review_waits_for_approval_then_shows_with_the_rating(): void
    {
        $this->review()->assertRedirect($this->product->url())->assertSessionHas('review_submitted', true);

        $review = Review::firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->get($this->product->url())->assertInertia(fn (Assert $p) => $p->where('reviews.count', 0)->has('reviews.items', 0));

        $this->actingAsAdmin();
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('admin.pending_reviews', 1));
        $this->patch("/admin/reviews/{$review->id}", ['status' => 'approved'])->assertSessionHas('success');

        $this->product->refresh();
        $this->assertSame(1, $this->product->reviews_count);
        $this->assertSame('5.00', $this->product->rating_avg);

        $this->get($this->product->url())->assertInertia(fn (Assert $p) => $p
            ->where('reviews.count', 1)
            ->where('reviews.average', 5)
            ->where('reviews.items.0.name', 'Ada Obi')
            ->missing('reviews.items.0.email')
            ->where('product.rating.count', 1));
        $this->get('/products')->assertInertia(fn (Assert $p) => $p->where('products.data.0.rating.average', 5));
    }

    public function test_bots_are_silently_ignored(): void
    {
        // Honeypot filled, sent 1 second after the page loaded, forged token, missing token.
        $this->review(['website' => 'http://spam.example'])->assertSessionHas('review_submitted', true);
        $this->review(['form_token' => $this->token(1)])->assertSessionHas('review_submitted', true);
        $this->review(['form_token' => 'not-a-real-token'])->assertSessionHas('review_submitted', true);
        $this->review(['form_token' => ''])->assertSessionHas('review_submitted', true);
        $this->review(['form_token' => $this->token(13 * 3600)])->assertSessionHas('review_submitted', true);

        $this->assertSame(0, Review::count());
    }

    public function test_spammy_content_goes_to_spam(): void
    {
        $this->review(['body' => 'Great deals at https://cheap-stuff.example go now now now']);
        $this->review(['body' => 'Amazing investment opportunity, message me for bitcoin returns']);
        $this->assertSame(['spam', 'spam'], Review::pluck('status')->all());
        $this->assertStringContainsString('contains a link', Review::first()->flags);

        // A copy of an existing review.
        $this->review(['body' => 'Copied text that someone posts on many products to spam.'], '10.1.1.1');
        $this->product = Product::factory()->create(['status' => 'published']);
        $this->review(['body' => 'Copied  text that someone posts on many products to spam.'], '10.1.1.2');
        $this->assertSame('spam', Review::latest('id')->first()->status);
        $this->assertSame('copy of another review', Review::latest('id')->first()->flags);
    }

    public function test_html_is_stripped(): void
    {
        $this->review(['name' => '<b>Ada</b>', 'body' => '<script>alert(1)</script>Lovely kettle, works perfectly every day.']);
        $review = Review::firstOrFail();
        $this->assertSame('Ada', $review->name);
        $this->assertStringNotContainsString('<script>', $review->body);
    }

    public function test_rate_limits_and_one_review_per_product(): void
    {
        $ip = '10.9.9.9';
        $this->review([], $ip)->assertSessionHasNoErrors();
        // Same visitor, same product.
        $this->review(['body' => 'Another opinion about the same kettle, still good.'], $ip)->assertSessionHasErrors('body');

        foreach (range(1, 3) as $i) {
            $this->product = Product::factory()->create(['status' => 'published']);
            $this->review(['body' => "Different product review number {$i}, all fine here."], $ip);
        }
        // 4th review in 10 minutes from one visitor: refused.
        $this->assertSame(3, Review::count());
    }

    public function test_verified_buyers_get_a_badge_and_can_be_published_automatically(): void
    {
        $order = Order::create([
            'payment_method' => 'delivery', 'payment_status' => 'paid', 'status' => 'completed',
            'customer_name' => 'Ada', 'customer_phone' => '0801 234 5678', 'customer_email' => 'ada@example.com',
            'shipping_country' => 'NG', 'subtotal' => 10000, 'total' => 10000, 'currency_code' => 'NGN',
        ]);
        $order->items()->create(['product_id' => $this->product->id, 'product_name' => 'Blue Kettle', 'unit_price' => 10000, 'quantity' => 1, 'line_total' => 10000]);
        $this->settings(['reviews_auto_publish' => 'verified']);

        $this->review(['email' => 'ADA@example.com'])->assertSessionHas('review_submitted', 'published');
        $review = Review::firstOrFail();
        $this->assertTrue($review->is_verified);
        $this->assertSame('approved', $review->status);
        $this->assertSame($order->id, $review->order_id);

        // Not a buyer: still waits for approval.
        $this->product = Product::factory()->create(['status' => 'published']);
        $this->review(['email' => 'someone@example.com', 'body' => 'Bought it in a shop nearby, works well so far.']);
        $this->assertSame('pending', Review::latest('id')->first()->status);
    }

    public function test_reviews_can_be_switched_off(): void
    {
        Review::create(['product_id' => $this->product->id, 'name' => 'A', 'rating' => 4, 'body' => 'Good kettle overall here.', 'status' => 'approved', 'approved_at' => now(), 'body_hash' => 'x']);
        $this->settings(['reviews_enabled' => false]);

        $this->get($this->product->url())->assertInertia(fn (Assert $p) => $p->where('reviews', null)->where('product.rating', null));
        $this->review()->assertNotFound();
        $this->getJson("/products/{$this->product->slug}/reviews")->assertNotFound();
    }

    public function test_homepage_section_needs_five_good_reviews_and_can_be_switched_off(): void
    {
        $make = fn (int $rating) => Review::create(['product_id' => $this->product->id, 'name' => 'Customer', 'rating' => $rating, 'body' => 'Very good product indeed '.uniqid(), 'status' => 'approved', 'approved_at' => now(), 'body_hash' => uniqid()]);

        foreach (range(1, 4) as $i) {
            $make(5);
        }
        $make(2); // low ratings don't count towards the section
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('reviews', null));

        $make(4);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('reviews.items', 5)->where('reviews.total', 6)->where('reviews.items.0.product.name', 'Blue Kettle'));

        $this->settings(['reviews_home_enabled' => false]);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('reviews', null));
    }

    public function test_reviews_page_through_with_cursors_filters_and_bad_cursors(): void
    {
        foreach (range(1, 20) as $i) {
            $r = Review::create(['product_id' => $this->product->id, 'name' => "Customer {$i}", 'rating' => $i % 5 + 1, 'body' => "Review number {$i} of the kettle.", 'status' => 'approved', 'approved_at' => now(), 'body_hash' => "h{$i}"]);
            $r->created_at = now()->subMinutes(100 - $i); // review 20 is the newest
            $r->save();
        }
        Review::create(['product_id' => $this->product->id, 'name' => 'Hidden', 'rating' => 5, 'body' => 'Waiting for approval here.', 'status' => 'pending', 'body_hash' => 'p']);

        // First page comes with the product page, then "Show more" walks the rest.
        $seen = [];
        $this->get($this->product->url())->assertInertia(function (Assert $p) use (&$seen, &$cursor) {
            $props = $p->toArray()['props']['reviews'];
            $seen = array_column($props['items'], 'name');
            $cursor = $props['next'];
        });
        $this->assertSame('Customer 20', $seen[0]);
        while ($cursor) {
            $page = $this->getJson("/products/{$this->product->slug}/reviews?".http_build_query(['cursor' => $cursor]))->assertOk()->json();
            $seen = [...$seen, ...array_column($page['reviews'], 'name')];
            $cursor = $page['next'];
        }
        $this->assertCount(20, $seen);
        $this->assertCount(20, array_unique($seen));
        $this->assertNotContains('Hidden', $seen);

        // Star filter + sort.
        $five = $this->getJson("/products/{$this->product->slug}/reviews?rating=5")->json('reviews');
        $this->assertCount(4, $five);
        $this->assertSame([5], array_values(array_unique(array_column($five, 'rating'))));
        $this->assertSame(1, $this->getJson("/products/{$this->product->slug}/reviews?sort=lowest")->json('reviews.0.rating'));

        // Forged, garbage or mismatched cursors never error: they start from the top.
        $newestCursor = $this->getJson("/products/{$this->product->slug}/reviews")->json('next');
        foreach ([base64_encode('{"id":"1 OR 1=1","_pointsToNextItems":true}'), 'garbage', str_repeat('A', 2000)] as $bad) {
            $this->getJson("/products/{$this->product->slug}/reviews?".http_build_query(['cursor' => $bad]))->assertOk();
        }
        $this->getJson("/products/{$this->product->slug}/reviews?".http_build_query(['cursor' => $newestCursor, 'sort' => 'highest']))
            ->assertOk()->assertJsonPath('reviews.0.rating', 5);
    }

    public function test_google_gets_the_star_rating(): void
    {
        Review::create(['product_id' => $this->product->id, 'name' => 'Ada', 'rating' => 4, 'body' => 'Good kettle overall here.', 'status' => 'approved', 'approved_at' => now(), 'body_hash' => 'y']);

        $html = $this->get($this->product->url())->getContent();
        $this->assertStringContainsString('"aggregateRating":{"@type":"AggregateRating","ratingValue":4', $html);
        $this->assertStringContainsString('"reviewCount":1', $html);
    }

    public function test_admin_moderates_in_bulk_and_changes_settings(): void
    {
        foreach (range(1, 3) as $i) {
            $this->review(['body' => "Honest review number {$i} about the kettle."]);
            $this->product = Product::factory()->create(['status' => 'published']);
        }
        $ids = Review::pluck('id')->all();
        $this->actingAsAdmin();

        $this->get('/admin/reviews')->assertOk()->assertInertia(fn (Assert $p) => $p->has('reviews.data', 3)->where('filters.status', 'pending'));
        $this->post('/admin/reviews/bulk', ['action' => 'approve', 'ids' => array_slice($ids, 0, 2)])->assertSessionHas('success', '2 reviews published.');
        $this->post('/admin/reviews/bulk', ['action' => 'delete', 'ids' => [$ids[2]]]);
        $this->assertSame(['approved', 'approved'], Review::pluck('status')->all());
        $this->get('/admin/reviews?status=all')->assertInertia(fn (Assert $p) => $p->has('reviews.data', 2));

        $this->put('/admin/reviews/settings', ['reviews_enabled' => '1', 'reviews_home_enabled' => '0', 'reviews_auto_publish' => 'manual'])->assertSessionHas('success');
        BusinessSetting::forgetResolved();
        $this->assertFalse(BusinessSetting::current()->reviews_home_enabled);
    }
}
