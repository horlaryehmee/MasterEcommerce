<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hero height on phones as a share of the screen height (40–100%), set with a
 * slider in Website Content → Hero. Replaces the three fixed shapes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('hero_mobile_vh')->default(75)->after('hero_mobile_height');
        });

        // Keep roughly the look each site had: portrait ≈ 70%, square ≈ 55%, landscape ≈ 40%.
        DB::table('homepage_settings')->update(['hero_mobile_vh' => DB::raw("CASE hero_mobile_height WHEN 'square' THEN 55 WHEN 'landscape' THEN 40 ELSE 70 END")]);
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn('hero_mobile_vh');
        });
    }
};
