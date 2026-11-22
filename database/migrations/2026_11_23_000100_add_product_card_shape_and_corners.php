<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product card options on top of the card design (Admin → Website Settings → Branding):
 * image shape (auto | square | portrait) and corners (auto | rounded | sharp).
 * "auto" keeps whatever the chosen card design does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('product_card_shape', 10)->default('auto');
            $table->string('product_card_corners', 10)->default('auto');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['product_card_shape', 'product_card_corners']);
        });
    }
};
