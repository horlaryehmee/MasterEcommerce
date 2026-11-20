<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_essential_seeder_is_repeatable_and_never_overwrites(): void
    {
        $this->seed(EssentialSeeder::class);
        Page::where('slug', 'about')->update(['title' => 'Our Story']);
        $this->seed(EssentialSeeder::class);

        $this->assertSame(6, Page::count());
        $this->assertSame('Our Story', Page::where('slug', 'about')->value('title'));
        $this->assertSame(1, BusinessSetting::count());
        $this->assertSame(1, HomepageSetting::count());
    }

    public function test_demo_seeder_builds_a_complete_site_and_is_repeatable(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame('Nexa Stores', BusinessSetting::current()->business_name);
        $this->assertSame(8, Category::count());
        $this->assertGreaterThanOrEqual(16, Product::count());
        $this->assertSame(Product::count(), Product::has('primaryImage')->count());
        $this->assertSame(1, User::count());
        Storage::disk('public')->assertExists(BusinessSetting::current()->logo_path);
        $this->assertNotNull(HomepageSetting::current()->hero_image_path);

        $counts = [Product::count(), ProductImage::count(), Category::count(), User::count()];
        $this->seed(DemoSeeder::class);
        $this->assertSame($counts, [Product::count(), ProductImage::count(), Category::count(), User::count()]);

        $this->get('/')->assertOk();
    }

    public function test_demo_seeder_refuses_to_touch_a_client_site(): void
    {
        $this->seed(EssentialSeeder::class);
        BusinessSetting::current()->update(['business_name' => 'Real Client Ltd']);
        Product::factory()->create(['name' => 'Client Product']);

        $this->seed(DemoSeeder::class);

        $this->assertSame('Real Client Ltd', BusinessSetting::query()->value('business_name'));
        $this->assertSame(1, Product::count());
        $this->assertSame(0, User::count());
    }
}
