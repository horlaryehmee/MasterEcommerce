<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices the admin switched off from the device list. Kept (not deleted) so
 * the device doesn't silently turn itself back on; only "Turn on" on that
 * device itself re-enables it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', fn (Blueprint $table) => $table->dropColumn('disabled_at'));
    }
};
