<?php

namespace App\Http\Controllers\Storefront;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Page;
use App\Support\Markdown;
use App\Support\Media;
use App\Support\Seo;
use Inertia\Response;

class PageController extends StorefrontController
{
    public function show(string $slug): Response
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();
        $settings = BusinessSetting::current();
        $content = $this->fill($page->content, $settings);

        $seo = Seo::make()
            ->title($page->seo_title ?: $page->title)
            ->description($page->seo_description ?: Markdown::excerpt($content))
            ->canonical($page->url())
            ->breadcrumbs([['Home', url('/')], [$page->title, $page->url()]]);

        $base = [
            'slug' => $page->slug,
            'title' => $page->title,
            'content_html' => Markdown::toHtml($content),
        ];

        return match ($page->slug) {
            'about' => $this->about($page, $base, $settings, $seo),
            'faq' => $this->faq($page, $base, $settings, $seo),
            'contact' => $this->page('Storefront/Contact', [
                'page' => $base,
                'contact' => $this->contact($settings),
            ], $seo),
            default => $this->page('Storefront/Page', [
                'page' => $base,
                'contact' => null,
            ], $seo),
        };
    }

    protected function about(Page $page, array $base, BusinessSetting $settings, Seo $seo): Response
    {
        $home = HomepageSetting::current();
        $imagePath = $page->image_path ?: $home->about_image_path;

        // "Our values" reuses the homepage "Why choose us" points, so they're edited in one place.
        $values = collect($home->why_items ?? [])->filter(fn ($i) => filled($i['title'] ?? null))->values();

        $categories = Category::query()->published()->ordered()
            ->withCount(['products as published_products_count' => fn ($q) => $q->published()])
            ->limit(5)->get();

        $stats = collect($page->extras['stats'] ?? [])->filter(fn ($s) => filled($s['value'] ?? null) && filled($s['label'] ?? null))->values();
        $quote = $page->extras['quote'] ?? [];

        return $this->page('Storefront/About', [
            'page' => $base + [
                'headline' => $this->fill($home->about_heading, $settings),
                'intro' => $settings->business_description,
                'image' => Media::image($imagePath, $page->title),
            ],
            'values' => $values->isEmpty() ? null : [
                'heading' => $home->why_heading ?: 'What we stand for',
                'subheading' => $home->why_subheading,
                'items' => $values,
            ],
            'categories' => $categories->map->toCard()->values(),
            'contact' => $this->contact($settings),
            'stats' => $stats->all(),
            'quote' => filled($quote['text'] ?? null) ? [
                'text' => $this->fill($quote['text'], $settings),
                'name' => $quote['name'] ?? null,
                'role' => $quote['role'] ?? null,
            ] : null,
        ], $seo->image(Media::url($imagePath)));
    }

    protected function faq(Page $page, array $base, BusinessSetting $settings, Seo $seo): Response
    {
        $items = collect($page->faq_items ?? [])
            ->filter(fn ($i) => filled($i['question'] ?? null))
            ->map(fn ($i) => [
                'question' => $this->fill($i['question'], $settings),
                'answer_html' => Markdown::toHtml($this->fill($i['answer'] ?? '', $settings)),
            ])->values();

        if ($items->isNotEmpty()) {
            // Built only from the questions actually shown on the page.
            $seo->schema([
                '@type' => 'FAQPage',
                'mainEntity' => $items->map(fn ($i) => [
                    '@type' => 'Question',
                    'name' => $i['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($i['answer_html']))],
                ])->all(),
            ]);
        }

        return $this->page('Storefront/Faq', [
            'page' => $base + ['items' => $items],
            'contact' => $this->contact($settings),
        ], $seo);
    }

    protected function contact(BusinessSetting $settings): array
    {
        return [
            'email' => $settings->email,
            'phone' => $settings->phone,
            'address' => $settings->address,
            'opening_hours' => $settings->opening_hours,
        ];
    }

    /** Placeholders let the same starter text work for any business. */
    protected function fill(?string $text, BusinessSetting $settings): string
    {
        return strtr((string) $text, [
            '{business_name}' => $settings->business_name,
            '{email}' => $settings->email ?: 'the contact details on our Contact page',
            '{phone}' => (string) $settings->phone,
            '{address}' => (string) $settings->address,
        ]);
    }
}
