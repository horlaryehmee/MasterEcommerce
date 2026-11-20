<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Built-in, first-party visitor statistics (Admin → Analytics): visitors,
 * sessions (30-minute inactivity, as in Google Analytics), page views and
 * events. No IP addresses are stored; location is resolved at collection time.
 *
 * `local_date` / `local_hour` are in the store's timezone, computed when the
 * hit is recorded, so daily figures are right on any database and across DST.
 */
return new class extends Migration
{
    protected const CURRENCY_TIMEZONE = [
        'NGN' => 'Africa/Lagos', 'GHS' => 'Africa/Accra', 'KES' => 'Africa/Nairobi', 'ZAR' => 'Africa/Johannesburg',
        'GBP' => 'Europe/London', 'EUR' => 'Europe/Berlin', 'USD' => 'America/New_York',
    ];

    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('stats_enabled')->default(true)->after('track_admins');
            $table->string('timezone', 64)->default('Africa/Lagos')->after('stats_enabled');
        });

        $currency = DB::table('business_settings')->value('currency_code');
        if ($currency) {
            DB::table('business_settings')->update(['timezone' => self::CURRENCY_TIMEZONE[$currency] ?? 'UTC']);
        }

        Schema::create('stats_visitors', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 36)->unique(); // random ID kept in the visitor's browser
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('region', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('device', 20)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('os', 40)->nullable();
            // How they first found the site.
            $table->string('first_channel', 30)->nullable();
            $table->string('first_source', 120)->nullable();
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('pageviews_count')->default(0);
        });

        Schema::create('stats_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('stats_visitors')->cascadeOnDelete();
            $table->char('sid', 36)->unique();
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at')->index();
            $table->date('local_date')->index();
            $table->boolean('is_new_visitor')->default(false);
            $table->unsignedSmallInteger('pageviews')->default(0);
            $table->unsignedInteger('engaged_seconds')->default(0);
            $table->boolean('has_conversion')->default(false);
            $table->string('entry_path', 255)->nullable();
            $table->string('exit_path', 255)->nullable();
            $table->string('referrer_host', 120)->nullable();
            $table->string('referrer_url', 500)->nullable();
            $table->string('channel', 30)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('medium', 80)->nullable();
            $table->string('campaign', 120)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('device', 20)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('os', 40)->nullable();
            $table->unsignedSmallInteger('screen_width')->nullable();

            $table->index(['local_date', 'channel']);
            $table->index(['local_date', 'country_code']);
        });

        Schema::create('stats_pageviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('stats_sessions')->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained('stats_visitors')->cascadeOnDelete();
            $table->char('pid', 36)->unique(); // lets engagement pings update this view
            $table->string('path', 255);
            $table->string('title', 200)->nullable();
            $table->timestamp('created_at')->index();
            $table->date('local_date');
            $table->unsignedTinyInteger('local_hour');
            $table->unsignedInteger('engaged_seconds')->default(0);

            $table->index(['local_date', 'path']);
        });

        Schema::create('stats_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('stats_sessions')->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained('stats_visitors')->cascadeOnDelete();
            // view_item, add_to_cart, begin_checkout, purchase, search, outbound_click
            $table->string('name', 30);
            $table->string('label', 255)->nullable();
            $table->decimal('value', 14, 2)->nullable();
            $table->timestamp('created_at');
            $table->date('local_date');

            $table->index(['local_date', 'name']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // Which visitor placed the order (for the visitor timeline).
            $table->foreignId('stats_visitor_id')->nullable()->after('id')->constrained('stats_visitors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stats_visitor_id');
        });
        Schema::dropIfExists('stats_events');
        Schema::dropIfExists('stats_pageviews');
        Schema::dropIfExists('stats_sessions');
        Schema::dropIfExists('stats_visitors');
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['stats_enabled', 'timezone']);
        });
    }
};
