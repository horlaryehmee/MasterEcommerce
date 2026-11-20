<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Redirect;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Product;
use App\Models\SocialLink;
use App\Services\ImageStorage;
use App\Services\Shipping;
use App\Support\Media;
use App\Support\Money;
use App\Support\Tracking;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class SettingsController extends Controller
{
    public const SECTIONS = ['business', 'branding', 'currency', 'whatsapp', 'shop', 'shipping', 'social', 'seo', 'tracking', 'code', 'redirects'];

    /** Separators offered for page titles ("Product | Store"). */
    public const TITLE_SEPARATORS = ['|', '-', '–', '—', '·', '•', ':'];

    protected const VERIFY_FIELDS = ['verify_google', 'verify_bing', 'verify_yandex', 'verify_pinterest', 'verify_facebook'];

    /** Image fields per section: request field => [column, profile, format]. */
    protected const IMAGES = [
        'business' => [
            'logo' => ['logo_path', 'branding', 'webp'],
            'favicon' => ['favicon_path', 'branding', 'png'],
        ],
        'seo' => [
            'og_image' => ['og_image_path', 'banners', 'jpg'],
        ],
    ];

    public function __construct(protected ImageStorage $images) {}

    public function edit(Request $request): Response
    {
        $s = BusinessSetting::current();
        $socials = SocialLink::query()->pluck('url', 'platform');

        return Inertia::render('Admin/Settings/Edit', [
            'tab' => in_array($request->query('tab'), self::SECTIONS, true) ? $request->query('tab') : 'business',
            // Phones show a settings menu until a section is picked.
            'tabChosen' => in_array($request->query('tab'), self::SECTIONS, true),
            'settings' => [
                ...collect($s->getAttributes())->except(['id', 'created_at', 'updated_at'])->all(),
                'show_floating_whatsapp' => $s->show_floating_whatsapp,
                'decimal_places' => $s->decimal_places,
                'shop_enabled' => $s->shop_enabled,
                'pay_whatsapp_enabled' => $s->pay_whatsapp_enabled,
                'pay_on_delivery_enabled' => $s->pay_on_delivery_enabled,
                'pay_bank_transfer_enabled' => $s->pay_bank_transfer_enabled,
                'email_owner_new_order' => $s->email_owner_new_order,
                'email_customer_new_order' => $s->email_customer_new_order,
                'email_customer_updates' => $s->email_customer_updates,
                'search_engines_visible' => $s->search_engines_visible,
                'track_admins' => $s->track_admins,
                'stats_enabled' => $s->stats_enabled,
                'custom_meta' => $s->custom_meta ?? [],
                'ship_to_countries' => $s->ship_to_countries ?? [],
                'ship_except_countries' => $s->ship_except_countries ?? [],
                'logo_url' => Media::url($s->logo_path),
                'favicon_url' => Media::url($s->favicon_path),
                'og_image_url' => Media::url($s->og_image_path, 'sm'),
            ],
            'socials' => collect(SocialLink::PLATFORMS)->map(fn ($p, $key) => [
                'platform' => $key,
                'label' => $p['label'],
                'url' => $socials[$key] ?? '',
            ])->values(),
            'countries' => collect(Shipping::countries())->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values(),
            'shipTo' => BusinessSetting::SHIP_TO,
            'titleSeparators' => self::TITLE_SEPARATORS,
            'timezones' => fn () => timezone_identifiers_list(),
            'redirects' => fn () => Redirect::query()->orderByDesc('updated_at')->limit(500)->get()->map(fn (Redirect $r) => [
                'id' => $r->id,
                'from_path' => $r->from_path,
                'to_path' => $r->to_path,
                'status_code' => $r->status_code,
                'is_automatic' => $r->is_automatic,
                'hits' => $r->hits,
                'last_hit' => $r->last_hit_at?->diffForHumans(),
            ]),
            'appIsProduction' => app()->isProduction(),
            'mail' => $this->mailStatus(),
            'shippingZones' => fn () => ShippingZone::query()->ordered()->with(['locations', 'methods'])->get()->map(fn (ShippingZone $z) => [
                'id' => $z->id,
                'name' => $z->is_fallback ? ShippingZone::FALLBACK_NAME : $z->name,
                'is_fallback' => $z->is_fallback,
                'regions' => $z->is_fallback ? 'Used when an address doesn’t match any zone above' : Shipping::describeRegions($z),
                'methods' => $z->methods->map(fn (ShippingMethod $m) => ['title' => $m->title, 'summary' => $m->summary(), 'enabled' => $m->is_enabled])->values(),
            ]),
            'hasWhatsapp' => WhatsApp::normalize($s->whatsapp_number) !== null,
            'cardStyles' => collect(BusinessSetting::CARD_STYLES)->map(fn ($c, $key) => ['value' => $key, 'label' => $c[0], 'description' => $c[1]])->values(),
            // A real product for the card previews (a discounted one shows the most).
            'sampleProduct' => fn () => Product::query()->visible()->with(['primaryImage', 'category:id,name'])
                ->orderByRaw('compare_at_price IS NULL')->orderByDesc('is_featured')->first()?->toCard(),
            'currencyPresets' => BusinessSetting::CURRENCY_PRESETS,
            'pricePreview' => Money::format(1234567.5, $s),
            'whatsappPreview' => WhatsApp::generalLink($s),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        // Redirects have their own controller.
        abort_unless(in_array($section, self::SECTIONS, true) && $section !== 'redirects', 404);
        $settings = BusinessSetting::current();

        if ($section === 'social') {
            return $this->updateSocial($request);
        }

        if ($section === 'shop') {
            return $this->updateShop($request, $settings);
        }

        if ($section === 'shipping') {
            return $this->updateShippingLocations($request, $settings);
        }

        if ($section === 'seo') {
            $request->merge([
                'search_engines_visible' => $request->boolean('search_engines_visible'),
                // Accept a pasted <meta … content="…"> tag and keep just the code.
                ...collect(self::VERIFY_FIELDS)->mapWithKeys(fn ($f) => [$f => self::verificationCode($request->input($f))])->all(),
                'twitter_handle' => $request->filled('twitter_handle') ? ltrim(trim((string) $request->input('twitter_handle')), '@') : null,
                'custom_meta' => collect((array) $request->input('custom_meta', []))
                    ->filter(fn ($m) => filled($m['key'] ?? null) || filled($m['content'] ?? null))->values()->all(),
            ]);
        }

        if ($section === 'tracking') {
            $request->merge([
                'track_admins' => $request->boolean('track_admins'),
                // Only when sent, so a partial save never switches statistics off.
                ...($request->has('stats_enabled') ? ['stats_enabled' => $request->boolean('stats_enabled')] : []),
                ...collect(['ga4_id', 'gtm_id', 'google_ads_id', 'tiktok_pixel_id'])->mapWithKeys(fn ($f) => [$f => $request->filled($f) ? strtoupper(trim((string) $request->input($f))) : null])->all(),
            ]);
        }

        if ($section === 'whatsapp') {
            $request->merge([
                'show_floating_whatsapp' => $request->boolean('show_floating_whatsapp'),
                'whatsapp_number' => $request->filled('whatsapp_number') ? preg_replace('/\D+/', '', (string) $request->input('whatsapp_number')) : null,
            ]);
        }

        $data = $request->validate($this->rules($section), $this->messages(), [
            'whatsapp_number' => 'WhatsApp number',
            'seo_title' => 'website title',
            'seo_description' => 'meta description',
            'ga4_id' => 'Google Analytics ID',
            'gtm_id' => 'Tag Manager ID',
            'google_ads_id' => 'Google Ads ID',
            'google_ads_purchase_label' => 'conversion label',
            'meta_pixel_id' => 'Meta Pixel ID',
            'tiktok_pixel_id' => 'TikTok Pixel ID',
        ]);

        $oldFiles = [];
        foreach (self::IMAGES[$section] ?? [] as $field => [$column, $profile, $format]) {
            unset($data[$field], $data['remove_'.$field]);
            if ($request->hasFile($field)) {
                try {
                    $data[$column] = $this->images->store($request->file($field), $profile, $format);
                } catch (RuntimeException $e) {
                    throw ValidationException::withMessages([$field => $e->getMessage()]);
                }
                $oldFiles[] = $settings->{$column};
            } elseif ($request->boolean('remove_'.$field)) {
                $data[$column] = null;
                $oldFiles[] = $settings->{$column};
            }
        }

        if ($section === 'branding') {
            foreach (['primary_color', 'secondary_color', 'accent_color'] as $key) {
                $data[$key] = strtoupper($data[$key]);
            }
        }

        if ($section === 'currency') {
            // "No separator" arrives as null after Laravel's empty-string conversion.
            $data['thousands_separator'] = $data['thousands_separator'] ?? '';
            $data['currency_symbol'] = $data['currency_symbol'] ?? '';
            $data['currency_code'] = strtoupper($data['currency_code']);
        }

        $settings->update($data);
        array_map(fn ($p) => $this->images->delete($p), array_filter($oldFiles));

        return redirect()->route('admin.settings.edit', ['tab' => $section])->with('success', $this->savedMessage('Settings saved.'));
    }

    /** Online ordering switch, payment options and order emails. */
    protected function updateShop(Request $request, BusinessSetting $settings): RedirectResponse
    {
        $flags = ['shop_enabled', 'pay_whatsapp_enabled', 'pay_on_delivery_enabled', 'pay_bank_transfer_enabled', 'email_owner_new_order', 'email_customer_new_order', 'email_customer_updates'];
        $request->merge(collect($flags)->mapWithKeys(fn ($f) => [$f => $request->boolean($f)])->all());

        $data = $request->validate([
            ...array_fill_keys($flags, ['boolean']),
            'bank_name' => ['nullable', 'string', 'max:120', 'required_if:pay_bank_transfer_enabled,true'],
            'bank_account_name' => ['nullable', 'string', 'max:120', 'required_if:pay_bank_transfer_enabled,true'],
            'bank_account_number' => ['nullable', 'string', 'max:40', 'required_if:pay_bank_transfer_enabled,true'],
            'order_notification_email' => ['nullable', 'email', 'max:160'],
        ], ['*.required_if' => 'Required when this option is switched on.']);

        $methods = $data['pay_whatsapp_enabled'] || $data['pay_on_delivery_enabled'] || $data['pay_bank_transfer_enabled'];
        if ($data['shop_enabled'] && ! $methods) {
            throw ValidationException::withMessages(['pay_on_delivery_enabled' => 'Switch on at least one payment option.']);
        }

        $settings->update($data);

        return redirect()->route('admin.settings.edit', ['tab' => 'shop'])->with('success', 'Shop settings saved.');
    }

    /** Emails the order-notification address, so the owner can check mail delivery works. */
    public function testEmail(): RedirectResponse
    {
        $s = BusinessSetting::current();
        $to = $s->orderNotificationAddress();
        $back = redirect()->route('admin.settings.edit', ['tab' => 'shop']);

        if (! $to) {
            return $back->with('error', 'Add an order email address (or a business email) first, then save.');
        }

        try {
            Mail::raw(
                "This is a test email from {$s->business_name}.

If you can read this, order emails are working: you'll get an email for every new order, and customers get their order confirmations and updates.",
                fn ($m) => $m->to($to)->subject("Test email from {$s->business_name}"),
            );
        } catch (Throwable $e) {
            Log::warning('Test email failed', ['error' => $e->getMessage()]);

            return $back->with('error', 'The email could not be sent: '.Str::limit($e->getMessage(), 200).' Check the MAIL_ settings in your .env file.');
        }

        return $back->with('success', $this->mailStatus()['delivers']
            ? "Test email sent to {$to}. Check your inbox (and spam folder)."
            : "Test email written to the log file (storage/logs) — real delivery isn't set up yet.");
    }

    /** Whether emails really leave the server (an SMTP or API mailer) or only go to the log. */
    protected function mailStatus(): array
    {
        $mailer = (string) config('mail.default');

        return [
            'mailer' => $mailer,
            'delivers' => ! in_array($mailer, ['log', 'array'], true),
            'from' => config('mail.from.address'),
        ];
    }

    /** Selling locations and the store's default country (zones have their own pages). */
    protected function updateShippingLocations(Request $request, BusinessSetting $settings): RedirectResponse
    {
        $codes = array_keys(Shipping::countries());
        $data = $request->validate([
            'default_country' => ['required', Rule::in($codes)],
            'ship_to' => ['required', Rule::in(array_keys(BusinessSetting::SHIP_TO))],
            'ship_to_countries' => ['array', 'required_if:ship_to,specific'],
            'ship_to_countries.*' => [Rule::in($codes)],
            'ship_except_countries' => ['array'],
            'ship_except_countries.*' => [Rule::in($codes)],
        ], ['ship_to_countries.required_if' => 'Choose at least one country.']);

        $settings->update([
            'default_country' => $data['default_country'],
            'ship_to' => $data['ship_to'],
            'ship_to_countries' => array_values(array_unique($data['ship_to_countries'] ?? [])),
            'ship_except_countries' => array_values(array_unique($data['ship_except_countries'] ?? [])),
        ]);

        return redirect()->route('admin.settings.edit', ['tab' => 'shipping'])->with('success', 'Shipping locations saved.');
    }

    protected function updateSocial(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (SocialLink::PLATFORMS as $key => $platform) {
            $rules["links.{$key}"] = ['nullable', 'string', 'max:255', 'url:https,http', function ($attr, $value, $fail) use ($platform) {
                $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                $host = preg_replace('/^(www\.|m\.)/', '', $host);
                $valid = collect($platform['hosts'])->contains(fn ($h) => $host === $h || str_ends_with($host, '.'.$h));
                if (! $valid) {
                    $fail("This doesn't look like a {$platform['label']} link (expected ".$platform['hosts'][0].').');
                }
            }];
        }

        $data = $request->validate($rules, ['links.*.url' => 'Enter a full link starting with https://']);

        DB::transaction(function () use ($data) {
            $order = 0;
            foreach (array_keys(SocialLink::PLATFORMS) as $key) {
                $url = trim((string) ($data['links'][$key] ?? ''));
                if ($url === '') {
                    SocialLink::query()->where('platform', $key)->delete();
                } else {
                    SocialLink::query()->updateOrCreate(['platform' => $key], ['url' => $url, 'sort_order' => $order]);
                }
                $order++;
            }
        });

        return redirect()->route('admin.settings.edit', ['tab' => 'social'])->with('success', 'Social links saved.');
    }

    protected function rules(string $section): array
    {
        $image = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('catalog.max_upload_kb'), 'dimensions:max_width=8000,max_height=8000'];
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return match ($section) {
            'business' => [
                'business_name' => ['required', 'string', 'max:120'],
                'business_description' => ['nullable', 'string', 'max:1000'],
                'email' => ['nullable', 'email', 'max:160'],
                'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s.]{5,40}$/'],
                'address' => ['nullable', 'string', 'max:500'],
                'opening_hours' => ['nullable', 'string', 'max:500'],
                'logo' => $image,
                'favicon' => $image,
                'remove_logo' => ['nullable', 'boolean'],
                'remove_favicon' => ['nullable', 'boolean'],
            ],
            'branding' => [
                'primary_color' => $hex,
                'secondary_color' => $hex,
                'accent_color' => $hex,
                'product_card_style' => ['sometimes', 'required', Rule::in(array_keys(BusinessSetting::CARD_STYLES))],
            ],
            'currency' => [
                'currency_code' => ['required', 'string', 'size:3', 'alpha'],
                'currency_symbol' => ['nullable', 'string', 'max:8'],
                'currency_position' => ['required', Rule::in(['before', 'after'])],
                'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
                'thousands_separator' => ['nullable', 'string', 'max:1', Rule::in([',', '.', ' ', "'", ''])],
                'decimal_separator' => ['required', 'string', Rule::in(['.', ','])],
            ],
            'whatsapp' => [
                'whatsapp_number' => ['nullable', 'string', function ($attr, $value, $fail) {
                    if ($value && ! WhatsApp::isValid($value)) {
                        $fail('Enter the number in international format with the country code, e.g. 2348012345678 (no leading 0).');
                    }
                }],
                'whatsapp_general_message' => ['nullable', 'string', 'max:1000'],
                'whatsapp_product_message' => ['nullable', 'string', 'max:1000'],
                'show_floating_whatsapp' => ['boolean'],
            ],
            'seo' => [
                'seo_title' => ['nullable', 'string', 'max:160'],
                'seo_description' => ['nullable', 'string', 'max:320'],
                'og_image' => $image,
                'remove_og_image' => ['nullable', 'boolean'],
                'search_engines_visible' => ['boolean'],
                'title_separator' => ['required', Rule::in(self::TITLE_SEPARATORS)],
                'twitter_handle' => ['nullable', 'regex:/^[A-Za-z0-9_]{1,15}$/'],
                'robots_extra' => ['nullable', 'string', 'max:2000'],
                ...array_fill_keys(self::VERIFY_FIELDS, ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-=+\/.:]+$/']),
                'custom_meta' => ['array', 'max:30'],
                'custom_meta.*.attribute' => ['required', Rule::in(['name', 'property', 'http-equiv'])],
                'custom_meta.*.key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9:._\-]+$/'],
                'custom_meta.*.content' => ['nullable', 'string', 'max:500'],
            ],
            'tracking' => [
                ...collect(Tracking::FORMATS)->map(fn ($pattern) => ['nullable', 'string', 'regex:'.$pattern])->all(),
                'track_admins' => ['boolean'],
                'stats_enabled' => ['boolean'],
                'timezone' => ['sometimes', 'required', Rule::in(timezone_identifiers_list())],
            ],
            'code' => [
                'head_code' => ['nullable', 'string', 'max:50000'],
                'body_code' => ['nullable', 'string', 'max:50000'],
                'footer_code' => ['nullable', 'string', 'max:50000'],
            ],
        };
    }

    /** '<meta name="google-site-verification" content="abc" />' → 'abc'. */
    protected static function verificationCode(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
            return trim($m[1]);
        }

        return trim($value, " \t\n\r\0\x0B\"'");
    }

    protected function messages(): array
    {
        return [
            'ga4_id.regex' => 'Use your GA4 Measurement ID, e.g. G-ABC123XYZ.',
            'gtm_id.regex' => 'Use your container ID, e.g. GTM-ABC1234.',
            'google_ads_id.regex' => 'Use your Google Ads tag ID, e.g. AW-123456789.',
            'google_ads_purchase_label.regex' => 'Use the label part of the conversion (after the slash), e.g. AbC-D_efG123.',
            'meta_pixel_id.regex' => 'The Meta Pixel ID is a number, e.g. 123456789012345.',
            'tiktok_pixel_id.regex' => 'Use your TikTok Pixel ID, e.g. C4ABCDEFGH1234567890.',
            'twitter_handle.regex' => 'Up to 15 letters, numbers or underscores, e.g. @mystore.',
            'verify_google.regex' => 'Paste the code (or the whole meta tag) from Google Search Console.',
            'custom_meta.*.key.regex' => 'Letters, numbers and : . _ - only.',
            'custom_meta.*.key.required' => 'Enter the tag name.',
            'phone.regex' => 'The phone number may only contain digits, spaces, +, - and brackets.',
            '*.regex' => 'Choose a valid colour.',
            'decimal_separator.in' => 'The decimal separator must be a dot or a comma.',
        ];
    }
}
