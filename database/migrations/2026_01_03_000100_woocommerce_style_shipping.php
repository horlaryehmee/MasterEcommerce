<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WooCommerce-style shipping: selling locations, shipping zones (countries,
 * states, postcodes) with Flat rate / Free shipping / Local pickup methods,
 * and a fallback zone for "locations not covered by your other zones".
 *
 * Replaces the simple delivery areas + pickup + free-delivery threshold, and
 * converts them: each old area becomes a Flat rate in a zone for the store's
 * country, pickup becomes Local pickup, the threshold becomes Free shipping.
 */
return new class extends Migration
{
    /** Store country guessed from the currency for existing installs. */
    protected const CURRENCY_COUNTRY = ['NGN' => 'NG', 'GHS' => 'GH', 'KES' => 'KE', 'ZAR' => 'ZA', 'USD' => 'US', 'GBP' => 'GB', 'CAD' => 'CA'];

    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('default_country', 2)->default('NG')->after('order_notification_email');
            // all | specific | all_except
            $table->string('ship_to', 12)->default('specific')->after('default_country');
            $table->json('ship_to_countries')->nullable()->after('ship_to');
            $table->json('ship_except_countries')->nullable()->after('ship_to_countries');
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // The single "Locations not covered by your other zones" zone.
            $table->boolean('is_fallback')->default(false);
            $table->timestamps();
        });

        Schema::create('shipping_zone_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // country | state | postcode
            $table->string('code', 40); // "NG", "NG:LA", "100*", "90210...99000"
            $table->index(['type', 'code']);
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // flat_rate | free_shipping | local_pickup
            $table->string('title', 120);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_country', 2)->nullable()->after('fulfilment');
            $table->string('shipping_state', 80)->nullable()->after('shipping_country');
            $table->string('postcode', 20)->nullable()->after('city');
            $table->string('shipping_method', 120)->nullable()->after('postcode');
            $table->string('shipping_method_type', 20)->nullable()->after('shipping_method');
        });

        $now = now();
        DB::table('shipping_zones')->insert(['name' => 'Locations not covered by your other zones', 'sort_order' => 9999, 'is_fallback' => true, 'created_at' => $now, 'updated_at' => $now]);

        $this->convertExistingSetup($now);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_zone_id');
            $table->renameColumn('delivery_zone_name', 'shipping_zone_name');
        });
        Schema::dropIfExists('delivery_zones');
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['pickup_enabled', 'pickup_address', 'free_delivery_threshold']);
        });
    }

    protected function convertExistingSetup($now): void
    {
        $settings = DB::table('business_settings')->first();
        if (! $settings) {
            return;
        }

        $country = self::CURRENCY_COUNTRY[$settings->currency_code] ?? 'NG';
        DB::table('business_settings')->update(['default_country' => $country, 'ship_to' => 'specific', 'ship_to_countries' => json_encode([$country])]);

        // Backfill orders placed with the old setup.
        DB::table('orders')->update(['shipping_country' => $country]);
        DB::table('orders')->where('fulfilment', 'pickup')->update(['shipping_method' => 'Local pickup', 'shipping_method_type' => 'local_pickup']);
        DB::table('orders')->where('fulfilment', 'delivery')->update(['shipping_method' => DB::raw("COALESCE(delivery_zone_name, 'Delivery')"), 'shipping_method_type' => 'flat_rate']);

        $areas = DB::table('delivery_zones')->where('is_active', true)->orderBy('sort_order')->get();
        $threshold = (float) ($settings->free_delivery_threshold ?? 0);
        if ($areas->isEmpty() && ! $settings->pickup_enabled && $threshold <= 0) {
            return;
        }

        $zoneId = DB::table('shipping_zones')->insertGetId(['name' => 'Delivery', 'sort_order' => 0, 'is_fallback' => false, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('shipping_zone_locations')->insert(['shipping_zone_id' => $zoneId, 'type' => 'country', 'code' => $country]);

        $order = 0;
        $method = function (string $type, string $title, array $config) use (&$order, $zoneId, $now) {
            return [
                'shipping_zone_id' => $zoneId, 'type' => $type, 'title' => $title, 'is_enabled' => true,
                'sort_order' => $order++, 'settings' => json_encode($config), 'created_at' => $now, 'updated_at' => $now,
            ];
        };
        $rows = [];
        if ($threshold > 0) {
            $rows[] = $method('free_shipping', 'Free delivery', ['requires' => 'min_amount', 'min_amount' => $threshold]);
        }
        foreach ($areas as $area) {
            $rows[] = $method('flat_rate', $area->name, ['cost' => (float) $area->fee, 'per_item' => 0]);
        }
        if ($settings->pickup_enabled) {
            $rows[] = $method('local_pickup', 'Local pickup', ['cost' => 0, 'address' => $settings->pickup_address]);
        }
        DB::table('shipping_methods')->insert($rows);
    }

    public function down(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('fee', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('pickup_enabled')->default(false);
            $table->text('pickup_address')->nullable();
            $table->decimal('free_delivery_threshold', 12, 2)->nullable();
            $table->dropColumn(['default_country', 'ship_to', 'ship_to_countries', 'ship_except_countries']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('shipping_zone_name', 'delivery_zone_name');
            $table->foreignId('delivery_zone_id')->nullable()->after('fulfilment')->constrained()->nullOnDelete();
            $table->dropColumn(['shipping_country', 'shipping_state', 'postcode', 'shipping_method', 'shipping_method_type']);
        });
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zone_locations');
        Schema::dropIfExists('shipping_zones');
    }
};
