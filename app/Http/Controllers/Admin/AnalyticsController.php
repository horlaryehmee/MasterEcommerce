<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\Shipping;
use App\Services\Stats\Channel;
use App\Services\Stats\Report;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Built-in visitor statistics (Admin → Analytics). */
class AnalyticsController extends Controller
{
    protected function report(Request $request): Report
    {
        return new Report($request->query('period', '7d'), $request->query('from'), $request->query('to'));
    }

    protected function shared(): array
    {
        $s = BusinessSetting::current();

        return [
            'periods' => Report::PERIODS,
            'geo' => Report::geoStatus(),
            'enabled' => $s->stats_enabled,
            'timezone' => $s->timezone,
        ];
    }

    public function index(Request $request): Response
    {
        $r = $this->report($request);

        return Inertia::render('Admin/Analytics/Overview', [
            ...$this->shared(),
            'summary' => $r->summary(),
            'realtime' => fn () => ['active5' => Report::realtime()['active5']],
            'pages' => $r->pages(),
            'entryPages' => $r->entryPages(),
            'channels' => $r->bySession('channel'),
            'referrers' => $r->bySession('referrer_host'),
            'campaigns' => $r->bySession('campaign'),
            'countries' => $r->countries(),
            'cities' => $r->cities(),
            'devices' => $r->bySession('device'),
            'browsers' => $r->bySession('browser'),
            'os' => $r->bySession('os'),
            'searches' => $r->eventLabels('search'),
            'clicks' => $r->eventLabels('outbound_click'),
            'products' => $r->eventLabels('view_item'),
            'funnel' => $r->funnel(),
        ]);
    }

    public function realtime(): Response
    {
        return Inertia::render('Admin/Analytics/Realtime', [...$this->shared(), 'realtime' => Report::realtime()]);
    }

    protected function visitorFilters(Request $request): array
    {
        return [
            'country' => preg_match('/^[A-Z]{2}$/', (string) $request->query('country')) ? $request->query('country') : '',
            'channel' => in_array($request->query('channel'), Channel::CHANNELS, true) ? $request->query('channel') : '',
            'search' => trim(mb_substr((string) $request->query('search', ''), 0, 60)),
            'per_page' => \App\Support\AdminList::perPage($request),
        ];
    }

