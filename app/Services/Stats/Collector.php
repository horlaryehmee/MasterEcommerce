<?php

namespace App\Services\Stats;

use App\Models\BusinessSetting;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records hits sent by the storefront beacon (resources/js/lib/stats.js).
 *
 * Accuracy rules:
 * - Only real browsers count: the beacon runs JavaScript, and user agents of
 *   bots, crawlers, headless browsers and monitors are discarded.
 * - Page views are recorded once each (a client-generated ID makes retries idempotent).
 * - Sessions end after 30 minutes without activity, as in Google Analytics.
 * - The logged-in store owner is excluded unless "Track my own visits" is on.
 */
class Collector
{
    public const SESSION_TIMEOUT_MINUTES = 30;

    /** Engagement pings are capped so a tab left open can't inflate time on page. */
    public const MAX_ENGAGED_SECONDS = 1800;

    public function __construct(protected GeoLocator $geo) {}

    public static function shouldTrack(Request $request, ?BusinessSetting $s = null): bool
    {
        $s ??= BusinessSetting::current();

        return $s->stats_enabled && (! $request->user() || $s->track_admins);
    }

    public function pageview(Request $request, array $data): void
    {
        $now = CarbonImmutable::now();
        $local = $now->setTimezone($this->timezone());

        DB::transaction(function () use ($request, $data, $now, $local) {
            [$visitor, $isNewVisitor] = $this->visitor($request, $data['vid'], $now);
            $session = $this->session($request, $visitor, $data, $isNewVisitor, $now, $local);

            // Idempotent: a retried beacon with the same page-view ID is ignored.
            if (DB::table('stats_pageviews')->where('pid', $data['pid'])->exists()) {
                return;
            }

            DB::table('stats_pageviews')->insert([
                'session_id' => $session->id,
                'visitor_id' => $visitor->id,
                'pid' => $data['pid'],
                'path' => $data['path'],
                'title' => isset($data['title']) ? mb_substr($data['title'], 0, 200) : null,
                'created_at' => $now,
                'local_date' => $local->toDateString(),
                'local_hour' => $local->hour,
            ]);

            DB::table('stats_sessions')->where('id', $session->id)->update([
                'pageviews' => DB::raw('pageviews + 1'),
                'exit_path' => $data['path'],
                'last_activity_at' => $now,
            ]);
            DB::table('stats_visitors')->where('id', $visitor->id)->update([
                'pageviews_count' => DB::raw('pageviews_count + 1'),
                'last_seen_at' => $now,
            ]);
        });
    }

    /** Time the page was visible (cumulative seconds for that page view). */
    public function engagement(array $data): void
    {
        $view = DB::table('stats_pageviews')->where('pid', $data['pid'])->first(['id', 'session_id', 'engaged_seconds']);
        if (! $view) {
            return;
        }

        $seconds = min((int) $data['engaged'], self::MAX_ENGAGED_SECONDS);
        $delta = $seconds - (int) $view->engaged_seconds;
        if ($delta <= 0) {
            return;
        }

        DB::table('stats_pageviews')->where('id', $view->id)->update(['engaged_seconds' => $seconds]);
        DB::table('stats_sessions')->where('id', $view->session_id)->update([
            'engaged_seconds' => DB::raw('engaged_seconds + '.$delta),
            'last_activity_at' => now(),
        ]);
    }

    public function event(Request $request, array $data): void
    {
        $session = DB::table('stats_sessions')->where('sid', $data['sid'])->first(['id', 'visitor_id']);
        // Beacons can arrive out of order: start the session from the event's context.
        if (! $session && isset($data['path'])) {
            $now = CarbonImmutable::now();
            [$visitor, $isNew] = $this->visitor($request, $data['vid'], $now);
            $session = $this->session($request, $visitor, $data, $isNew, $now, $now->setTimezone($this->timezone()));
        }
        if (! $session) {
            return;
        }

        $local = CarbonImmutable::now()->setTimezone($this->timezone());
        DB::table('stats_events')->insert([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'name' => $data['name'],
            'label' => isset($data['label']) ? mb_substr((string) $data['label'], 0, 255) : null,
            'value' => isset($data['value']) && is_numeric($data['value']) ? round((float) $data['value'], 2) : null,
            'created_at' => now(),
            'local_date' => $local->toDateString(),
        ]);

        $update = ['last_activity_at' => now()];
        if ($data['name'] === 'purchase') {
            $update['has_conversion'] = true;
        }
        DB::table('stats_sessions')->where('id', $session->id)->update($update);
    }

