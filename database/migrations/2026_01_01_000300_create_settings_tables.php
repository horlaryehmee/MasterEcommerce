<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table: business profile, branding, currency, WhatsApp,
        // footer and SEO defaults for this installation.
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name', 120)->default('My Business');
            $table->text('business_description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('email', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->text('address')->nullable();
            $table->text('opening_hours')->nullable();

            $table->string('primary_color', 7)->default('#111827');
            $table->string('secondary_color', 7)->default('#F4F1EC');
            $table->string('accent_color', 7)->default('#C2410C');

            $table->string('currency_code', 3)->default('NGN');
            $table->string('currency_symbol', 8)->default('₦');
            $table->string('currency_position', 6)->default('before');
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->string('thousands_separator', 2)->default(',');
            $table->string('decimal_separator', 2)->default('.');

            $table->text('whatsapp_general_message')->nullable();
            $table->text('whatsapp_product_message')->nullable();
            $table->boolean('show_floating_whatsapp')->default(true);

            $table->text('footer_description')->nullable();
            $table->string('copyright_text', 200)->nullable();

            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('og_image_path')->nullable();
            $table->timestamps();
        });

        // Single-row table: editable homepage sections.
        Schema::create('homepage_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('announcement_enabled')->default(false);
            $table->string('announcement_text', 255)->nullable();
            $table->string('announcement_link', 255)->nullable();
            $table->string('announcement_bg_color', 7)->default('#111827');
            $table->string('announcement_text_color', 7)->default('#FFFFFF');

            $table->boolean('hero_enabled')->default(true);
            $table->string('hero_eyebrow', 80)->nullable();
            $table->string('hero_heading', 160)->nullable();
            $table->text('hero_description')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('hero_primary_text', 40)->nullable();
            $table->string('hero_primary_link', 255)->nullable();
            $table->string('hero_secondary_text', 40)->nullable();
            $table->string('hero_secondary_link', 255)->nullable();

            $table->boolean('categories_enabled')->default(true);
            $table->string('categories_heading', 120)->nullable();
            $table->string('categories_subheading', 255)->nullable();

            $table->boolean('featured_enabled')->default(true);
            $table->string('featured_heading', 120)->nullable();
            $table->string('featured_subheading', 255)->nullable();

            $table->boolean('new_arrivals_enabled')->default(true);
            $table->string('new_arrivals_heading', 120)->nullable();
            $table->string('new_arrivals_subheading', 255)->nullable();
            $table->unsignedTinyInteger('new_arrivals_count')->default(8);

            $table->boolean('promo_enabled')->default(false);
            $table->string('promo_image_path')->nullable();
            $table->string('promo_eyebrow', 80)->nullable();
            $table->string('promo_heading', 160)->nullable();
            $table->text('promo_description')->nullable();
            $table->string('promo_button_text', 40)->nullable();
            $table->string('promo_button_link', 255)->nullable();

            $table->boolean('about_enabled')->default(true);
            $table->string('about_heading', 160)->nullable();
            $table->text('about_body')->nullable();
            $table->string('about_image_path')->nullable();

            $table->boolean('why_enabled')->default(true);
            $table->string('why_heading', 120)->nullable();
            $table->string('why_subheading', 255)->nullable();
            // List of {icon, title, text} selling points.
            $table->json('why_items')->nullable();

            $table->timestamps();
        });

        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->unique();
            $table->string('url', 255);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->longText('content')->nullable();
            $table->boolean('is_published')->default(true);
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('social_links');
        Schema::dropIfExists('homepage_settings');
        Schema::dropIfExists('business_settings');
    }
};
