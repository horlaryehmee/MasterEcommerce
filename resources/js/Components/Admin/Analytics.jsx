import { Link, router, usePage } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, CalendarRange, Info, Minus, Table2, LineChart } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { PageHeader } from '@/Components/Admin/ui';

/* Chart palette: reference categorical slots 1–2 (validated pair), recessive grid. */
export const SERIES = { views: '#2a78d6', visitors: '#eb6834' };
const GRID = '#e4e4e7';

// Flag SVGs (country-flag-icons, MIT) as separate small files, loaded only when shown.
const FLAGS = import.meta.glob('../../../../node_modules/country-flag-icons/3x2/*.svg', { eager: true, query: '?url', import: 'default' });
const flagUrl = (code) => (code ? FLAGS[`../../../../node_modules/country-flag-icons/3x2/${code.toUpperCase()}.svg`] : null);

export function Flag({ code, className = 'h-3.5 w-5' }) {
    const url = flagUrl(code);
    if (!url) return <span className={`inline-block shrink-0 rounded-[2px] bg-zinc-200 ${className}`} aria-hidden />;
    return <img src={url} alt="" className={`inline-block shrink-0 rounded-[2px] object-cover ring-1 ring-zinc-900/10 ${className}`} loading="lazy" />;
}

export const fmt = (n) => (n ?? 0).toLocaleString();
export function fmtDuration(seconds) {
    const s = Math.round(seconds || 0);
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m ${String(s % 60).padStart(2, '0')}s`;
    return `${Math.floor(m / 60)}h ${String(m % 60).padStart(2, '0')}m`;
}

const TABS = [
    ['/admin/analytics', 'Overview'],
    ['/admin/analytics/realtime', 'Real-time'],
    ['/admin/analytics/visitors', 'Visitors'],
];

/** Header, tabs and the period picker shared by the Analytics pages. */
export function AnalyticsShell({ title = 'Analytics', summary, periods, children, showPeriod = true, actions }) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const [custom, setCustom] = useState(summary?.period === 'custom');
    const [range, setRange] = useState({ from: summary?.from ?? '', to: summary?.to ?? '' });

    const go = (params) => router.get(path, { ...Object.fromEntries(new URLSearchParams(url.split('?')[1] || '')), page: undefined, ...params }, { preserveScroll: true, preserveState: true, replace: true });

    return (
        <>
            <PageHeader title={title} description="Your own first-party visitor statistics. Bots and your own visits are excluded." actions={actions} />
            <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <nav className="flex gap-1 rounded-xl bg-zinc-100 p-1 max-sm:[&>a]:flex-1 max-sm:[&>a]:text-center" aria-label="Analytics sections">
                    {TABS.map(([href, label]) => {
                        const on = path === href || (href !== '/admin/analytics' && path.startsWith(href));
                        return (
                            <Link key={href} href={href} className={`rounded-lg px-3.5 py-2 text-sm font-medium transition active:scale-95 sm:py-1.5 ${on ? 'bg-white text-zinc-900 shadow-xs' : 'text-zinc-600 hover:text-zinc-900'}`} aria-current={on ? 'page' : undefined}>
                                {label}
                            </Link>
                        );
                    })}
                </nav>
                {showPeriod && periods && (
                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            value={custom ? 'custom' : summary.period}
                            onChange={(e) => {
                                if (e.target.value === 'custom') return setCustom(true);
                                setCustom(false);
                                go({ period: e.target.value, from: undefined, to: undefined });
                            }}
                            className="input h-11 flex-1 py-0 sm:h-9 sm:w-auto sm:flex-none sm:text-sm"
                            aria-label="Date range"
                        >
                            {Object.entries(periods).map(([k, l]) => (
                                <option key={k} value={k}>
                                    {l}
                                </option>
                            ))}
                            <option value="custom">Custom range…</option>
                        </select>
                        {custom && (
                            <form
                                className="flex items-center gap-1.5"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    go({ period: 'custom', ...range });
                                }}
                            >
                                <CalendarRange size={16} className="text-zinc-400" />
                                <input type="date" className="input h-11 flex-1 py-0 sm:h-9 sm:w-auto sm:flex-none sm:text-sm" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} aria-label="From" required />
                                <span className="text-zinc-400">–</span>
                                <input type="date" className="input h-11 flex-1 py-0 sm:h-9 sm:w-auto sm:flex-none sm:text-sm" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} aria-label="To" required />
                                <button className="h-9 rounded-lg bg-zinc-900 px-3 text-sm font-semibold text-white">Apply</button>
                            </form>
                        )}
                    </div>
                )}
            </div>
            {children}
        </>
    );
}

/** % change vs the previous period; for "lower is better" metrics the colours flip. */
export function Change({ value, invert = false }) {
    if (value === null || value === undefined) return <span className="text-xs text-zinc-400">No earlier data</span>;
    if (value === 0)
        return (
            <span className="inline-flex items-center gap-0.5 text-xs font-medium text-zinc-500">
                <Minus size={13} /> 0%
            </span>
        );
    const up = value > 0;
    const good = invert ? !up : up;
    const Icon = up ? ArrowUpRight : ArrowDownRight;
    return (
        <span className={`inline-flex items-center gap-0.5 text-xs font-semibold ${good ? 'text-emerald-700' : 'text-red-700'}`} title="Compared with the previous period of the same length">
            <Icon size={14} aria-hidden />
            {Math.abs(value)}%<span className="sr-only">{up ? ' increase' : ' decrease'}</span>
        </span>
    );
}

export function Kpi({ label, value, change, invert, hint }) {
    return (
        <div className="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs">
            <p className="flex items-center gap-1 text-[13px] font-medium text-zinc-500">
                {label}
                {hint && (
                    <span title={hint} className="text-zinc-400">
                        <Info size={13} />
                    </span>
                )}
            </p>
            <p className="mt-1.5 text-2xl font-bold tracking-tight text-zinc-900 tabular-nums">{value}</p>
            <div className="mt-1">
                <Change value={change} invert={invert} />
            </div>
        </div>
    );
}

function niceMax(v) {
    if (v <= 5) return 5;
    const pow = 10 ** Math.floor(Math.log10(v));
    const n = v / pow;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * pow;
}
const compact = (n) => (n >= 10000 ? `${Math.round(n / 1000)}K` : n.toLocaleString());

/** Views & visitors over time: two lines on one count axis, crosshair tooltip, table view. */
export function TrendChart({ series }) {
    const wrap = useRef(null);
    const [width, setWidth] = useState(800);
    const [hover, setHover] = useState(null);
    const [asTable, setAsTable] = useState(false);
    const points = series.points;

    useEffect(() => {
        const el = wrap.current;
        if (!el) return;
        const ro = new ResizeObserver(([e]) => setWidth(Math.max(280, e.contentRect.width)));
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const H = 240;
    const pad = { top: 12, right: 12, bottom: 28, left: 44 };
    const w = width - pad.left - pad.right;
    const h = H - pad.top - pad.bottom;
    const max = niceMax(Math.max(1, ...points.map((p) => Math.max(p.views, p.visitors))));
    const x = (i) => pad.left + (points.length <= 1 ? w / 2 : (i / (points.length - 1)) * w);
    const y = (v) => pad.top + h - (v / max) * h;
    const line = (key) => points.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(p[key]).toFixed(1)}`).join(' ');
    const area = `${line('views')} L${x(points.length - 1)},${y(0)} L${x(0)},${y(0)} Z`;
    const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => Math.round(max * t));
    const labelEvery = Math.max(1, Math.ceil(points.length / Math.max(2, Math.floor(w / 70))));

    const onMove = (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const px = e.clientX - rect.left - pad.left;
        const i = points.length <= 1 ? 0 : Math.round((px / w) * (points.length - 1));
        setHover(Math.min(points.length - 1, Math.max(0, i)));
    };

    const totals = useMemo(() => ({ views: points.reduce((n, p) => n + p.views, 0) }), [points]);
    const hp = hover !== null ? points[hover] : null;

    return (
        <div>
            <div className="mb-3 flex items-center justify-between gap-3">
                <div className="flex items-center gap-4 text-sm text-zinc-600" aria-label="Legend">
                    <span className="flex items-center gap-1.5">
                        <span className="h-0.5 w-4 rounded-full" style={{ background: SERIES.views }} /> Views
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-0.5 w-4 rounded-full" style={{ background: SERIES.visitors }} /> Visitors
                    </span>
                </div>
                <button type="button" onClick={() => setAsTable((t) => !t)} className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900">
                    {asTable ? <LineChart size={14} /> : <Table2 size={14} />} {asTable ? 'Chart' : 'Table'}
                </button>
            </div>

            {asTable ? (
                <div className="max-h-72 overflow-y-auto rounded-lg border border-zinc-100">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-zinc-50 text-left text-xs text-zinc-500">
                            <tr>
                                <th className="px-3 py-2 font-medium">{series.unit === 'hour' ? 'Hour' : series.unit === 'month' ? 'Month' : 'Day'}</th>
                                <th className="px-3 py-2 text-right font-medium">Views</th>
                                <th className="px-3 py-2 text-right font-medium">Visitors</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100">
                            {points.map((p) => (
                                <tr key={p.key}>
                                    <td className="px-3 py-1.5 text-zinc-700">{p.label}</td>
                                    <td className="px-3 py-1.5 text-right text-zinc-900 tabular-nums">{fmt(p.views)}</td>
                                    <td className="px-3 py-1.5 text-right text-zinc-900 tabular-nums">{fmt(p.visitors)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div ref={wrap} className="relative">
                    <svg width={width} height={H} role="img" aria-label={`Views and visitors per ${series.unit}. ${fmt(totals.views)} views in total.`} onMouseMove={onMove} onMouseLeave={() => setHover(null)} className="block touch-none select-none">
                        {ticks.map((t) => (
                            <g key={t}>
                                <line x1={pad.left} x2={width - pad.right} y1={y(t)} y2={y(t)} stroke={GRID} strokeWidth="1" />
                                <text x={pad.left - 8} y={y(t)} dy="0.32em" textAnchor="end" className="fill-zinc-500 text-[11px] tabular-nums">
                                    {compact(t)}
                                </text>
                            </g>
                        ))}
                        {points.map((p, i) =>
                            i % labelEvery === 0 || i === points.length - 1 ? (
                                <text key={p.key} x={x(i)} y={H - 8} textAnchor={i === 0 ? 'start' : i === points.length - 1 ? 'end' : 'middle'} className="fill-zinc-500 text-[11px]">
                                    {p.label}
                                </text>
                            ) : null,
                        )}
                        <path d={area} fill={SERIES.views} opacity="0.1" />
                        <path d={line('views')} fill="none" stroke={SERIES.views} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
                        <path d={line('visitors')} fill="none" stroke={SERIES.visitors} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
                        {points.length === 1 && ['views', 'visitors'].map((k) => <circle key={k} cx={x(0)} cy={y(points[0][k])} r="4" fill={SERIES[k]} stroke="#fff" strokeWidth="2" />)}
                        {hp && (
                            <g pointerEvents="none">
                                <line x1={x(hover)} x2={x(hover)} y1={pad.top} y2={pad.top + h} stroke="#a1a1aa" strokeWidth="1" />
                                <circle cx={x(hover)} cy={y(hp.views)} r="4.5" fill={SERIES.views} stroke="#fff" strokeWidth="2" />
                                <circle cx={x(hover)} cy={y(hp.visitors)} r="4.5" fill={SERIES.visitors} stroke="#fff" strokeWidth="2" />
                            </g>
                        )}
                    </svg>
                    {hp && (
                        <div className="pointer-events-none absolute top-2 z-10 min-w-36 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs shadow-lg" style={{ left: Math.min(Math.max(x(hover) + 12, 0), width - 160) }}>
                            <p className="font-semibold text-zinc-900">{hp.label}</p>
                            <p className="mt-1 flex items-center justify-between gap-4 text-zinc-600">
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2 w-2 rounded-full" style={{ background: SERIES.views }} />
                                    Views
                                </span>
                                <span className="font-semibold text-zinc-900 tabular-nums">{fmt(hp.views)}</span>
                            </p>
                            <p className="mt-0.5 flex items-center justify-between gap-4 text-zinc-600">
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2 w-2 rounded-full" style={{ background: SERIES.visitors }} />
                                    Visitors
                                </span>
                                <span className="font-semibold text-zinc-900 tabular-nums">{fmt(hp.visitors)}</span>
                            </p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export function Panel({ title, description, children, className = '', action }) {
    return (
        <section className={`rounded-2xl border border-zinc-200 bg-white shadow-xs ${className}`}>
            <div className="flex flex-wrap items-start justify-between gap-x-3 gap-y-2 border-b border-zinc-100 px-4 py-3.5 sm:px-5">
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold text-zinc-900">{title}</h2>
                    {description && <p className="mt-0.5 text-xs text-zinc-500">{description}</p>}
                </div>
                {action}
            </div>
            <div className="px-4 py-3 sm:px-5">{children}</div>
        </section>
    );
}

/**
 * Ranked list with a thin magnitude bar under each row (single hue).
 * rows: [{ key, label, sub?, value, icon? }]
 */
export function RankedList({ rows, valueLabel, empty = 'No data yet for this period.', limit = 10 }) {
    const [all, setAll] = useState(false);
    if (!rows.length) return <p className="py-6 text-center text-sm text-zinc-400">{empty}</p>;
    const max = Math.max(...rows.map((r) => r.value), 1);
    const shown = all ? rows : rows.slice(0, limit);
    return (
        <>
            <div className="mb-1 flex justify-end text-[11px] font-medium tracking-wide text-zinc-400 uppercase">{valueLabel}</div>
            <ul className="space-y-2.5">
                {shown.map((r) => (
                    <li key={r.key}>
                        <div className="flex items-center justify-between gap-3 text-sm">
                            <span className="flex min-w-0 items-center gap-2">
                                {r.icon}
                                <span className="min-w-0">
                                    <span className="block truncate text-zinc-800" title={r.title || r.label}>
                                        {r.label}
                                    </span>
                                    {r.sub && <span className="block truncate text-xs text-zinc-400">{r.sub}</span>}
                                </span>
                            </span>
                            <span className="shrink-0 font-semibold text-zinc-900 tabular-nums">{fmt(r.value)}</span>
                        </div>
                        <div className="mt-1 h-1 overflow-hidden rounded-full bg-zinc-100">
                            <div className="h-full rounded-full" style={{ width: `${(r.value / max) * 100}%`, background: SERIES.views }} />
                        </div>
                    </li>
                ))}
            </ul>
            {rows.length > limit && (
                <button type="button" onClick={() => setAll((a) => !a)} className="mt-3 text-xs font-medium text-zinc-500 hover:text-zinc-900">
                    {all ? 'Show less' : `Show all ${rows.length}`}
                </button>
            )}
        </>
    );
}

/** Shop funnel as horizontal bars from a common baseline. */
export function Funnel({ steps }) {
    const max = Math.max(steps[0]?.sessions || 0, 1);
    return (
        <ul className="space-y-3">
            {steps.map((s, i) => (
                <li key={s.key}>
                    <div className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="text-zinc-700">{s.label}</span>
                        <span className="text-zinc-900 tabular-nums">
                            <strong className="font-semibold">{fmt(s.sessions)}</strong>
                            {i > 0 && <span className="ml-1.5 text-xs text-zinc-500">{s.rate}%</span>}
                        </span>
                    </div>
                    <div className="mt-1 h-3 overflow-hidden rounded-r bg-zinc-100">
                        <div className="h-full rounded-r" style={{ width: `${(s.sessions / max) * 100}%`, background: SERIES.views, opacity: 1 - i * 0.15 }} />
                    </div>
                </li>
            ))}
        </ul>
    );
}

export function GeoNote({ geo }) {
    return (
        <p className="mt-6 text-center text-xs text-zinc-400">
            {geo.level ? (
                <>
                    Locations from the free DB-IP {geo.level} database (updated {geo.updated}).{' '}
                    <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer" className="underline">
                        IP Geolocation by DB-IP
                    </a>
                    .
                </>
            ) : (
                <>
                    Visitor countries need the free geolocation database: run <code className="rounded bg-zinc-100 px-1">php artisan stats:geoip-update</code> once (it then refreshes monthly), or put the site behind Cloudflare.
                </>
            )}
        </p>
    );
}
