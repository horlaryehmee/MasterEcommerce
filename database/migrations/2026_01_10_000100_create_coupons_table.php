<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupon codes (WooCommerce-style): percentage, fixed cart and fixed product
 * discounts, free shipping, spend limits, product/category restrictions and
 * usage limits. Orders keep a snapshot of the code and the discount given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            // Stored upper-case; customers can type it in any case.
            $table->string('code', 40)->unique();
            $table->string('description', 255)->nullable();
            $table->string('discount_type', 20)->default('percent');
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('free_shipping')->default(false);
            $table->boolean('is_active')->default(true);
            // Calendar days in the store timezone (inclusive).
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();

            $table->decimal('minimum_spend', 12, 2)->nullable();
            $table->decimal('maximum_spend', 12, 2)->nullable();
            $table->boolean('exclude_sale_items')->default(false);
            $table->json('product_ids')->nullable();
            $table->json('excluded_product_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('excluded_category_ids')->nullable();
            $table->json('allowed_emails')->nullable();

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedSmallInteger('limit_usage_to_items')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('subtotal')->constrained()->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id');
            $table->decimal('discount_total', 12, 2)->default(0)->after('coupon_code');
            $table->index(['coupon_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('discount', 12, 2)->default(0)->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
        Schema::table('orders', function (Blueprint $table) {
            // The foreign key may be using the composite index, so it goes first.
            $table->dropForeign(['coupon_id']);
            $table->dropIndex(['coupon_id', 'status']);
            $table->dropColumn(['coupon_id', 'coupon_code', 'discount_total']);
        });
        Schema::dropIfExists('coupons');
    }
};
