<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional online ordering: cart, guest checkout and orders. Switched on per
 * installation in Admin → Website Settings → Shop & checkout (off by default,
 * so existing catalogue-only sites are unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('shop_enabled')->default(false)->after('show_floating_whatsapp');
            // Payment options offered at checkout.
            $table->boolean('pay_whatsapp_enabled')->default(true)->after('shop_enabled');
            $table->boolean('pay_on_delivery_enabled')->default(true)->after('pay_whatsapp_enabled');
            $table->boolean('pay_bank_transfer_enabled')->default(false)->after('pay_on_delivery_enabled');
            $table->string('bank_name', 120)->nullable()->after('pay_bank_transfer_enabled');
            $table->string('bank_account_name', 120)->nullable()->after('bank_name');
            $table->string('bank_account_number', 40)->nullable()->after('bank_account_name');
            // Delivery options (fees per zone live in delivery_zones).
            $table->boolean('pickup_enabled')->default(false)->after('bank_account_number');
            $table->text('pickup_address')->nullable()->after('pickup_enabled');
            $table->decimal('free_delivery_threshold', 12, 2)->nullable()->after('pickup_address');
            $table->string('order_notification_email', 160)->nullable()->after('free_delivery_threshold');
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('fee', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            // Unguessable key for the customer's order page (guest checkout has no login).
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->string('payment_method', 20);
            $table->string('payment_status', 20)->default('unpaid');

            $table->string('customer_name', 120);
            $table->string('customer_phone', 40);
            $table->string('customer_email', 160)->nullable();

            $table->string('fulfilment', 10)->default('delivery');
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot, so renaming or deleting a zone doesn't change past orders.
            $table->string('delivery_zone_name', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency_code', 3);
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of the product at the time of ordering.
            $table->string('product_name', 160);
            $table->string('product_slug', 180)->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('delivery_zones');
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'shop_enabled', 'pay_whatsapp_enabled', 'pay_on_delivery_enabled', 'pay_bank_transfer_enabled',
                'bank_name', 'bank_account_name', 'bank_account_number',
                'pickup_enabled', 'pickup_address', 'free_delivery_threshold', 'order_notification_email',
            ]);
        });
    }
};
