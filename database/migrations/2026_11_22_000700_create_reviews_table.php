<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Customer reviews of products, with moderation and spam scoring. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Set when the reviewer's email matches an order for this product.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60);
            $table->string('email', 160)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 100)->nullable();
            $table->text('body');
            // pending → approved | rejected; spam = caught by the filters (kept for review, never shown)
            $table->string('status', 10)->default('pending');
            $table->boolean('is_verified')->default(false);
            // Why the filters held it, for the admin ("contains links", "duplicate"…).
            $table->string('flags', 255)->nullable();
            // Hashed (with APP_KEY): enough for rate limits and duplicates, no raw IPs stored.
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('body_hash', 64)->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'created_at']);
            $table->index(['status', 'rating', 'approved_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            // Approved reviews only; kept up to date when reviews change.
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('reviews_enabled')->default(true);
            $table->boolean('reviews_home_enabled')->default(true);
            // manual: every review waits for approval; verified: buyers' clean reviews go live at once
            $table->string('reviews_auto_publish', 10)->default('manual');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['reviews_count', 'rating_avg']));
        Schema::table('business_settings', fn (Blueprint $table) => $table->dropColumn(['reviews_enabled', 'reviews_home_enabled', 'reviews_auto_publish']));
    }
};
