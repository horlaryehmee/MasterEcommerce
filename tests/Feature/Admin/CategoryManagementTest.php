<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_a_category(): void
    {
        $this->actingAsAdmin();
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/categories/create')->assertOk();

        $this->post('/admin/categories', [
            'name' => 'Home Décor',
            'description' => 'Lovely things',
            'sort_order' => 3,
            'is_published' => '1',
            'is_featured' => '1',
            'image' => UploadedFile::fake()->image('decor.jpg', 1200, 1200),
        ])->assertRedirect('/admin/categories');

        $category = Category::firstOrFail();
        $this->assertSame('home-decor', $category->slug);
        $this->assertTrue($category->is_featured);
        Storage::disk('public')->assertExists($category->image_path);
        $oldImage = $category->image_path;

        $this->get("/admin/categories/{$category->id}/edit")->assertOk();
        $this->put("/admin/categories/{$category->id}", [
            'name' => 'Decor',
            'slug' => 'decor',
            'sort_order' => 1,
            'is_published' => '0',
            'remove_image' => '1',
        ])->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertSame('decor', $category->slug);
        $this->assertFalse($category->is_published);
        $this->assertNull($category->image_path);
        Storage::disk('public')->assertMissing($oldImage);
    }

    public function test_category_name_is_required_and_slug_unique(): void
    {
        $this->actingAsAdmin();
        Category::factory()->create(['slug' => 'phones']);

        $this->post('/admin/categories', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/admin/categories', ['name' => 'Phones', 'slug' => 'phones'])->assertSessionHasErrors('slug');
    }

    public function test_category_with_products_cannot_be_deleted_without_a_decision(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $category->id]);

        $this->delete("/admin/categories/{$category->id}")->assertSessionHasErrors('products_action');
        $this->assertModelExists($category);
    }

    public function test_deleting_a_category_can_reassign_its_products(): void
    {
        $this->actingAsAdmin();
        $old = Category::factory()->create();
        $new = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $old->id]);

        $this->delete("/admin/categories/{$old->id}", ['products_action' => 'reassign'])->assertSessionHasErrors('reassign_to');

        $this->delete("/admin/categories/{$old->id}", ['products_action' => 'reassign', 'reassign_to' => $new->id])->assertRedirect('/admin/categories');
        $this->assertModelMissing($old);
        $this->assertSame(2, $new->products()->count());
    }

    public function test_deleting_a_category_can_leave_products_uncategorised(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->delete("/admin/categories/{$category->id}", ['products_action' => 'uncategorize'])->assertRedirect();
        $this->assertNull($product->fresh()->category_id);
    }

    public function test_empty_category_deletes_directly(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        $this->delete("/admin/categories/{$category->id}")->assertRedirect('/admin/categories');
        $this->assertModelMissing($category);
    }
}