    /** Link a new order to the visitor who placed it (visitor cookie set by the beacon). */
    public static function linkOrder(Request $request, Order $order): void
    {
        $uid = (string) $request->cookie('nx_vid');
        if (! preg_match('/^[0-9a-f-]{36}$/i', $uid)) {
            return;
        }
        $visitorId = DB::table('stats_visitors')->where('uid', $uid)->value('id');
        if ($visitorId) {
            $order->forceFill(['stats_visitor_id' => $visitorId])->saveQuietly();
        }
    }

    public function timezone(): string
    {
        $tz = BusinessSetting::current()->timezone;

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : config('app.timezone', 'UTC');
    }

    /** @return array{0: object, 1: bool} visitor row and whether it is new */
    protected function visitor(Request $request, string $uid, CarbonImmutable $now): array
    {
        $visitor = DB::table('stats_visitors')->where('uid', $uid)->first();
        if ($visitor) {
            return [$visitor, false];
        }

        $geo = $this->geo->locate($request);
        $ua = UserAgent::parse($request->userAgent());
        $id = DB::table('stats_visitors')->insertGetId([
            'uid' => $uid,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'country_code' => $geo['country'],
            'region' => $geo['region'],
            'city' => $geo['city'],
            'device' => $ua['device'],
            'browser' => $ua['browser'],
            'os' => $ua['os'],
        ]);

        return [DB::table('stats_visitors')->find($id), true];
    }

    protected function session(Request $request, object $visitor, array $data, bool $isNewVisitor, CarbonImmutable $now, CarbonImmutable $local): object
    {
        $session = DB::table('stats_sessions')->where('sid', $data['sid'])->first();
        if ($session && (int) $session->visitor_id === (int) $visitor->id) {
            return $session;
        }

        $source = Channel::classify($data['ref'] ?? null, $data['utm'] ?? [], (string) parse_url(config('app.url'), PHP_URL_HOST) ?: $request->getHost());
        // The session's location and device come from this request (a visitor can travel or switch devices).
        $geo = $this->geo->locate($request);
        $ua = UserAgent::parse($request->userAgent());

        // A reused session ID from another visitor (copied storage) gets a fresh one.
        $sid = $session ? (string) \Illuminate\Support\Str::uuid() : $data['sid'];

        $id = DB::table('stats_sessions')->insertGetId([
            'visitor_id' => $visitor->id,
            'sid' => $sid,
            'started_at' => $now,
            'last_activity_at' => $now,
            'local_date' => $local->toDateString(),
            'is_new_visitor' => $isNewVisitor,
            'entry_path' => $data['path'],
            'exit_path' => $data['path'],
            'referrer_host' => $source['referrer_host'],
            'referrer_url' => isset($data['ref']) ? mb_substr($data['ref'], 0, 500) : null,
            'channel' => $source['channel'],
            'source' => $source['source'],
            'medium' => $source['medium'],
            'campaign' => $source['campaign'],
            'country_code' => $geo['country'] ?? $visitor->country_code,
            'region' => $geo['region'],
            'city' => $geo['city'],
            'device' => $ua['device'],
            'browser' => $ua['browser'],
            'os' => $ua['os'],
            'screen_width' => isset($data['w']) ? min((int) $data['w'], 10000) : null,
        ]);

        DB::table('stats_visitors')->where('id', $visitor->id)->update(array_filter([
            'sessions_count' => DB::raw('sessions_count + 1'),
            'first_channel' => $visitor->first_channel ? null : $source['channel'],
            'first_source' => $visitor->first_source ? null : ($source['source'] ?? 'Direct'),
            // Keep the latest known location and device.
            'country_code' => $geo['country'],
            'region' => $geo['region'],
            'city' => $geo['city'],
            'device' => $ua['device'],
            'browser' => $ua['browser'],
            'os' => $ua['os'],
        ], fn ($v) => $v !== null));

        return DB::table('stats_sessions')->find($id);
    }
}
