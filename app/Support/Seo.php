<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Collects the meta tags and structured data for one storefront page.
 * Rendered server-side in resources/views/app.blade.php so crawlers and
 * social networks see it without running JavaScript.
 */
class Seo
{
    protected ?string $title = null;

    protected ?string $description = null;

    protected ?string $canonical = null;

    protected ?string $image = null;

    protected string $type = 'website';

    protected bool $noindex = false;

    protected bool $isHome = false;

    /** Open Graph product tags (price, currency, availability). */
    protected array $productMeta = [];

    /** @var array<int, array> */
    protected array $schemas = [];

    public static function make(): self
    {
        return new self;
    }

    /** Page title; the business name is appended automatically (except on the homepage). */
    public function title(?string $title): self
    {
        $this->title = $title ? trim($title) : null;

        return $this;
    }

    public function description(?string $description): self
    {
        if ($description) {
            $this->description = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 300, '…');
        }

        return $this;
    }

    public function canonical(?string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    /**
     * Canonical for a paginated list: page 1 is the plain URL, later pages
     * point to themselves (?page=N), as Google recommends.
     */
    public function paginatedCanonical(string $url, int $page): self
    {
        return $this->canonical($page > 1 ? $url.'?page='.$page : $url);
    }

    public function image(?string $url): self
    {
        if ($url) {
            $this->image = $url;
        }

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function noindex(bool $noindex = true): self
    {
        $this->noindex = $noindex;

        return $this;
    }

    public function home(): self
    {
        $this->isHome = true;

        return $this;
    }

    public function schema(array $schema): self
    {
        $this->schemas[] = ['@context' => 'https://schema.org'] + $schema;

        return $this;
    }

    /** @param array<int, array{0: string, 1: string}> $items [name, url] pairs */
    public function breadcrumbs(array $items): self
    {
        return $this->schema([
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => $item[1],
            ])->all(),
        ]);
    }

    /** Product structured data built only from real catalog data (no ratings/reviews). */
    public function product(Product $product): self
    {
        $settings = BusinessSetting::current();
        $images = $product->images->map(fn ($i) => Media::url($i->path))->filter()->values()->all();

        $this->productMeta = [
            'product:price:amount' => number_format((float) $product->price, 2, '.', ''),
            'product:price:currency' => $settings->currency_code,
            'product:availability' => in_array($product->availability, Product::PURCHASABLE, true) ? 'in stock' : ($product->availability === 'out_of_stock' ? 'out of stock' : 'available for order'),
            'product:condition' => 'new',
        ];

        $rating = $product->ratingSummary();
        $topReviews = $rating ? $product->reviews()->approved()->latest('approved_at')->limit(5)->get() : collect();

        return $this->type('product')->schema(array_filter([
            '@type' => 'Product',
            'name' => $product->name,
            'aggregateRating' => $rating ? [
                '@type' => 'AggregateRating',
                'ratingValue' => $rating['average'],
                'reviewCount' => $rating['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
            'review' => $topReviews->isNotEmpty() ? $topReviews->map(fn ($r) => [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $r->name],
                'datePublished' => $r->approved_at?->toDateString(),
                'reviewBody' => $r->body,
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $r->rating, 'bestRating' => 5],
            ])->values()->all() : null,
            'description' => $product->short_description ?: Markdown::excerpt($product->description, 300),
            'image' => $images ?: null,
            'url' => $product->url(),
            'category' => $product->category?->name,
            // Variations with different prices: a price range (Google shows "₦X – ₦Y").
            'offers' => $product->hasPriceRange() ? [
                '@type' => 'AggregateOffer',
                'url' => $product->url(),
                'priceCurrency' => $settings->currency_code,
                'lowPrice' => number_format((float) $product->price, 2, '.', ''),
                'highPrice' => number_format((float) $product->price_max, 2, '.', ''),
                'offerCount' => $product->relationLoaded('variants') ? $product->variants->where('is_enabled', true)->count() : null,
                'availability' => Product::SCHEMA_AVAILABILITY[$product->availability] ?? 'https://schema.org/InStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'Organization', 'name' => $settings->business_name],
            ] : [
                '@type' => 'Offer',
                'url' => $product->url(),
                'priceCurrency' => $settings->currency_code,
                'price' => number_format((float) $product->price, 2, '.', ''),
                'availability' => Product::SCHEMA_AVAILABILITY[$product->availability] ?? 'https://schema.org/InStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'Organization', 'name' => $settings->business_name],
            ],
        ]));
    }

    public function toArray(): array
    {
        $settings = BusinessSetting::current();
        $siteName = $settings->business_name;
        $defaultTitle = $settings->seo_title ?: $siteName;

        $separator = trim((string) $settings->title_separator) ?: '|';
        $title = $this->isHome || ! $this->title
            ? $defaultTitle
            : $this->title.' '.$separator.' '.$siteName;

        // "Discourage search engines" (Website Settings → SEO) noindexes every page.
        $noindex = $this->noindex || ! $settings->search_engines_visible;

        $description = $this->description
            ?: ($settings->seo_description ?: Str::limit((string) $settings->business_description, 300, '…'));

        $image = $this->image ?: Media::url($settings->og_image_path) ?: Media::url($settings->logo_path);

        $schemas = $this->schemas;
        if ($this->isHome) {
            $schemas[] = $this->businessSchema($settings);
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/products').'?search={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ];
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $this->canonical ?: url()->current(),
            'image' => $image,
            'type' => $this->type === 'product' ? 'product' : 'website',
            'site_name' => $siteName,
            // Large image previews and full snippets in Google results for indexable pages.
            'robots' => $noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'schemas' => $schemas,
            'product' => $this->productMeta,
            'twitter_site' => $settings->twitter_handle ? '@'.ltrim($settings->twitter_handle, '@') : null,
        ];
    }

    protected function businessSchema(BusinessSetting $s): array
    {
        $sameAs = \App\Models\SocialLink::query()->orderBy('sort_order')->pluck('url')->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => $s->business_name,
            'description' => $s->business_description,
            'url' => url('/'),
            'logo' => Media::url($s->logo_path),
            'image' => Media::url($s->og_image_path) ?: Media::url($s->logo_path),
            'email' => $s->email,
            'telephone' => $s->phone,
            'address' => $s->address ? ['@type' => 'PostalAddress', 'streetAddress' => $s->address] : null,
            'sameAs' => $sameAs ?: null,
        ]);
    }
}