    public function visitorsExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $r = $this->report($request);
        $query = $this->visitorsQuery($r, $this->visitorFilters($request))
            ->when(\App\Support\AdminList::ids($request), fn ($q, $ids) => $q->whereIn('v.id', $ids));

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $v) {
                $row = $this->visitorRow($v);
                yield [
                    $row['name'], $row['country_name'], $v->region, $v->city, $v->device, $v->browser, $v->os,
                    $v->first_channel, $v->first_source,
                    \App\Support\AdminList::localDate($v->first_seen_at), \App\Support\AdminList::localDate($v->last_seen_at),
                    $row['sessions'], $row['pageviews'], $row['orders'], \App\Support\CsvExport::amount($v->revenue ?? 0),
                ];
            }
        })();
        $code = BusinessSetting::current()->currency_code;

        return \App\Support\CsvExport::download('visitors', [
            'Visitor', 'Country', 'Region', 'City', 'Device', 'Browser', 'System', 'First came from', 'Source', 'First visit', 'Last visit', 'Visits', 'Page views', 'Orders', "Sales ({$code})",
        ], $rows);
    }

    /** Forget the ticked visitors: their visits, page views and events. Their orders stay (unlinked). */
    public function visitorsBulk(Request $request): \Illuminate\Http\RedirectResponse
    {
        $bulk = \App\Support\AdminList::bulk($request, ['delete']);
        $n = DB::table('stats_visitors')->whereIn('id', $bulk['ids'])->delete();

        return back()->with('success', \App\Support\AdminList::count($n, 'visitor').' and their visit history deleted.');
    }

    protected function visitorsQuery($r, array $filters)
    {
        $orders = DB::table('orders')->whereNotIn('status', \App\Models\Order::NOT_SALES)->whereNotNull('stats_visitor_id')
            ->selectRaw('stats_visitor_id, COUNT(*) as orders_count, SUM(total) as revenue')->groupBy('stats_visitor_id');

        $query = DB::table('stats_visitors as v')
            ->leftJoinSub($orders, 'o', 'o.stats_visitor_id', '=', 'v.id')
            // Visitors with a visit in the period.
            ->whereExists(fn ($q) => $q->from('stats_sessions as s')->whereColumn('s.visitor_id', 'v.id')->whereBetween('s.local_date', [$r->from->toDateString(), $r->to->toDateString()]))
            ->when($filters['country'], fn ($q, $c) => $q->where('v.country_code', $c))
            ->when($filters['channel'], fn ($q, $c) => $q->where('v.first_channel', $c))
            ->when($filters['search'], function ($q, $term) {
                $number = ltrim($term, '#');
                $q->where(fn ($w) => $w->where('v.uid', 'like', addcslashes($term, '%_\\').'%')
                    ->orWhere('v.city', 'like', '%'.addcslashes($term, '%_\\').'%')
                    ->orWhereIn('v.id', DB::table('orders')->where('number', $number)->orWhere('customer_name', 'like', '%'.addcslashes($term, '%_\\').'%')->whereNotNull('stats_visitor_id')->select('stats_visitor_id')));
            })
            ->orderByDesc('v.last_seen_at')
            ->select('v.*', DB::raw('COALESCE(o.orders_count, 0) as orders_count'), DB::raw('COALESCE(o.revenue, 0) as revenue'));

        return $query;
    }

    public function visitors(Request $request): Response
    {
        $r = $this->report($request);
        $filters = $this->visitorFilters($request);

        $visitors = $this->visitorsQuery($r, $filters)->paginate($filters['per_page'])->withQueryString()->through(fn ($v) => $this->visitorRow($v));

        return Inertia::render('Admin/Analytics/Visitors', [
            ...$this->shared(),
            'summary' => ['period' => $r->period, 'from' => $r->from->toDateString(), 'to' => $r->to->toDateString(), 'label' => $r->label()],
            'visitors' => $visitors,
            'filters' => $filters,
            'countries' => collect($r->countries())->whereNotNull('code')->map(fn ($c) => ['code' => $c['code'], 'name' => $c['name']])->values(),
            'channels' => Channel::CHANNELS,
        ]);
    }

    public function visitor(int $id): Response
    {
        $v = DB::table('stats_visitors')->find($id);
        abort_unless($v, 404);
        $tz = BusinessSetting::current()->timezone;
        $at = fn ($t) => CarbonImmutable::parse($t)->setTimezone($tz);

        $sessions = DB::table('stats_sessions')->where('visitor_id', $id)->orderByDesc('started_at')->limit(50)->get();
        $ids = $sessions->pluck('id');
        $views = DB::table('stats_pageviews')->whereIn('session_id', $ids)->orderBy('created_at')->get(['session_id', 'path', 'title', 'created_at', 'engaged_seconds'])->groupBy('session_id');
        $events = DB::table('stats_events')->whereIn('session_id', $ids)->orderBy('created_at')->get(['session_id', 'name', 'label', 'value', 'created_at'])->groupBy('session_id');

        $orders = DB::table('orders')->where('stats_visitor_id', $id)->orderByDesc('created_at')->get(['id', 'number', 'total', 'status', 'created_at']);

        return Inertia::render('Admin/Analytics/Visitor', [
            ...$this->shared(),
            'visitor' => $this->visitorRow((object) [...(array) $v, 'orders_count' => $orders->count(), 'revenue' => $orders->sum('total')]),
            'orders' => $orders->map(fn ($o) => ['id' => $o->id, 'number' => $o->number, 'total' => Money::format($o->total), 'status' => $o->status, 'date' => $at($o->created_at)->format('j M Y, g:i a')]),
            'sessions' => $sessions->map(fn ($s) => [
                'id' => $s->id,
                'started' => $at($s->started_at)->format('D j M Y, g:i a'),
                'duration' => max(0, (int) CarbonImmutable::parse($s->started_at)->diffInSeconds(CarbonImmutable::parse($s->last_activity_at))),
                'engaged_seconds' => (int) $s->engaged_seconds,
                'pageviews' => (int) $s->pageviews,
                'channel' => $s->channel,
                'source' => $s->source,
                'medium' => $s->medium,
                'campaign' => $s->campaign,
                'referrer' => $s->referrer_url,
                'country' => $s->country_code,
                'country_name' => Shipping::countryName($s->country_code),
                'city' => $s->city,
                'region' => $s->region,
                'device' => $s->device,
                'browser' => $s->browser,
                'os' => $s->os,
                'converted' => (bool) $s->has_conversion,
                'timeline' => collect($views[$s->id] ?? [])->map(fn ($p) => ['type' => 'view', 'time' => $at($p->created_at)->format('g:i:s a'), 'sort' => $p->created_at, 'path' => $p->path, 'title' => $p->title, 'engaged' => (int) $p->engaged_seconds])
                    ->concat(collect($events[$s->id] ?? [])->map(fn ($e) => ['type' => 'event', 'time' => $at($e->created_at)->format('g:i:s a'), 'sort' => $e->created_at, 'name' => $e->name, 'label' => $e->label, 'value' => $e->value !== null ? Money::format($e->value) : null]))
                    ->sortBy('sort')->values()->map(fn ($x) => collect($x)->except('sort')->all()),
            ]),
        ]);
    }

    protected function visitorRow(object $v): array
    {
        $tz = BusinessSetting::current()->timezone;

        return [
            'id' => $v->id,
            'name' => 'Visitor '.strtoupper(substr($v->uid, 0, 6)),
            'country' => $v->country_code,
            'country_name' => Shipping::countryName($v->country_code) ?? 'Unknown',
            'region' => $v->region,
            'city' => $v->city,
            'device' => $v->device,
            'browser' => $v->browser,
            'os' => $v->os,
            'first_channel' => $v->first_channel,
            'first_source' => $v->first_source,
            'first_seen' => CarbonImmutable::parse($v->first_seen_at)->setTimezone($tz)->format('j M Y, g:i a'),
            'last_seen' => CarbonImmutable::parse($v->last_seen_at)->diffForHumans(),
            'online' => CarbonImmutable::parse($v->last_seen_at)->gt(now()->subMinutes(5)),
            'sessions' => (int) $v->sessions_count,
            'pageviews' => (int) $v->pageviews_count,
            'orders' => (int) ($v->orders_count ?? 0),
            'revenue' => (float) ($v->revenue ?? 0) > 0 ? Money::format($v->revenue) : null,
        ];
    }
}
