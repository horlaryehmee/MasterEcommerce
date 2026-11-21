<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand fonts and button shape (Website Settings → Branding), a scrolling text
 * strip under the hero, and hero slider dots/shade options.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            // Null = the built-in font.
            $table->string('heading_font', 60)->nullable()->after('product_card_style');
            $table->string('body_font', 60)->nullable()->after('heading_font');
            // Null = each heading keeps its designed weight.
            $table->unsignedSmallInteger('heading_weight')->nullable()->after('body_font');
            $table->string('button_shape', 10)->default('pill')->after('heading_weight');
        });

        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->string('hero_dots_position', 10)->default('center')->after('hero_mobile_text');
            $table->string('hero_shade', 10)->default('strong')->after('hero_dots_position');

            $table->boolean('marquee_enabled')->default(false)->after('hero_image_path');
            $table->json('marquee_items')->nullable()->after('marquee_enabled');
            $table->string('marquee_separator', 8)->default('✱')->after('marquee_items');
            $table->string('marquee_bg_color', 7)->default('#FFFFFF')->after('marquee_separator');
            $table->string('marquee_text_color', 7)->default('#111111')->after('marquee_bg_color');
            $table->string('marquee_accent_color', 7)->nullable()->after('marquee_text_color');
            $table->boolean('marquee_border')->default(true)->after('marquee_accent_color');
            $table->boolean('marquee_uppercase')->default(true)->after('marquee_border');
            $table->string('marquee_speed', 10)->default('normal')->after('marquee_uppercase');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hero_dots_position', 'hero_shade',
                'marquee_enabled', 'marquee_items', 'marquee_separator', 'marquee_bg_color', 'marquee_text_color',
                'marquee_accent_color', 'marquee_border', 'marquee_uppercase', 'marquee_speed',
            ]);
        });
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['heading_font', 'body_font', 'heading_weight', 'button_shape']);
        });
    }
};
