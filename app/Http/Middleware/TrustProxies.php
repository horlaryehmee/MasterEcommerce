<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Which reverse proxies (and which of their headers) to believe.
 *
 * - TRUSTED_PROXIES empty (the default, typical cPanel / Cloudflare hosting): only
 *   "this visit was HTTPS" (X-Forwarded-Proto/Port) is believed, from any peer.
 *   Without it, sites whose HTTPS is handled by a proxy would redirect to https
 *   forever. Client IP headers are ignored, so IP-based limits (login, reviews,
 *   checkout) can't be dodged with a forged X-Forwarded-For.
 * - TRUSTED_PROXIES set (addresses/CIDRs of proxies you operate): only those
 *   peers are believed, and their X-Forwarded-For gives the real visitor IP.
 */
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        return $this->configured() ?: '*';
    }

    protected function headers()
    {
        return $this->configured()
            ? Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_FOR
            : Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT;
    }

    /** @return list<string> */
    protected function configured(): array
    {
        return array_values(array_filter((array) config('trustedproxy.proxies', [])));
    }
}
