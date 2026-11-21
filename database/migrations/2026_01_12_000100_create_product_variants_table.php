<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Variable products: up to three options per product (e.g. Size, Colour) and
 * one variant per combination, each with its own price, availability, SKU and
 * image. Order items keep a snapshot of the variant that was bought.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // [{name: "Size", values: [{label: "M", color: null}, ...]}, ...]
            $table->json('options')->nullable()->after('compare_at_price');
            $table->boolean('has_variants')->default(false)->after('options');
            // Highest variant price, for "From ₦X" (price holds the lowest).
            $table->decimal('price_max', 12, 2)->nullable()->after('has_variants');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Values for the product's first, second and third option.
            $table->string('option1', 60)->nullable();
            $table->string('option2', 60)->nullable();
            $table->string('option3', 60)->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('sku', 64)->nullable();
            $table->string('availability', 20)->default('in_stock');
            $table->foreignId('product_image_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            // Snapshot, e.g. "M / Red", so later edits don't change past orders.
            $table->string('variant_label', 200)->nullable()->after('product_name');
            $table->string('sku', 64)->nullable()->after('variant_label');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn(['variant_label', 'sku']);
        });
        Schema::dropIfExists('product_variants');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['options', 'has_variants', 'price_max']);
        });
    }
};
