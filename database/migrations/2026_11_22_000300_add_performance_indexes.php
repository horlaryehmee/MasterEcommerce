<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for large shops (tested with 100,000 orders): the order list's
 * newest-first and by-total sorting, the payment filters, and a covering index
 * so the customer list can total each customer's orders from the index alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'orders_created_at_id_index');
            $table->index('total', 'orders_total_index');
            $table->index(['payment_status', 'status', 'created_at'], 'orders_payment_status_index');
            // Covers COUNT/SUM/MIN/MAX per customer (customer list and segments).
            $table->index(['customer_key', 'created_at', 'status', 'total'], 'orders_customer_totals_index');
        });
        // Replaced by the covering index above (same leading columns).
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_key_created_at_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['updated_at', 'id'], 'products_updated_at_id_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'payments_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_key', 'created_at'], 'orders_customer_key_created_at_index');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_created_at_id_index');
            $table->dropIndex('orders_total_index');
            $table->dropIndex('orders_payment_status_index');
            $table->dropIndex('orders_customer_totals_index');
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex('products_updated_at_id_index'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payments_status_created_at_index'));
    }
};
