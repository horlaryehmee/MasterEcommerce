<?php

namespace App\Services\Stats;

use App\Models\BusinessSetting;
use App\Services\Shipping;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Figures for Admin → Analytics, using Google Analytics 4 definitions:
 * - Visitors: unique visitors with a visit in the period.
 * - Engaged visit: 10+ seconds of engagement, 2+ page views, or an order.
 * - Bounce rate: share of visits that were not engaged.
 * - Avg. engagement time: total engaged time ÷ visitors.
 * Days are in the store's timezone (Website Settings → Analytics).
 */
class Report
{
    public const PERIODS = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', '12m' => 'Last 12 months'];

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    public readonly string $period;

    public readonly string $timezone;

    public function __construct(?string $period = '7d', ?string $from = null, ?string $to = null)
    {
        $this->timezone = app(Collector::class)->timezone();
        $today = CarbonImmutable::now($this->timezone)->startOfDay();

        if ($period === 'custom' && $from && $to && strtotime($from) && strtotime($to)) {
            $a = CarbonImmutable::parse($from, $this->timezone)->startOfDay();
            $b = CarbonImmutable::parse($to, $this->timezone)->startOfDay();
            [$a, $b] = $a->gt($b) ? [$b, $a] : [$a, $b];
            $this->from = $a->max($b->subDays(730));
            $this->to = $b->min($today);
            $this->period = 'custom';

            return;
        }

        $this->period = array_key_exists((string) $period, self::PERIODS) ? $period : '7d';
        [$this->from, $this->to] = match ($this->period) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            '30d' => [$today->subDays(29), $today],
            '90d' => [$today->subDays(89), $today],
            '12m' => [$today->subMonths(12)->addDay(), $today],
            default => [$today->subDays(6), $today],
        };
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** The same-length period just before this one, for comparisons. */
    public function previous(): self
    {
        $days = $this->days();

        return new self('custom', $this->from->subDays($days)->toDateString(), $this->from->subDay()->toDateString());
    }

