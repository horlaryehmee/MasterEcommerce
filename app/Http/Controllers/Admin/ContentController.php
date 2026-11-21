<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\Product;
use App\Rules\SafeLink;
use App\Services\ImageStorage;
use App\Support\Color;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** "Website Content": homepage sections, footer text and informational pages. */
class ContentController extends Controller
{
    public const SECTIONS = ['announcement', 'hero', 'marquee', 'categories', 'featured', 'new_arrivals', 'promo', 'about', 'why', 'footer'];

    /** Image fields per section: request field => settings column. */
    protected const IMAGES = [
        'hero' => ['hero_image' => 'hero_image_path', 'hero_mobile_image' => 'hero_mobile_image_path'],
        'promo' => ['promo_image' => 'promo_image_path'],
        'about' => ['about_image' => 'about_image_path'],
    ];

    public function __construct(protected ImageStorage $images) {}

    public function edit(): Response
    {
        $home = HomepageSetting::current();

        return Inertia::render('Admin/Content/Edit', [
            'home' => [
                ...collect($home->getAttributes())->except(['id', 'created_at', 'updated_at', 'why_items'])->all(),
                ...$home->only([
                    'announcement_enabled', 'marquee_enabled', 'marquee_border', 'marquee_uppercase', 'hero_enabled', 'categories_enabled', 'featured_enabled',
                    'new_arrivals_enabled', 'promo_enabled', 'about_enabled', 'why_enabled', 'new_arrivals_count',
                    'hero_autoplay', 'hero_interval', 'hero_mobile_vh',
                ]),
                'why_items' => $home->why_items ?? [],
                'marquee_items' => $home->marquee_items ?? [],
                'hero_image_url' => Media::url($home->hero_image_path, 'sm'),
                'hero_mobile_image_url' => Media::url($home->hero_mobile_image_path, 'sm'),
                'promo_image_url' => Media::url($home->promo_image_path, 'sm'),
                // For the date-time field: the end date in the store's timezone.
                'promo_ends_local' => $home->promo_ends_at?->clone()->setTimezone(BusinessSetting::current()->timezone ?: config('app.timezone'))->format('Y-m-d\TH:i'),
                'store_timezone' => BusinessSetting::current()->timezone ?: config('app.timezone'),
                'about_image_url' => Media::url($home->about_image_path, 'sm'),
            ],
            'footer' => [
                'footer_description' => BusinessSetting::current()->footer_description,
                'copyright_text' => BusinessSetting::current()->copyright_text,
            ],
            'categories' => Category::query()->ordered()->get(['id', 'name', 'is_featured', 'is_published']),
            'products' => Product::query()->with('primaryImage')->orderBy('name')->get(['id', 'name', 'is_featured', 'status'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'is_featured' => $p->is_featured,
                    'status' => $p->status,
                    'thumb' => Media::url($p->primaryImage?->path, 'sm'),
                ]),
            'pages' => Page::query()->get(['id', 'slug', 'title', 'is_published', 'updated_at'])
                ->sortBy(fn (Page $p) => array_search($p->slug, array_keys(Page::DEFAULTS), true))
                ->map(fn (Page $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'url' => $p->url(),
                    'is_published' => $p->is_published,
                    'updated_at' => $p->updated_at?->diffForHumans(),
                ])->values(),
            'icons' => HomepageSetting::WHY_ICONS,
            'marqueeSpeeds' => HomepageSetting::MARQUEE_SPEEDS,
            'slides' => HeroSlide::query()->ordered()->get()->map(fn (HeroSlide $s) => [
                ...$s->only('id', 'eyebrow', 'heading', 'description', 'button_text', 'button_link', 'secondary_text', 'secondary_link', 'content_mode', 'link', 'text_position', 'text_theme', 'is_active'),
                'desktop_image_url' => Media::url($s->desktop_image_path, 'sm'),
                'mobile_image_url' => Media::url($s->mobile_image_path, 'sm'),
            ]),
            'heroOptions' => [
                'types' => HomepageSetting::HERO_TYPES,
                'widths' => HomepageSetting::HERO_WIDTHS,
                'desktopHeights' => HomepageSetting::DESKTOP_HEIGHTS,
                'mobileHeights' => HomepageSetting::MOBILE_HEIGHTS,
                'mobileText' => HomepageSetting::MOBILE_TEXT,
                'dotsPositions' => HomepageSetting::DOTS_POSITIONS,
                'shades' => HomepageSetting::SHADES,
                'positions' => HeroSlide::POSITIONS,
                'themes' => HeroSlide::THEMES,
                'modes' => HeroSlide::MODES,
            ],
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);

        $home = HomepageSetting::current();
        $enabledField = $section.'_enabled';
        if ($section === 'marquee') {
            foreach (['marquee_border', 'marquee_uppercase'] as $flag) {
                $request->merge([$flag => $request->boolean($flag)]);
            }
        }
        if (in_array($section, ['announcement', 'hero', 'marquee', 'categories', 'featured', 'new_arrivals', 'promo', 'about', 'why'], true)) {
            $request->merge([$enabledField => $request->boolean($enabledField)]);
        }
        if ($section === 'hero' && $request->has('hero_autoplay')) {
            $request->merge(['hero_autoplay' => $request->boolean('hero_autoplay')]);
        }

        $data = $request->validate($this->rules($section), [
            'hero_heading.required' => 'Add a heading — it is used by the split and full banner designs.',
        ], $this->attributeNames());

        match ($section) {
            'footer' => BusinessSetting::current()->update($data),
            'categories' => DB::transaction(function () use ($home, $data) {
                Category::query()->update(['is_featured' => false]);
                Category::query()->whereIn('id', $data['featured_ids'] ?? [])->update(['is_featured' => true]);
                unset($data['featured_ids']);
                $home->update($data);
            }),
            'featured' => DB::transaction(function () use ($home, $data) {
                Product::query()->where('is_featured', true)->whereNotIn('id', $data['featured_ids'] ?? [])->update(['is_featured' => false]);
                Product::query()->whereIn('id', $data['featured_ids'] ?? [])->update(['is_featured' => true]);
                unset($data['featured_ids']);
                $home->update($data);
            }),
            default => $this->saveWithImages($home, $section, $request, $data),
        };

        // Bulk updates above bypass model events, so refresh caches explicitly.
        \App\Support\StorefrontCache::flush();

        return back()->with('success', $this->savedMessage('Changes saved.'));
    }

    protected function saveWithImages(HomepageSetting $home, string $section, Request $request, array $data): void
    {
        $oldFiles = [];
        foreach (self::IMAGES[$section] ?? [] as $field => $column) {
            unset($data[$field], $data['remove_'.$field]);

            if ($request->hasFile($field)) {
                try {
                    $data[$column] = $this->images->store($request->file($field), 'banners');
                } catch (RuntimeException $e) {
                    throw ValidationException::withMessages([$field => $e->getMessage()]);
                }
                $oldFiles[] = $home->{$column};
            } elseif ($request->boolean('remove_'.$field)) {
                $data[$column] = null;
                $oldFiles[] = $home->{$column};
            }
        }

        if ($section === 'why') {
            $data['why_items'] = collect($data['why_items'] ?? [])
                ->map(fn ($i) => ['icon' => $i['icon'] ?? 'badge-check', 'title' => trim($i['title']), 'text' => trim($i['text'] ?? '')])
                ->values()->all();
        }

        if ($section === 'promo') {
            // Typed in the store's timezone; stored in UTC.
            $data['promo_ends_at'] = filled($data['promo_ends_at'] ?? null)
                ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d\TH:i', $data['promo_ends_at'], BusinessSetting::current()->timezone ?: config('app.timezone'))->utc()
                : null;
            $data['promo_bg_color'] = Color::isHex($data['promo_bg_color'] ?? null) ? strtoupper($data['promo_bg_color']) : null;
        }

        if ($section === 'marquee') {
            $data['marquee_items'] = collect($data['marquee_items'] ?? [])->map(fn ($t) => trim((string) $t))->filter()->values()->all();
            $data['marquee_separator'] = trim((string) ($data['marquee_separator'] ?? ''));
            $data['marquee_bg_color'] = Color::sanitize($data['marquee_bg_color'] ?? null, '#FFFFFF');
            $data['marquee_text_color'] = Color::sanitize($data['marquee_text_color'] ?? null, '#111111');
            $data['marquee_accent_color'] = Color::isHex($data['marquee_accent_color'] ?? null) ? strtoupper($data['marquee_accent_color']) : null;
        }

        if ($section === 'announcement') {
            $data['announcement_bg_color'] = Color::sanitize($data['announcement_bg_color'] ?? null, '#111827');
            $data['announcement_text_color'] = Color::sanitize($data['announcement_text_color'] ?? null, '#FFFFFF');
        }

        $home->update($data);
        array_map(fn ($path) => $this->images->delete($path), array_filter($oldFiles));
    }

    protected function rules(string $section): array
    {
        $image = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('catalog.max_upload_kb'), 'dimensions:max_width=8000,max_height=8000'];
        $link = ['nullable', 'string', 'max:255', new SafeLink];
        $hex = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return match ($section) {
            'announcement' => [
                'announcement_enabled' => ['boolean'],
                'announcement_text' => ['nullable', 'required_if:announcement_enabled,true', 'string', 'max:255'],
                'announcement_link' => $link,
                'announcement_bg_color' => $hex,
                'announcement_text_color' => $hex,
            ],
            'hero' => [
                'hero_enabled' => ['boolean'],
                'hero_type_desktop' => ['sometimes', 'required', Rule::in(array_keys(HomepageSetting::HERO_TYPES))],
                'hero_type_mobile' => ['sometimes', 'required', Rule::in(array_keys(HomepageSetting::HERO_TYPES))],
                'hero_width' => ['sometimes', 'required', Rule::in(array_keys(HomepageSetting::HERO_WIDTHS))],
                'hero_text_align' => ['sometimes', 'required', Rule::in(['left', 'center'])],
                'hero_autoplay' => ['boolean'],
                'hero_interval' => ['sometimes', 'required', 'integer', 'min:3', 'max:15'],
                'hero_desktop_height' => ['sometimes', 'required', Rule::in(array_keys(HomepageSetting::DESKTOP_HEIGHTS))],
                'hero_mobile_height' => ['sometimes', 'required', Rule::in(array_keys(HomepageSetting::MOBILE_HEIGHTS))],
                'hero_mobile_text' => ['sometimes', 'required', Rule::in(array_keys(HomepageSetting::MOBILE_TEXT))],
                'hero_dots_position' => ['sometimes', 'required', Rule::in(array_keys(HomepageSetting::DOTS_POSITIONS))],
                'hero_mobile_vh' => ['sometimes', 'required', 'integer', 'min:40', 'max:100'],
                'hero_mobile_valign' => ['sometimes', 'required', Rule::in(['bottom', 'middle'])],
                'hero_shade' => ['sometimes', 'required', Rule::in(array_keys(HomepageSetting::SHADES))],
                'hero_eyebrow' => ['nullable', 'string', 'max:80'],
                // The heading is needed only when a device uses the split or banner design.
                // (requiredIf is an "implicit" rule, so it also runs when the field is empty.)
                'hero_heading' => ['nullable', 'string', 'max:160', Rule::requiredIf(function () {
                    $request = request();
                    $home = HomepageSetting::current();

                    return $request->boolean('hero_enabled') && array_intersect([
                        $request->input('hero_type_desktop', $home->hero_type_desktop),
                        $request->input('hero_type_mobile', $home->hero_type_mobile),
                    ], ['split', 'banner']);
                })],
                'hero_description' => ['nullable', 'string', 'max:500'],
                'hero_primary_text' => ['nullable', 'string', 'max:40'],
                'hero_primary_link' => [...$link, 'required_with:hero_primary_text'],
                'hero_secondary_text' => ['nullable', 'string', 'max:40'],
                'hero_secondary_link' => [...$link, 'required_with:hero_secondary_text'],
                'hero_image' => $image,
                'remove_hero_image' => ['nullable', 'boolean'],
                'hero_mobile_image' => $image,
                'remove_hero_mobile_image' => ['nullable', 'boolean'],
            ],
            'marquee' => [
                'marquee_enabled' => ['boolean'],
                'marquee_items' => ['nullable', 'array', 'max:12'],
                'marquee_items.*' => ['nullable', 'string', 'max:80'],
                'marquee_separator' => ['nullable', 'string', 'max:8'],
                'marquee_bg_color' => $hex,
                'marquee_text_color' => $hex,
                'marquee_accent_color' => $hex,
                'marquee_border' => ['boolean'],
                'marquee_uppercase' => ['boolean'],
                'marquee_speed' => ['required', Rule::in(array_keys(HomepageSetting::MARQUEE_SPEEDS))],
            ],
            'categories' => [
                'categories_enabled' => ['boolean'],
                'categories_heading' => ['nullable', 'string', 'max:120'],
                'categories_subheading' => ['nullable', 'string', 'max:255'],
                'featured_ids' => ['nullable', 'array'],
                'featured_ids.*' => ['integer', Rule::exists('categories', 'id')],
            ],
            'featured' => [
                'featured_enabled' => ['boolean'],
                'featured_heading' => ['nullable', 'string', 'max:120'],
                'featured_subheading' => ['nullable', 'string', 'max:255'],
                'featured_ids' => ['nullable', 'array', 'max:24'],
                'featured_ids.*' => ['integer', Rule::exists('products', 'id')],
            ],
            'new_arrivals' => [
                'new_arrivals_enabled' => ['boolean'],
                'new_arrivals_heading' => ['nullable', 'string', 'max:120'],
                'new_arrivals_subheading' => ['nullable', 'string', 'max:255'],
                'new_arrivals_count' => ['required', 'integer', 'min:4', 'max:12'],
            ],
            'promo' => [
                'promo_enabled' => ['boolean'],
                'promo_eyebrow' => ['nullable', 'string', 'max:80'],
                'promo_badge' => ['nullable', 'string', 'max:40'],
                'promo_ends_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
                'promo_bg_color' => $hex,
                'promo_heading' => ['nullable', 'required_if:promo_enabled,true', 'string', 'max:160'],
                'promo_description' => ['nullable', 'string', 'max:500'],
                'promo_button_text' => ['nullable', 'string', 'max:40'],
                'promo_button_link' => [...$link, 'required_with:promo_button_text'],
                'promo_image' => $image,
                'remove_promo_image' => ['nullable', 'boolean'],
            ],
            'about' => [
                'about_enabled' => ['boolean'],
                'about_heading' => ['nullable', 'string', 'max:160'],
                'about_body' => ['nullable', 'required_if:about_enabled,true', 'string', 'max:5000'],
                'about_image' => $image,
                'remove_about_image' => ['nullable', 'boolean'],
            ],
            'why' => [
                'why_enabled' => ['boolean'],
                'why_heading' => ['nullable', 'string', 'max:120'],
                'why_subheading' => ['nullable', 'string', 'max:255'],
                'why_items' => ['nullable', 'array', 'max:8'],
                'why_items.*.icon' => ['nullable', Rule::in(array_keys(HomepageSetting::WHY_ICONS))],
                'why_items.*.title' => ['required', 'string', 'max:80'],
                'why_items.*.text' => ['nullable', 'string', 'max:255'],
            ],
            'footer' => [
                'footer_description' => ['nullable', 'string', 'max:500'],
                'copyright_text' => ['nullable', 'string', 'max:200'],
            ],
        };
    }

    protected function attributeNames(): array
    {
        return [
            'announcement_text' => 'announcement text',
            'marquee_items' => 'messages',
            'marquee_items.*' => 'message',
            'hero_heading' => 'heading',
            'hero_primary_link' => 'primary button link',
            'hero_secondary_link' => 'secondary button link',
            'promo_heading' => 'heading',
            'promo_button_link' => 'button link',
            'about_body' => 'about text',
            'why_items.*.title' => 'title',
            'new_arrivals_count' => 'number of products',
        ];
    }
}
