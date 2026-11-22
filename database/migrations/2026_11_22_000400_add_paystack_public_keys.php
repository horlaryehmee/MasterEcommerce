<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paystack public keys for each mode (public by design, so stored as plain text). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('paystack_test_public_key', 120)->nullable();
            $table->string('paystack_live_public_key', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', fn (Blueprint $table) => $table->dropColumn(['paystack_test_public_key', 'paystack_live_public_key']));
    }
};
