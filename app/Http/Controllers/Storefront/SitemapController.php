<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\Media;
use App\Support\StorefrontCache;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $xml = StorefrontCache::remember('sitemap', function () {
            $urls = [
                ['loc' => url('/'), 'lastmod' => null, 'priority' => '1.0'],
                ['loc' => route('products.index'), 'lastmod' => null, 'priority' => '0.9'],
                ['loc' => route('categories.index'), 'lastmod' => null, 'priority' => '0.7'],
            ];

            Category::query()->published()->ordered()->get(['slug', 'name', 'image_path', 'updated_at'])
                ->each(function ($c) use (&$urls) {
                    $urls[] = ['loc' => route('categories.show', $c->slug), 'lastmod' => $c->updated_at, 'priority' => '0.8', 'images' => array_filter([Media::url($c->image_path)])];
                });

            // Products with all their images (Google Images picks them up from here).
            Product::query()->visible()->with('images:id,product_id,path')->latest('updated_at')->get(['id', 'slug', 'updated_at'])
                ->each(function ($p) use (&$urls) {
                    $urls[] = ['loc' => route('products.show', $p->slug), 'lastmod' => $p->updated_at, 'priority' => '0.8', 'images' => $p->images->map(fn ($i) => Media::url($i->path))->filter()->values()->all()];
                });

            Page::query()->published()->get(['slug', 'updated_at'])
                ->each(function ($p) use (&$urls) {
                    $urls[] = ['loc' => $p->url(), 'lastmod' => $p->updated_at, 'priority' => '0.4'];
                });

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
            foreach ($urls as $u) {
                $out .= '  <url><loc>'.e($u['loc']).'</loc>';
                if ($u['lastmod']) {
                    $out .= '<lastmod>'.$u['lastmod']->toAtomString().'</lastmod>';
                }
                $out .= '<priority>'.$u['priority'].'</priority>';
                foreach (array_slice($u['images'] ?? [], 0, 20) as $image) {
                    $out .= '<image:image><image:loc>'.e($image).'</image:loc></image:image>';
                }
                $out .= '</url>'."\n";
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $settings = BusinessSetting::current();
        // Staging/local installs, or "Discourage search engines" in the admin, are kept out of search engines.
        $visible = app()->isProduction() && $settings->search_engines_visible;

        $lines = $visible
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /cart', 'Disallow: /checkout', 'Disallow: /order/', 'Allow: /']
            : ['User-agent: *', 'Disallow: /'];

        // Extra rules from Website Settings → SEO (e.g. rules for specific bots).
        if ($visible && filled($settings->robots_extra)) {
            $lines[] = '';
            array_push($lines, ...preg_split('/\r\n|\r|\n/', trim($settings->robots_extra)));
        }
        if ($visible) {
            array_push($lines, '', 'Sitemap: '.route('sitemap'));
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
