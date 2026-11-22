<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online card / bank payments through Paystack.
 *
 * `payments` is the audit trail: one row per payment attempt, with the amount
 * we expected (in the currency's smallest unit), what Paystack reported, and
 * when it was verified. An order is only marked paid after the server itself
 * has verified the transaction with Paystack.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('pay_paystack_enabled')->default(false);
            // Which pair of keys is used: "test" (no real money) or "live".
            $table->string('paystack_mode', 4)->default('test');
            // Secret keys, encrypted with APP_KEY (casts on the model); never sent to the browser.
            $table->text('paystack_test_secret_key')->nullable();
            $table->text('paystack_live_secret_key')->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('paystack');
            $table->string('reference', 64)->unique();
            // Expected amount, in kobo / pesewas / cents. Compared exactly with Paystack's figure.
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            // pending → success | failed | abandoned | flagged (amount/currency mismatch: never marks the order paid)
            $table->string('status', 20)->default('pending')->index();
            $table->string('mode', 4)->nullable(); // test | live
            $table->string('channel', 40)->nullable();
            $table->string('gateway_id', 40)->nullable();
            $table->unsignedBigInteger('amount_paid')->nullable();
            $table->string('message', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('paid_at'));
        Schema::dropIfExists('payments');
        Schema::table('business_settings', fn (Blueprint $table) => $table->dropColumn(['pay_paystack_enabled', 'paystack_mode', 'paystack_test_secret_key', 'paystack_live_secret_key']));
    }
};
