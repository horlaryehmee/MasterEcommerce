<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coming soon / maintenance page (Website Settings → Coming soon & maintenance):
 * visitors see one branded page, optionally with a countdown that can open the
 * shop by itself; admins and holders of the secret preview link see the shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('launch_enabled')->default(false);
            $table->string('launch_mode', 20)->default('coming_soon'); // coming_soon | maintenance
            $table->string('launch_heading', 120)->nullable();
            $table->text('launch_message')->nullable();
            $table->timestamp('launch_at')->nullable();
            $table->boolean('launch_countdown')->default(true);
            $table->boolean('launch_auto_open')->default(true);
            $table->boolean('launch_show_contact')->default(true);
            $table->string('launch_image_path')->nullable();
            // Secret preview link (?preview=…): lets others see the shop before it opens.
            $table->string('launch_token', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['launch_enabled', 'launch_mode', 'launch_heading', 'launch_message', 'launch_at', 'launch_countdown', 'launch_auto_open', 'launch_show_contact', 'launch_image_path', 'launch_token']);
        });
    }
};
