<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic order emails (Admin → Settings → Shop & checkout): switches for
 * the new-order emails and the customer's order-update emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('email_owner_new_order')->default(true)->after('order_notification_email');
            $table->boolean('email_customer_new_order')->default(true)->after('email_owner_new_order');
            $table->boolean('email_customer_updates')->default(true)->after('email_customer_new_order');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['email_owner_new_order', 'email_customer_new_order', 'email_customer_updates']);
        });
    }
};
