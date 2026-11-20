<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            // Hero design, chosen separately for desktop/tablet and for phones:
            // "split" (text beside image), "banner" (full image with text on top), "slider".
            $table->string('hero_type_desktop', 10)->default('split')->after('hero_enabled');
            $table->string('hero_type_mobile', 10)->default('split')->after('hero_type_desktop');
            // Banner/slider width: "boxed" (rounded, inside the page margins) or "full" (edge to edge).
            $table->string('hero_width', 10)->default('boxed')->after('hero_type_mobile');
            // Optional phone-specific image for the split/banner designs (e.g. portrait crop).
            $table->string('hero_mobile_image_path')->nullable()->after('hero_image_path');
            // Banner text alignment: left or center.
            $table->string('hero_text_align', 10)->default('left')->after('hero_mobile_image_path');

            // Slider options.
            $table->boolean('hero_autoplay')->default(true)->after('hero_text_align');
            $table->unsignedTinyInteger('hero_interval')->default(6)->after('hero_autoplay');
            $table->string('hero_desktop_height', 10)->default('standard')->after('hero_interval');
            $table->string('hero_mobile_height', 10)->default('portrait')->after('hero_desktop_height');
            // Phones: show banner text "below" the image (never covers it) or "overlay" on top of it.
            $table->string('hero_mobile_text', 10)->default('below')->after('hero_mobile_height');
        });

        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('desktop_image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('eyebrow', 80)->nullable();
            $table->string('heading', 160)->nullable();
            $table->string('description', 300)->nullable();
            $table->string('button_text', 40)->nullable();
            $table->string('button_link', 255)->nullable();
            $table->string('secondary_text', 40)->nullable();
            $table->string('secondary_link', 255)->nullable();
            // "text" = show heading/buttons over the image; "image" = image only (text is part of the design).
            $table->string('content_mode', 10)->default('text');
            // Where clicking an image-only slide goes.
            $table->string('link', 255)->nullable();
            $table->string('text_position', 10)->default('left'); // left | center | right
            $table->string('text_theme', 10)->default('light');   // light text on dark shade | dark text on light shade
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hero_type_desktop', 'hero_type_mobile', 'hero_width', 'hero_mobile_image_path', 'hero_text_align',
                'hero_autoplay', 'hero_interval', 'hero_desktop_height', 'hero_mobile_height', 'hero_mobile_text',
            ]);
        });
    }
};
