<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Promotional banner: a sticker ("Up to 30% off"), an end date with countdown, and a background colour. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->string('promo_badge', 40)->nullable()->after('promo_eyebrow');
            // Stored in UTC; the banner hides itself once this passes.
            $table->timestamp('promo_ends_at')->nullable()->after('promo_badge');
            // Null = the brand's soft (secondary) colour.
            $table->string('promo_bg_color', 7)->nullable()->after('promo_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn(['promo_badge', 'promo_ends_at', 'promo_bg_color']);
        });
    }
};
