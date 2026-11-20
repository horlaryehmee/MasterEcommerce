<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ColorProfile;
use App\Services\ImageStorage;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    /** A minimal but structurally valid RGB ICC profile (not sRGB). */
    protected function fakeP3Profile(): string
    {
        $icc = str_repeat("\0", 200);
        $icc = substr_replace($icc, pack('N', 200), 0, 4);
        $icc = substr_replace($icc, 'RGB ', 16, 4);
        $icc = substr_replace($icc, 'XYZ ', 20, 4);
        $icc = substr_replace($icc, 'acsp', 36, 4);

        return substr_replace($icc, 'Display P3 test', 150, 15);
    }

    /** A "photo": smooth gradients plus fine noise, like a camera image. */
    protected function photoJpeg(int $w, int $h, ?string $icc = null, int $orientation = 1): string
    {
        // Build a small noisy gradient and scale it up (fast, and photo-like).
        $sw = max(2, intdiv($w, 16));
        $sh = max(2, intdiv($h, 16));
        $seed = imagecreatetruecolor($sw, $sh);
        for ($x = 0; $x < $sw; $x++) {
            for ($y = 0; $y < $sh; $y++) {
                imagesetpixel($seed, $x, $y, imagecolorallocate($seed, (int) ($x * 255 / $sw), (int) ($y * 255 / $sh), mt_rand(60, 180)));
            }
        }
        $img = imagescale($seed, $w, $h, IMG_BICUBIC);
        ob_start();
        imagejpeg($img, null, 92);
        $jpeg = ob_get_clean();

        if ($icc) {
            $jpeg = ColorProfile::embedInJpeg($jpeg, $icc);
        }
        if ($orientation !== 1) {
            $jpeg = $this->withOrientation($jpeg, $orientation);
        }

        return $jpeg;
    }

    protected function withOrientation(string $jpeg, int $orientation): string
    {
        // Minimal EXIF APP1: little-endian TIFF with one IFD entry (Orientation).
        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, $orientation, 0).pack('V', 0);
        $app1 = "Exif\0\0".$tiff;

        return "\xFF\xD8\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
    }

    protected function upload(string $bytes, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_photos_are_resized_with_variants_and_never_enlarged(): void
    {
        $svc = app(ImageStorage::class);

        $big = $svc->store($this->upload($this->photoJpeg(3200, 2400), 'big.jpg'), 'products');
        $disk = Storage::disk('public');
        $this->assertSame([1600, 1200], array_slice(getimagesizefromstring($disk->get($big)), 0, 2));
        $this->assertSame(1000, getimagesizefromstring($disk->get(Media::variantPath($big, 'md')))[0]);
        $this->assertSame(600, getimagesizefromstring($disk->get(Media::variantPath($big, 'sm')))[0]);

        // A small image keeps its size (no blurry upscaling).
        $small = $svc->store($this->upload($this->photoJpeg(500, 400), 'small.jpg'), 'products');
        $this->assertSame(500, getimagesizefromstring($disk->get($small))[0]);
        $this->assertSame(500, getimagesizefromstring($disk->get(Media::variantPath($small, 'sm')))[0]);
    }

    public function test_photos_use_high_quality_lossy_webp_and_are_much_smaller(): void
    {
        $svc = app(ImageStorage::class);
        $original = $this->photoJpeg(3200, 2400);
        $path = $svc->store($this->upload($original, 'p.jpg'), 'products');
        $stored = Storage::disk('public')->get($path);

        $this->assertStringStartsWith('RIFF', $stored);
        $this->assertStringNotContainsString('VP8L', substr($stored, 0, 64)); // lossy
        $this->assertLessThan(strlen($original), strlen($stored));
        $this->assertSame(1, $svc->stats()['count']);
        $this->assertStringContainsString('1 image optimised', $svc->summary());
    }

    public function test_colour_profile_is_kept_in_webp_and_jpeg_output(): void
    {
        $icc = $this->fakeP3Profile();
        $source = $this->photoJpeg(1200, 900, $icc);
        $this->assertSame($icc, ColorProfile::extract($source, IMAGETYPE_JPEG));

        $svc = app(ImageStorage::class);
        $disk = Storage::disk('public');

        $webp = $disk->get($svc->store($this->upload($source, 'p3.jpg'), 'products'));
        $this->assertSame($icc, ColorProfile::extract($webp, IMAGETYPE_WEBP));
        $this->assertSame('VP8X', substr($webp, 12, 4));
        $this->assertNotFalse(@imagecreatefromstring($webp), 'Extended WebP must still decode.');

        $jpg = $disk->get($svc->store($this->upload($source, 'p3.jpg'), 'banners', 'jpg'));
        $this->assertSame($icc, ColorProfile::extract($jpg, IMAGETYPE_JPEG));
        $this->assertNotFalse(@imagecreatefromstring($jpg));
    }

    public function test_srgb_and_non_rgb_profiles_are_not_reattached(): void
    {
        $srgb = substr_replace($this->fakeP3Profile(), 'sRGB IEC61966', 150, 13);
        $this->assertNull(ColorProfile::extract($this->photoJpeg(100, 100, $srgb), IMAGETYPE_JPEG));

        $gray = substr_replace($this->fakeP3Profile(), 'GRAY', 16, 4);
        $this->assertNull(ColorProfile::extract($this->photoJpeg(100, 100, $gray), IMAGETYPE_JPEG));
    }

    public function test_logos_and_graphics_are_stored_losslessly_with_transparency(): void
    {
        $img = imagecreatetruecolor(600, 200);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagefilledrectangle($img, 20, 20, 180, 180, imagecolorallocate($img, 194, 65, 12));
        imagestring($img, 5, 220, 90, 'Ada Lighting', imagecolorallocate($img, 17, 24, 39));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        $path = app(ImageStorage::class)->store($this->upload($png, 'logo.png'), 'branding');
        $stored = Storage::disk('public')->get($path);
        $this->assertStringContainsString('VP8L', substr($stored, 0, 64)); // lossless

        // Every pixel is identical to the original (size unchanged, lossless).
        $out = imagecreatefromstring($stored);
        foreach ([[100, 100], [20, 20], [180, 180]] as [$x, $y]) {
            $this->assertSame(imagecolorsforindex($img, imagecolorat($img, $x, $y)), imagecolorsforindex($out, imagecolorat($out, $x, $y)));
        }
        // Transparent areas stay fully transparent.
        $this->assertSame(127, imagecolorsforindex($out, imagecolorat($out, 5, 5))['alpha']);
        $this->assertSame(127, imagecolorsforindex($out, imagecolorat($out, 599, 199))['alpha']);
    }

    public function test_photos_saved_as_png_use_lossy_webp_to_stay_small(): void
    {
        ob_start();
        imagepng(imagecreatefromstring($this->photoJpeg(1200, 900)));
        $png = ob_get_clean();

        $path = app(ImageStorage::class)->store($this->upload($png, 'photo.png'), 'products');
        $stored = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString('VP8L', substr($stored, 0, 64));
        $this->assertLessThan(strlen($png) / 4, strlen($stored));
    }

    public function test_phone_rotation_is_corrected(): void
    {
        // Orientation 6 = the camera was rotated 90°; stored pixels are landscape.
        $path = app(ImageStorage::class)->store($this->upload($this->photoJpeg(800, 400, null, 6), 'rotated.jpg'), 'products');
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([400, 800], [$w, $h]);
    }

    public function test_admin_sees_how_much_images_were_optimised(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/products', [
            'name' => 'Lamp', 'price' => 100, 'availability' => 'in_stock', 'status' => 'published',
            'images' => [$this->upload($this->photoJpeg(2400, 1800), 'a.jpg')],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Product created.') && str_contains($m, '1 image optimised:'));
    }

    public function test_srcset_only_lists_existing_variants_and_regenerate_fills_gaps(): void
    {
        $svc = app(ImageStorage::class);
        $disk = Storage::disk('public');
        $path = $svc->store($this->upload($this->photoJpeg(2000, 1500), 'p.jpg'), 'products');
        $product = Product::factory()->create();
        ProductImage::forceCreate(['product_id' => $product->id, 'path' => $path, 'is_primary' => true]);

        // Simulate an image uploaded before the "md" size existed.
        $disk->delete(Media::variantPath($path, 'md'));
        $image = Media::image($path, 'x');
        $this->assertStringNotContainsString('-md.', $image['srcset']);
        $this->assertStringContainsString('-sm.webp 600w', $image['srcset']);

        $this->artisan('catalog:regenerate-images')->assertSuccessful();

        $disk->assertExists(Media::variantPath($path, 'md'));
        $this->assertStringContainsString('-md.webp 1000w', Media::image($path, 'x')['srcset']);

        // Full-size originals are never rewritten.
        $before = $disk->get($path);
        $this->artisan('catalog:regenerate-images', ['--force' => true])->assertSuccessful();
        $this->assertSame($before, $disk->get($path));
    }
}
