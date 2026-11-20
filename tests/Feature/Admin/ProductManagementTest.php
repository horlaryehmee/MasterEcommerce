<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Classic Leather Bag',
            'short_description' => 'A handmade bag.',
            'description' => "Great bag.\n\n- Leather",
            'price' => '25000',
            'compare_at_price' => '30000',
            'availability' => 'in_stock',
            'status' => 'published',
            'is_featured' => '1',
            'is_new_arrival' => '0',
        ], $overrides);
    }

    public function test_product_pages_render(): void
    {
        $this->actingAsAdmin();
        Product::factory()->count(3)->create();

        $this->get('/admin/products')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Products/Index')->has('products.data', 3));
        $this->get('/admin/products/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Products/Form'));
        $this->get('/admin/products/'.Product::first()->id.'/edit')->assertOk();
    }

    public function test_admin_can_create_a_product_with_images(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $response = $this->post('/admin/products', $this->payload([
            'category_id' => $category->id,
            'images' => [
                UploadedFile::fake()->image('front.jpg', 1600, 1600),
                UploadedFile::fake()->image('back.png', 800, 800),
            ],
            'primary_image' => 'new:1',
        ]));

        $product = Product::with('images')->firstOrFail();
        $response->assertRedirect("/admin/products/{$product->id}/edit")->assertSessionHas('success');

        $this->assertSame('classic-leather-bag', $product->slug);
        $this->assertTrue($product->is_featured);
        $this->assertFalse($product->is_new_arrival);
        $this->assertNotNull($product->published_at);
        $this->assertCount(2, $product->images);

        // The second upload was chosen as the main image.
        $primary = $product->images->firstWhere('is_primary', true);
        $this->assertSame(1, $product->images->where('is_primary', true)->count());
        $this->assertSame($product->images->sortBy('sort_order')->last()->id, $primary->id);

        // Files are re-encoded to WebP with random names, plus a small variant; large photos are downsized.
        foreach ($product->images as $image) {
            $this->assertStringEndsWith('.webp', $image->path);
            $this->assertStringNotContainsString('front', $image->path);
            Storage::disk('public')->assertExists([$image->path, Media::variantPath($image->path, 'sm')]);
        }
        $big = $product->images->sortBy('sort_order')->first();
        $this->assertSame(config('catalog.images.products.lg'), getimagesizefromstring(Storage::disk('public')->get($big->path))[0]);
    }

    public function test_validation_rules_are_enforced_on_the_server(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/products', $this->payload(['name' => '', 'price' => 'abc']))->assertSessionHasErrors(['name', 'price']);
        $this->post('/admin/products', $this->payload(['price' => '500', 'compare_at_price' => '400']))->assertSessionHasErrors('compare_at_price');
        $this->post('/admin/products', $this->payload(['availability' => 'teleported']))->assertSessionHasErrors('availability');
        $this->post('/admin/products', $this->payload(['status' => 'live']))->assertSessionHasErrors('status');
        $this->post('/admin/products', $this->payload(['category_id' => 999]))->assertSessionHasErrors('category_id');

        $this->assertSame(0, Product::count());
    }

    public function test_unsafe_uploads_are_rejected(): void
    {
        $this->actingAsAdmin();

        // A PHP script renamed to .jpg is not an image.
        $fakeImage = UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>');
        $this->post('/admin/products', $this->payload(['images' => [$fakeImage]]))->assertSessionHasErrors('images.0');

        $pdf = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $this->post('/admin/products', $this->payload(['images' => [$pdf]]))->assertSessionHasErrors('images.0');

        $gif = UploadedFile::fake()->image('anim.gif');
        $this->post('/admin/products', $this->payload(['images' => [$gif]]))->assertSessionHasErrors('images.0');

        $huge = UploadedFile::fake()->image('huge.jpg')->size(20000);
        $this->post('/admin/products', $this->payload(['images' => [$huge]]))->assertSessionHasErrors('images.0');

        $this->assertSame(0, Product::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_slugs_are_unique_and_editable(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/products', $this->payload());
        $this->post('/admin/products', $this->payload());
        $this->assertEqualsCanonicalizing(['classic-leather-bag', 'classic-leather-bag-2'], Product::pluck('slug')->all());

        $this->post('/admin/products', $this->payload(['slug' => 'My Custom Slug!']));
        $this->assertTrue(Product::where('slug', 'my-custom-slug')->exists());

        // A manually chosen slug that is already taken is reported.
        $this->post('/admin/products', $this->payload(['slug' => 'classic-leather-bag']))->assertSessionHasErrors('slug');
    }

    public function test_admin_can_update_product_remove_images_and_change_primary(): void
    {
        $this->actingAsAdmin();
        $this->post('/admin/products', $this->payload([
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]));
        $product = Product::with('images')->first();
        [$first, $second] = $product->images->sortBy('sort_order')->values()->all();
        $this->assertTrue($first->is_primary);

        $this->put("/admin/products/{$product->id}", $this->payload([
            'name' => 'Renamed Bag',
            'slug' => $product->slug,
            'status' => 'draft',
            'remove_images' => [$first->id],
            'images' => [UploadedFile::fake()->image('c.jpg')],
            'primary_image' => "existing:{$second->id}",
        ]))->assertSessionHasNoErrors();

        $product->refresh()->load('images');
        $this->assertSame('Renamed Bag', $product->name);
        $this->assertSame('draft', $product->status);
        $this->assertCount(2, $product->images);
        $this->assertTrue($product->images->firstWhere('id', $second->id)->is_primary);
        Storage::disk('public')->assertMissing($first->path);
    }

    public function test_removing_the_primary_image_promotes_another(): void
    {
        $this->actingAsAdmin();
        $this->post('/admin/products', $this->payload(['images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]));
        $product = Product::with('images')->first();
        $primary = $product->images->firstWhere('is_primary', true);

        $this->put("/admin/products/{$product->id}", $this->payload(['slug' => $product->slug, 'remove_images' => [$primary->id]]));

        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_quick_toggles_publish_and_feature(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create(['is_featured' => false, 'status' => 'published']);

        $this->patch("/admin/products/{$product->id}/toggle", ['field' => 'status'])->assertRedirect();
        $this->assertSame('draft', $product->fresh()->status);

        $this->patch("/admin/products/{$product->id}/toggle", ['field' => 'is_featured']);
        $this->assertTrue($product->fresh()->is_featured);

        $this->patch("/admin/products/{$product->id}/toggle", ['field' => 'price'])->assertSessionHasErrors('field');
    }

    public function test_admin_can_delete_a_product_and_its_files(): void
    {
        $this->actingAsAdmin();
        $this->post('/admin/products', $this->payload(['images' => [UploadedFile::fake()->image('a.jpg')]]));
        $product = Product::with('images')->first();
        $path = $product->images->first()->path;

        $this->delete("/admin/products/{$product->id}")->assertRedirect('/admin/products');

        $this->assertModelMissing($product);
        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('public')->assertMissing([$path, Media::variantPath($path, 'sm')]);
    }

    public function test_admin_product_list_filters(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create(['name' => 'Red Shoe', 'status' => 'published']);
        Product::factory()->draft()->create(['name' => 'Blue Hat']);

        $this->get('/admin/products?status=draft')->assertInertia(fn (Assert $p) => $p->has('products.data', 1)->where('products.data.0.name', 'Blue Hat'));
        $this->get('/admin/products?search=shoe')->assertInertia(fn (Assert $p) => $p->has('products.data', 1)->where('products.data.0.name', 'Red Shoe'));
    }
}
