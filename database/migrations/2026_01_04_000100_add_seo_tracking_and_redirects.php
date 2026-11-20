<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO & marketing settings (Admin → Website Settings): search engine
 * visibility, verification codes, custom meta tags, analytics/ad pixels,
 * custom head/body/footer code, extra robots.txt rules, and URL redirects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('search_engines_visible')->default(true)->after('og_image_path');
            $table->string('title_separator', 5)->default('|')->after('search_engines_visible');
            $table->string('twitter_handle', 40)->nullable()->after('title_separator');
            $table->text('robots_extra')->nullable()->after('twitter_handle');

            // Site verification (content values of the meta tags).
            $table->string('verify_google', 200)->nullable();
            $table->string('verify_bing', 200)->nullable();
            $table->string('verify_yandex', 200)->nullable();
            $table->string('verify_pinterest', 200)->nullable();
            $table->string('verify_facebook', 200)->nullable();
            // [{attribute: name|property|http-equiv, key, content}]
            $table->json('custom_meta')->nullable();

            // Analytics and ads.
            $table->string('ga4_id', 30)->nullable();
            $table->string('gtm_id', 30)->nullable();
            $table->string('google_ads_id', 30)->nullable();
            $table->string('google_ads_purchase_label', 80)->nullable();
            $table->string('meta_pixel_id', 30)->nullable();
            $table->string('tiktok_pixel_id', 40)->nullable();
            $table->boolean('track_admins')->default(false);

            // Raw code added to every storefront page.
            $table->longText('head_code')->nullable();
            $table->longText('body_code')->nullable();
            $table->longText('footer_code')->nullable();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 255)->unique();
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            // Created automatically when a product or category URL changes.
            $table->boolean('is_automatic')->default(false);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'search_engines_visible', 'title_separator', 'twitter_handle', 'robots_extra',
                'verify_google', 'verify_bing', 'verify_yandex', 'verify_pinterest', 'verify_facebook', 'custom_meta',
                'ga4_id', 'gtm_id', 'google_ads_id', 'google_ads_purchase_label', 'meta_pixel_id', 'tiktok_pixel_id', 'track_admins',
                'head_code', 'body_code', 'footer_code',
            ]);
        });
    }
};
