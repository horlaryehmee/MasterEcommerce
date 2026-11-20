<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-click updates (Admin → Updates): the GitHub repository releases come
 * from and an optional access token (stored encrypted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('update_repository')->nullable();
            $table->text('update_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['update_repository', 'update_token']);
        });
    }
};
