<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone/desktop notifications for the admin (Web Push): one row per device
 * that turned notifications on, plus this site's VAPID key pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            // The browser's keys for encrypting messages to it (RFC 8291).
            $table->string('public_key', 120);
            $table->string('auth_token', 60);
            $table->string('device', 120)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('business_settings', function (Blueprint $table) {
            // Generated automatically on first use. The private key is encrypted with APP_KEY.
            $table->text('vapid_private_key')->nullable();
            $table->string('vapid_public_key', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::table('business_settings', fn (Blueprint $table) => $table->dropColumn(['vapid_private_key', 'vapid_public_key']));
    }
};
