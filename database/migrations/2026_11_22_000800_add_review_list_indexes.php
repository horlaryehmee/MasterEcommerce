<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes so review lists stay instant with tens of thousands of reviews:
 *  - product page sorted / filtered by stars (and the star breakdown + rating sync),
 *  - the admin list (status tab, newest first) and the "All" tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // "Highest rated" (read backwards) + star filter + breakdown + rating sync.
            $table->index(['product_id', 'status', 'rating', 'created_at'], 'reviews_product_rating_index');
            // "Lowest rated": lowest stars first, newest first within each star.
            $table->rawIndex('product_id, status, rating, created_at DESC, id DESC', 'reviews_product_lowest_index');
            $table->index(['status', 'created_at'], 'reviews_status_created_index');
            $table->index('created_at', 'reviews_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_product_rating_index');
            $table->dropIndex('reviews_product_lowest_index');
            $table->dropIndex('reviews_status_created_index');
            $table->dropIndex('reviews_created_index');
        });
    }
};
