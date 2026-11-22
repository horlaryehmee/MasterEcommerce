<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Stats\Collector;
use App\Services\Stats\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/** Receives the storefront beacon (navigator.sendBeacon). Always answers 204. */
class StatsController extends Controller
{
    public const EVENTS = ['view_item', 'add_to_cart', 'begin_checkout', 'purchase', 'search', 'outbound_click'];

    public function collect(Request $request, Collector $collector): Response
    {
        $empty = response()->noContent();
        if (strlen($request->getContent()) > 16384) {
            return $empty;
        }

        if (! Collector::shouldTrack($request) || UserAgent::parse($request->userAgent())['bot']) {
            return $empty;
        }

        $data = $request->json()->all();

        $uuid = ['required', 'string', 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'];
        $validator = Validator::make($data, [
            't' => ['required', 'in:pageview,engage,event'],
            'vid' => $uuid,
            'sid' => $uuid,
            'pid' => ['required_if:t,pageview,engage', ...array_slice($uuid, 1)],
            'path' => ['required_if:t,pageview', 'nullable', 'string', 'max:255', 'regex:#^/#'],
            'title' => ['nullable', 'string', 'max:300'],
            'ref' => ['nullable', 'string', 'max:1000'],
            'w' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'utm' => ['nullable', 'array:source,medium,campaign'],
            'utm.*' => ['nullable', 'string', 'max:200'],
            'engaged' => ['required_if:t,engage', 'nullable', 'integer', 'min:0'],
            'name' => ['required_if:t,event', 'nullable', 'in:'.implode(',', self::EVENTS)],
            'label' => ['nullable', 'string', 'max:500'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);
        if ($validator->fails()) {
            return $empty;
        }
        $data = $validator->validated();

        // A beacon may come from an older client. Scrub every validated string before persistence.
        array_walk_recursive($data, function (&$value) {
            if (is_string($value) && \App\Support\Tracking::containsPrivateUrl($value)) {
                $value = '/private';
            }
        });

        // Never count the admin or the beacon itself.
        if (isset($data['path']) && preg_match('#^/(admin|hello)(/|$)#', $data['path'])) {
            return $empty;
        }

        match ($data['t']) {
            'pageview' => $collector->pageview($request, $data),
            'engage' => $collector->engagement($data),
            'event' => $collector->event($request, $data),
        };

        return $empty;
    }
}
