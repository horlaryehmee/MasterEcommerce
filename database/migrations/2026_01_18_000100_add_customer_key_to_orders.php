<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customers are guests (no accounts), so orders are grouped into customers by
 * phone number (or email when there is no usable phone). The key is stored on
 * each order so the Customers list can group and filter quickly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_key', 40)->nullable()->after('customer_email');
            $table->index(['customer_key', 'created_at']);
        });

        DB::table('orders')->select('id', 'customer_phone', 'customer_email')->orderBy('id')->chunk(500, function ($orders) {
            foreach ($orders as $o) {
                DB::table('orders')->where('id', $o->id)->update(['customer_key' => Order::customerKey($o->customer_phone, $o->customer_email)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_key', 'created_at']);
            $table->dropColumn('customer_key');
        });
    }
};
