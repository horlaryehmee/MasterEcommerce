<?php

namespace App\Http\Middleware;

use App\Models\BusinessSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Cart and checkout only exist when online ordering is switched on in the admin. */
class EnsureShopEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(BusinessSetting::current()->shop_enabled, 404);

        return $next($request);
    }
}