    protected function sessions(): Builder
    {
        return DB::table('stats_sessions')->whereBetween('local_date', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    protected function pageviews(): Builder
    {
        return DB::table('stats_pageviews')->whereBetween('local_date', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    protected function events(string $name): Builder
    {
        return DB::table('stats_events')->where('name', $name)->whereBetween('local_date', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    /** SQL for "this visit was engaged" (GA4). */
    protected const ENGAGED = '(engaged_seconds >= 10 OR pageviews >= 2 OR has_conversion = 1)';

    public function totals(): array
    {
        $s = $this->sessions()->selectRaw('COUNT(*) as sessions, COUNT(DISTINCT visitor_id) as visitors, SUM(CASE WHEN is_new_visitor = 1 THEN 1 ELSE 0 END) as new_visitors, SUM(CASE WHEN '.self::ENGAGED.' THEN 1 ELSE 0 END) as engaged, SUM(engaged_seconds) as engaged_seconds, SUM(CASE WHEN has_conversion = 1 THEN 1 ELSE 0 END) as converted')->first();
        $sessions = (int) $s->sessions;
        $visitors = (int) $s->visitors;
        $views = $this->pageviews()->count();
        $orders = $this->orders();

        return [
            'visitors' => $visitors,
            'new_visitors' => (int) $s->new_visitors,
            'returning_visitors' => max(0, $visitors - (int) $s->new_visitors),
            'sessions' => $sessions,
            'pageviews' => $views,
            'views_per_session' => $sessions ? round($views / $sessions, 2) : 0,
            'bounce_rate' => $sessions ? round((1 - (int) $s->engaged / $sessions) * 100, 1) : 0,
            'avg_engagement_seconds' => $visitors ? (int) round((int) $s->engaged_seconds / $visitors) : 0,
            'orders' => $orders['count'],
            'revenue' => $orders['revenue'],
            'conversion_rate' => $sessions ? round((int) $s->converted / $sessions * 100, 2) : 0,
        ];
    }

    /** Orders from the orders table (the source of truth), by local day. */
    protected function orders(): array
    {
        $row = DB::table('orders')
            ->whereNotIn('status', \App\Models\Order::NOT_SALES)
            ->whereBetween('created_at', [$this->from->utc(), $this->to->endOfDay()->utc()])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as revenue')->first();

        return ['count' => (int) $row->count, 'revenue' => (float) $row->revenue];
    }

    /** Views and visitors per day (or per hour for a single day). */
    public function series(): array
    {
        if ($this->days() === 1) {
            $rows = $this->pageviews()->selectRaw('local_hour as k, COUNT(*) as views, COUNT(DISTINCT visitor_id) as visitors')->groupBy('local_hour')->get()->keyBy('k');
            $points = [];
            $lastHour = $this->to->isToday() ? (int) CarbonImmutable::now($this->timezone)->hour : 23;
            for ($h = 0; $h <= $lastHour; $h++) {
                $points[] = ['key' => sprintf('%02d:00', $h), 'label' => CarbonImmutable::createFromTime($h)->format('ga'), 'views' => (int) ($rows[$h]->views ?? 0), 'visitors' => (int) ($rows[$h]->visitors ?? 0)];
            }

            return ['unit' => 'hour', 'points' => $points];
        }

        $monthly = $this->days() > 120;
        $views = $this->pageviews()->selectRaw('local_date as k, COUNT(*) as n')->groupBy('local_date')->pluck('n', 'k');
        // Unique visitors per bucket must be counted per bucket, not summed per day.
        $visitorsByDay = $this->sessions()->select('local_date', 'visitor_id')->distinct()->get();

        $buckets = [];
        foreach (CarbonPeriod::create($this->from, $this->to) as $day) {
            $key = $monthly ? $day->format('Y-m') : $day->toDateString();
            $buckets[$key] ??= ['key' => $key, 'label' => $monthly ? $day->format('M Y') : $day->format('j M'), 'views' => 0, 'visitors' => [], 'start' => $day->toDateString()];
            $buckets[$key]['views'] += (int) ($views[$day->toDateString()] ?? 0);
        }
        foreach ($visitorsByDay as $row) {
            $key = $monthly ? substr((string) $row->local_date, 0, 7) : substr((string) $row->local_date, 0, 10);
            if (isset($buckets[$key])) {
                $buckets[$key]['visitors'][$row->visitor_id] = true;
            }
        }

        return ['unit' => $monthly ? 'month' : 'day', 'points' => array_values(array_map(fn ($b) => [...$b, 'visitors' => count($b['visitors'])], $buckets))];
    }

    /** Top values of a session column with visits and unique visitors. */
    public function bySession(string $column, int $limit = 10): array
    {
        $total = max(1, $this->sessions()->count());

        return $this->sessions()->whereNotNull($column)
            ->selectRaw("{$column} as name, COUNT(*) as sessions, COUNT(DISTINCT visitor_id) as visitors")
            ->groupBy($column)->orderByDesc('sessions')->limit($limit)->get()
            ->map(fn ($r) => ['name' => (string) $r->name, 'sessions' => (int) $r->sessions, 'visitors' => (int) $r->visitors, 'share' => round($r->sessions / $total * 100, 1)])
            ->all();
    }

    public function pages(int $limit = 10): array
    {
        return $this->pageviews()
            ->selectRaw('path, MAX(title) as title, COUNT(*) as views, COUNT(DISTINCT visitor_id) as visitors, AVG(engaged_seconds) as avg_engaged')
            ->groupBy('path')->orderByDesc('views')->limit($limit)->get()
            ->map(fn ($r) => ['path' => $r->path, 'title' => self::pageTitle($r->title), 'views' => (int) $r->views, 'visitors' => (int) $r->visitors, 'avg_engaged' => (int) round((float) $r->avg_engaged)])
            ->all();
    }

    public function entryPages(int $limit = 10): array
    {
        return array_map(fn ($r) => ['path' => $r['name'], 'sessions' => $r['sessions'], 'visitors' => $r['visitors']], $this->bySession('entry_path', $limit));
    }

    public function countries(int $limit = 250): array
    {
        $rows = $this->bySession('country_code', $limit);
        $total = max(1, $this->sessions()->distinct()->count('visitor_id'));
        $unknown = $this->sessions()->whereNull('country_code')->distinct()->count('visitor_id');

        $out = array_map(fn ($r) => ['code' => $r['name'], 'name' => Shipping::countryName($r['name']), 'visitors' => $r['visitors'], 'sessions' => $r['sessions'], 'share' => round($r['visitors'] / $total * 100, 1)], $rows);
        usort($out, fn ($a, $b) => $b['visitors'] <=> $a['visitors']);
        if ($unknown) {
            $out[] = ['code' => null, 'name' => 'Unknown', 'visitors' => $unknown, 'sessions' => 0, 'share' => round($unknown / $total * 100, 1)];
        }

        return $out;
    }

    public function cities(int $limit = 10): array
    {
        return $this->sessions()->whereNotNull('city')
            ->selectRaw('city, country_code, COUNT(DISTINCT visitor_id) as visitors')
            ->groupBy('city', 'country_code')->orderByDesc('visitors')->limit($limit)->get()
            ->map(fn ($r) => ['name' => $r->city, 'country' => $r->country_code, 'visitors' => (int) $r->visitors])->all();
    }

    /** Most frequent labels of an event (search terms, outbound clicks, products viewed). */
    public function eventLabels(string $name, int $limit = 10): array
    {
        return $this->events($name)->whereNotNull('label')
            ->selectRaw('label, COUNT(*) as count, COUNT(DISTINCT visitor_id) as visitors')
            ->groupBy('label')->orderByDesc('count')->limit($limit)->get()
            ->map(fn ($r) => ['name' => $r->label, 'count' => (int) $r->count, 'visitors' => (int) $r->visitors])->all();
    }

    /** Shop funnel: visits that reached each step. */
    public function funnel(): array
    {
        $sessions = $this->sessions()->count();
        $steps = ['view_item' => 'Viewed a product', 'add_to_cart' => 'Added to cart', 'begin_checkout' => 'Started checkout', 'purchase' => 'Placed an order'];
        $out = [['key' => 'visit', 'label' => 'Visited', 'sessions' => $sessions]];
        foreach ($steps as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label, 'sessions' => $this->events($key)->distinct()->count('session_id')];
        }

        return array_map(fn ($s) => [...$s, 'rate' => $sessions ? round($s['sessions'] / $sessions * 100, 1) : 0], $out);
    }

    public function summary(): array
    {
        $totals = $this->totals();
        $prev = $this->previous()->totals();
        $change = fn ($key) => $prev[$key] > 0 ? round(($totals[$key] - $prev[$key]) / $prev[$key] * 100, 1) : null;

        return [
            'period' => $this->period,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'label' => $this->label(),
            'totals' => [...$totals, 'revenue_formatted' => Money::format($totals['revenue'])],
            'previous' => $prev,
            'change' => collect(array_keys($totals))->mapWithKeys(fn ($k) => [$k => $change($k)])->all(),
            'series' => $this->series(),
        ];
    }

    public function label(): string
    {
        if ($this->period !== 'custom') {
            return self::PERIODS[$this->period];
        }

        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /** Right now: visitors active in the last 5 / 30 minutes. */
    public static function realtime(): array
    {
        $now = now();
        $active = fn (int $minutes) => DB::table('stats_sessions')->where('last_activity_at', '>=', $now->copy()->subMinutes($minutes));

        $perMinute = array_fill(0, 30, 0);
        DB::table('stats_pageviews')->where('created_at', '>=', $now->copy()->subMinutes(30))->pluck('created_at')
            ->each(function ($t) use (&$perMinute, $now) {
                // Seconds from the view until now (Carbon 3 differences are signed).
                $ago = (int) floor(CarbonImmutable::parse($t)->diffInSeconds($now) / 60);
                if ($ago >= 0 && $ago < 30) {
                    $perMinute[29 - $ago]++;
                }
            });

        $activeSessions = $active(5)->pluck('id');
        $currentPages = DB::table('stats_pageviews as p')
            ->whereIn('p.session_id', $activeSessions)
            ->whereRaw('p.id = (SELECT MAX(p2.id) FROM stats_pageviews p2 WHERE p2.session_id = p.session_id)')
            ->selectRaw('p.path, MAX(p.title) as title, COUNT(*) as visitors')->groupBy('p.path')->orderByDesc('visitors')->limit(10)->get();

        $countries = $active(5)->whereNotNull('country_code')->selectRaw('country_code, COUNT(DISTINCT visitor_id) as visitors')->groupBy('country_code')->orderByDesc('visitors')->limit(10)->get();

        $recent = DB::table('stats_pageviews as p')->join('stats_sessions as s', 's.id', '=', 'p.session_id')
            ->where('p.created_at', '>=', $now->copy()->subMinutes(30))
            ->orderByDesc('p.id')->limit(20)
            ->get(['p.path', 'p.title', 'p.created_at', 'p.visitor_id', 's.country_code', 's.city', 's.device', 's.browser', 's.channel', 's.source']);

        return [
            'active5' => $active(5)->distinct()->count('visitor_id'),
            'active30' => $active(30)->distinct()->count('visitor_id'),
            'perMinute' => $perMinute,
            'pages' => $currentPages->map(fn ($r) => ['path' => $r->path, 'title' => self::pageTitle($r->title), 'visitors' => (int) $r->visitors])->all(),
            'countries' => $countries->map(fn ($r) => ['code' => $r->country_code, 'name' => Shipping::countryName($r->country_code), 'visitors' => (int) $r->visitors])->all(),
            'recent' => $recent->map(fn ($r) => [
                'path' => $r->path,
                'title' => self::pageTitle($r->title),
                'ago' => CarbonImmutable::parse($r->created_at)->diffForHumans(short: true),
                'visitor_id' => $r->visitor_id,
                'country' => $r->country_code,
                'country_name' => Shipping::countryName($r->country_code),
                'city' => $r->city,
                'device' => $r->device,
                'browser' => $r->browser,
                'source' => $r->source ?: $r->channel,
            ])->all(),
        ];
    }

    /** "Desk Lamp | Nexa Stores" → "Desk Lamp" (the site name adds nothing in reports). */
    public static function pageTitle(?string $title): ?string
    {
        if (! $title) {
            return null;
        }
        $site = preg_quote(BusinessSetting::current()->business_name, '/');

        return preg_replace('/\s[|—\-·•:–]\s'.$site.'$/u', '', $title) ?: $title;
    }

    public static function geoStatus(): array
    {
        $db = GeoLocator::installed();

        return $db
            ? ['level' => $db['level'], 'updated' => CarbonImmutable::createFromTimestamp($db['updated_at'])->diffForHumans()]
            : ['level' => null, 'updated' => null];
    }

    public static function storeTimezone(): string
    {
        return BusinessSetting::current()->timezone;
    }
}
