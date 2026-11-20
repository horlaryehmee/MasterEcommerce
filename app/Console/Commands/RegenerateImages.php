<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\ProductImage;
use App\Services\ImageStorage;
use Illuminate\Console\Command;
use Throwable;

/**
 * Creates any missing smaller copies (thumbnails, phone sizes) of uploaded
 * images, e.g. after updating to a version with new image sizes.
 * The full-size images are never modified.
 */
class RegenerateImages extends Command
{
    protected $signature = 'catalog:regenerate-images {--force : Rebuild every size variant, not only missing ones}';

    protected $description = 'Create missing responsive size variants for all uploaded images';

    public function handle(ImageStorage $images): int
    {
        $paths = collect()
            ->merge(ProductImage::query()->pluck('path'))
            ->merge(Category::query()->pluck('image_path'))
            ->merge(Page::query()->pluck('image_path'))
            ->merge(HeroSlide::query()->pluck('desktop_image_path'))
            ->merge(HeroSlide::query()->pluck('mobile_image_path'))
            ->merge(collect([BusinessSetting::current()])->flatMap->only(['logo_path', 'favicon_path', 'og_image_path']))
            ->merge(collect([HomepageSetting::current()])->flatMap->only(['hero_image_path', 'promo_image_path', 'about_image_path']))
            ->filter()->unique()->values();

        $written = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($paths->count());

        foreach ($paths as $path) {
            try {
                $written += $images->regenerateVariants($path, (bool) $this->option('force'));
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("Skipped {$path}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Checked {$paths->count()} images, wrote {$written} size variants".($failed ? ", {$failed} skipped" : '').'.');

        return self::SUCCESS;
    }
}
