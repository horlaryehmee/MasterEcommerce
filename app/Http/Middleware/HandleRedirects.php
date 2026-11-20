<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applies admin-managed and automatic URL redirects to storefront GET requests. */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }
        if ($request->is('admin', 'admin/*', 'build/*', 'storage/*', 'up', 'install', 'install/*')) {
            return $next($request);
        }

        $map = Redirect::map();
        if ($map === []) {
            return $next($request);
        }

        $path = Redirect::normalizePath('/'.$request->path());
        if (! isset($map[$path])) {
            return $next($request);
        }

        [$id, $to, $status] = $map[$path];
        Redirect::query()->whereKey($id)->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]);

        // Keep the query string (e.g. ?utm_source=…) unless the target has its own.
        $target = str_starts_with($to, 'http') ? $to : url($to);
        if ($request->getQueryString() && ! str_contains($target, '?')) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->away($target, $status);
    }
}
