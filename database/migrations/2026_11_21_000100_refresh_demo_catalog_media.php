<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const PRODUCT_IMAGES = [
        'aurora-x5-smartphone-128gb' => ['aurora-x5-smartphone', 3],
        'vertex-lite-5g-smartphone-64gb' => ['vertex-lite-5g-smartphone', 3],
        'nimbus-book-14-laptop' => ['nimbus-book-14-laptop', 2],
        'strata-pro-16-creator-laptop' => ['strata-pro-16-laptop', 2],
        'pulse-anc-wireless-headphones' => ['pulse-anc-wireless-headphones', 2],
        'orbit-mini-bluetooth-speaker' => ['orbit-mini-bluetooth-speaker', 2],
        'halo-43-inch-4k-smart-tv' => ['halo-43-inch-4k-smart-tv', 2],
        'classic-leather-chelsea-boots' => ['classic-leather-chelsea-boots', 2],
        'relaxed-fit-linen-shirt' => ['relaxed-fit-linen-shirt', 2],
        'ankara-print-wrap-dress' => ['ankara-print-wrap-dress', 2],
        'breeze-1-7l-electric-kettle' => ['breeze-electric-kettle', 2],
        'problend-1000w-blender' => ['problend-1000w-blender', 2],
        'coolwave-18-inch-standing-fan' => ['coolwave-18-inch-standing-fan', 2],
        'chrono-steel-analogue-watch' => ['chrono-steel-analogue-watch', 2],
        'powergo-20000mah-power-bank' => ['powergo-20000mah-power-bank', 2],
        'solace-polarised-sunglasses' => ['solace-polarised-sunglasses', 2],
        'glow-vitamin-c-face-serum-30ml' => ['glow-vitamin-c-face-serum', 2],
        'shea-butter-body-lotion-400ml' => ['shea-butter-body-lotion', 2],
        'sage-ceramic-table-lamp' => ['sage-ceramic-table-lamp', 2],
        'amber-soy-candle-set' => ['amber-soy-candle-set', 2],
    ];

    private const CATEGORY_IMAGES = [
        'smartphones' => 'smartphones',
        'laptops' => 'laptops',
        'electronics' => 'electronics',
        'fashion' => 'fashion',
        'home-appliances' => 'home-appliances',
        'accessories' => 'accessories',
        'beauty-and-personal-care' => 'beauty',
        'home-and-living' => 'home-living',
    ];

    private const SLIDES = ['tech', 'home', 'style'];

    public function up(): void
    {
        if (! $this->isDemoCatalog()) {
            return;
        }

        Schema::create('demo_media_refresh_backups', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->longText('paths');
            $table->timestamps();
        });

        $backup = ['products' => [], 'categories' => [], 'slides' => []];
        $disk = Storage::disk('public');

        foreach (self::PRODUCT_IMAGES as $slug => [$asset, $expected]) {
            $product = DB::table('products')->where('slug', $slug)->first(['id']);
            $images = DB::table('product_images')->where('product_id', $product->id)
                ->orderBy('sort_order')->orderBy('id')->limit($expected)->get(['id', 'path']);

            foreach ($images as $index => $image) {
                $number = $index + 1;
                $source = database_path("seeders/demo-images/products/{$asset}-{$number}.webp");
                $path = "products/demo-v1.1.2-{$asset}-{$number}.webp";
                $this->copy($disk, $source, $path);
                $backup['products'][(string) $image->id] = $image->path;
                DB::table('product_images')->where('id', $image->id)->update(['path' => $path]);
            }
        }

        foreach (self::CATEGORY_IMAGES as $slug => $asset) {
            $category = DB::table('categories')->where('slug', $slug)->first(['id', 'image_path']);
            $path = "categories/demo-v1.1.2-{$asset}.webp";
            $this->copy($disk, database_path("seeders/demo-images/categories/{$asset}.webp"), $path);
            $backup['categories'][(string) $category->id] = $category->image_path;
            DB::table('categories')->where('id', $category->id)->update(['image_path' => $path]);
        }

        $slides = DB::table('hero_slides')->orderBy('sort_order')->orderBy('id')->limit(3)->get();
        foreach ($slides as $index => $slide) {
            $asset = self::SLIDES[$index];
            $desktop = "banners/demo-v1.1.2-slide-{$asset}-desktop.webp";
            $mobile = "banners/demo-v1.1.2-slide-{$asset}-mobile.webp";
            $this->copy($disk, database_path("seeders/demo-images/slides/slide-{$asset}-desktop.webp"), $desktop);
            $this->copy($disk, database_path("seeders/demo-images/slides/slide-{$asset}-mobile.webp"), $mobile);
            $backup['slides'][(string) $slide->id] = [
                'desktop' => $slide->desktop_image_path,
                'mobile' => $slide->mobile_image_path,
            ];
            DB::table('hero_slides')->where('id', $slide->id)->update([
                'desktop_image_path' => $desktop,
                'mobile_image_path' => $mobile,
            ]);
        }

        DB::table('demo_media_refresh_backups')->insert([
            'version' => '1.1.2',
            'paths' => json_encode($backup, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('demo_media_refresh_backups')) {
            return;
        }

        $row = DB::table('demo_media_refresh_backups')->where('version', '1.1.2')->first(['paths']);
        $backup = $row ? json_decode($row->paths, true) : null;

        if (is_array($backup)) {
            foreach ($backup['products'] ?? [] as $id => $path) {
                DB::table('product_images')->where('id', $id)->update(['path' => $path]);
            }
            foreach ($backup['categories'] ?? [] as $id => $path) {
                DB::table('categories')->where('id', $id)->update(['image_path' => $path]);
            }
            foreach ($backup['slides'] ?? [] as $id => $paths) {
                DB::table('hero_slides')->where('id', $id)->update([
                    'desktop_image_path' => $paths['desktop'] ?? null,
                    'mobile_image_path' => $paths['mobile'] ?? null,
                ]);
            }
        }

        Schema::drop('demo_media_refresh_backups');
    }

    private function isDemoCatalog(): bool
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_images')
            || ! Schema::hasTable('categories') || ! Schema::hasTable('hero_slides')) {
            return false;
        }

        if (DB::table('products')->whereIn('slug', array_keys(self::PRODUCT_IMAGES))->count() !== count(self::PRODUCT_IMAGES)
            || DB::table('categories')->whereIn('slug', array_keys(self::CATEGORY_IMAGES))->count() !== count(self::CATEGORY_IMAGES)) {
            return false;
        }

        foreach (self::PRODUCT_IMAGES as $slug => [, $expected]) {
            $productId = DB::table('products')->where('slug', $slug)->value('id');
            if (DB::table('product_images')->where('product_id', $productId)->count() < $expected) {
                return false;
            }
        }

        return DB::table('hero_slides')->count() >= 3;
    }

    private function copy($disk, string $source, string $destination): void
    {
        if (! is_file($source)) {
            throw new RuntimeException("Bundled update image is missing: {$source}");
        }

        if (! $disk->put($destination, (string) file_get_contents($source))) {
            throw new RuntimeException("Could not write updated image to the public disk: {$destination}");
        }
    }
};
