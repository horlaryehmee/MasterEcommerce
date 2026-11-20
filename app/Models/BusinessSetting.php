<?php

namespace App\Models;

use App\Support\StorefrontCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Single-row settings for this installation (business profile, branding,
 * currency, WhatsApp, footer and SEO defaults).
 */
class BusinessSetting extends Model
{
    public const DEFAULT_GENERAL_MESSAGE = 'Hello {business_name}, I would like to make an enquiry.';

    public const DEFAULT_PRODUCT_MESSAGE = "Hello {business_name}, I am interested in the {product_name} ({product_price}) listed on your website. Please provide more information.\n\n{product_url}";

    /** Common currencies offered as presets in the admin. Any ISO code is accepted. */
    public const CURRENCY_PRESETS = [
        'NGN' => ['symbol' => '₦', 'position' => 'before', 'decimals' => 0, 'thousands' => ',', 'decimal' => '.'],
        'USD' => ['symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'GBP' => ['symbol' => '£', 'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'EUR' => ['symbol' => '€', 'position' => 'after', 'decimals' => 2, 'thousands' => '.', 'decimal' => ','],
        'GHS' => ['symbol' => 'GH₵', 'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'KES' => ['symbol' => 'KSh', 'position' => 'before', 'decimals' => 0, 'thousands' => ',', 'decimal' => '.'],
        'ZAR' => ['symbol' => 'R', 'position' => 'before', 'decimals' => 2, 'thousands' => ' ', 'decimal' => '.'],
    ];

    /** Product card designs: key => [name, description]. */
    public const CARD_STYLES = [
        'modern' => ['Modern', 'Large rounded image. Add to cart sits on the image (a bag button on phones, a bar on hover).'],
        'classic' => ['Classic', 'Image, details, then a full-width Add to cart button under each product.'],
        'boxed' => ['Boxed', 'Each product in a bordered card with the price and a bag button side by side.'],
    ];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = [
        'business_name' => 'My Business',
        'primary_color' => '#111827',
        'secondary_color' => '#F4F1EC',
        'accent_color' => '#C2410C',
        'product_card_style' => 'modern',
        'currency_code' => 'NGN',
        'currency_symbol' => '₦',
        'currency_position' => 'before',
        'decimal_places' => 0,
        'thousands_separator' => ',',
        'decimal_separator' => '.',
        'show_floating_whatsapp' => true,
        'shop_enabled' => false,
        'pay_whatsapp_enabled' => true,
        'pay_on_delivery_enabled' => true,
        'pay_bank_transfer_enabled' => false,
        'email_owner_new_order' => true,
        'email_customer_new_order' => true,
        'email_customer_updates' => true,
        'search_engines_visible' => true,
        'title_separator' => '|',
        'track_admins' => false,
        'stats_enabled' => true,
        'timezone' => 'Africa/Lagos',
        'default_country' => 'NG',
        'ship_to' => 'specific',
        'ship_to_countries' => '["NG"]',
    ];

    /** Selling locations (WooCommerce "Selling location(s)"). */
    public const SHIP_TO = [
        'specific' => 'Specific countries only',
        'all' => 'All countries',
        'all_except' => 'All countries, except for…',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'show_floating_whatsapp' => 'boolean',
            'shop_enabled' => 'boolean',
            'pay_whatsapp_enabled' => 'boolean',
            'pay_on_delivery_enabled' => 'boolean',
            'pay_bank_transfer_enabled' => 'boolean',
            'email_owner_new_order' => 'boolean',
            'email_customer_new_order' => 'boolean',
            'email_customer_updates' => 'boolean',
            'search_engines_visible' => 'boolean',
            'track_admins' => 'boolean',
            'stats_enabled' => 'boolean',
            'custom_meta' => 'array',
            'ship_to_countries' => 'array',
            'ship_except_countries' => 'array',
            'update_token' => 'encrypted',
        ];
    }

    /** Never sent to the browser. */
    protected $hidden = ['update_token'];

    /**
     * Payment methods customers can choose at checkout (key => label). WhatsApp
     * needs a valid number; bank transfer needs an account number. Never empty:
     * falls back to pay on delivery.
     */
    public function paymentMethods(): array
    {
        $methods = array_filter([
            'whatsapp' => $this->pay_whatsapp_enabled && \App\Support\WhatsApp::normalize($this->whatsapp_number),
            'delivery' => $this->pay_on_delivery_enabled,
            'bank_transfer' => $this->pay_bank_transfer_enabled && filled($this->bank_account_number),
        ]);

        $keys = array_keys($methods) ?: ['delivery'];

        return array_intersect_key(Order::PAYMENT_METHODS, array_flip($keys));
    }

    /** Where new-order emails go: the order email, or else the business email. */
    public function orderNotificationAddress(): ?string
    {
        return ($this->order_notification_email ?: $this->email) ?: null;
    }

    /** Currency format for client-side totals; mirrors App\Support\Money. */
    public function moneyFormat(): array
    {
        return [
            'code' => $this->currency_code,
            'symbol' => (string) $this->currency_symbol,
            'position' => $this->currency_position,
            'decimals' => (int) $this->decimal_places,
            'thousands' => (string) $this->thousands_separator,
            'decimal' => (string) $this->decimal_separator,
        ];
    }

    protected static ?self $resolved = null;

    protected static function booted(): void
    {
        static::saved(function () {
            static::$resolved = null;
            Cache::forget('settings.business');
            StorefrontCache::flush();
        });
    }

    /** The settings row for this installation (created with defaults if missing). */
    public static function current(): self
    {
        return static::$resolved ??= Cache::rememberForever('settings.business', function () {
            return static::query()->first() ?? static::query()->create([
                'whatsapp_general_message' => self::DEFAULT_GENERAL_MESSAGE,
                'whatsapp_product_message' => self::DEFAULT_PRODUCT_MESSAGE,
            ]);
        });
    }

    public static function forgetResolved(): void
    {
        static::$resolved = null;
    }
}
