<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phones, text on the hero image: at the bottom (default) or in the middle. Always centred left to right. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->string('hero_mobile_valign', 10)->default('bottom')->after('hero_mobile_vh');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn('hero_mobile_valign');
        });
    }
};
