<?php

namespace App\Http\Middleware;

use App\Support\Themes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One-request template previews for the admin's template thumbnails:
 * a signed `?template_preview=atelier&embed=1` shows that template for this
 * request only, without starting a preview in the browser's session (several
 * thumbnails load side by side, and the admin's own browsing stays as it is).
 * Shareable previews go through the `template.preview` route instead.
 */
class TemplatePreview
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->query('template_preview');
        if (Themes::exists($key) && $request->boolean('embed') && $request->hasValidSignature()) {
            $request->attributes->set(Themes::PREVIEW_KEY, $key);
            $request->attributes->set('theme_embed', true);
        }

        return $next($request);
    }
}
