import { Link, usePoll } from '@inertiajs/react';
import { Laptop, Smartphone, Tablet } from 'lucide-react';
import { AnalyticsShell, Flag, fmt, GeoNote, Panel, RankedList, SERIES } from '@/Components/Admin/Analytics';
import AdminLayout from '@/Layouts/AdminLayout';

const DEVICE_ICONS = { mobile: Smartphone, tablet: Tablet, desktop: Laptop };

/** Page views per minute for the last 30 minutes (thin columns from one baseline, hover for values). */
function MinuteBars({ data }) {
    const max = Math.max(1, ...data);
    return (
        <div>
            <div className="flex h-28 items-end gap-[2px]" role="img" aria-label={`Page views per minute, last 30 minutes: ${data.reduce((a, b) => a + b, 0)} in total.`}>
                {data.map((v, i) => (
                    <div key={i} className="group relative flex h-full flex-1 items-end" title={`${29 - i === 0 ? 'This minute' : `${29 - i} min ago`}: ${v} ${v === 1 ? 'view' : 'views'}`}>
                        <div className="w-full max-w-6 rounded-t-[4px] transition-opacity group-hover:opacity-80" style={{ height: v ? `${Math.max(6, (v / max) * 100)}%` : '2px', background: v ? SERIES.views : '#e4e4e7' }} />
                    </div>
                ))}
            </div>
            <div className="mt-1.5 flex justify-between text-[11px] text-zinc-500">
                <span>30 min ago</span>
                <span>Now</span>
            </div>
        </div>
    );
}

export default function Realtime({ geo, realtime }) {
    // Refresh every 10 seconds while this page is open (and paused when the tab is hidden).
    usePoll(10000, { only: ['realtime'] });
    const r = realtime;

    return (
        <AdminLayout title="Real-time">
            <AnalyticsShell showPeriod={false}>
                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs">
                        <p className="flex items-center gap-2 text-sm font-medium text-zinc-500">
                            <span className="relative flex h-2.5 w-2.5">
                                {r.active5 > 0 && <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />}
                                <span className={`relative inline-flex h-2.5 w-2.5 rounded-full ${r.active5 > 0 ? 'bg-emerald-500' : 'bg-zinc-300'}`} />
                            </span>
                            Visitors right now
                        </p>
                        <p className="mt-2 text-5xl font-bold tracking-tight text-zinc-900 tabular-nums">{fmt(r.active5)}</p>
                        <p className="mt-1 text-xs text-zinc-500">Active in the last 5 minutes · {fmt(r.active30)} in the last 30 minutes</p>
                        <div className="mt-5">
                            <MinuteBars data={r.perMinute} />
                        </div>
                        <p className="mt-3 text-[11px] text-zinc-400">Updates every 10 seconds.</p>
                    </section>

                    <Panel title="Pages being viewed" className="lg:col-span-1">
                        <RankedList rows={r.pages.map((p) => ({ key: p.path, label: p.title || p.path, sub: p.title ? p.path : null, value: p.visitors }))} valueLabel="Visitors" empty="Nobody is on the site right now." />
                    </Panel>

                    <Panel title="Where they are">
                        <RankedList rows={r.countries.map((c) => ({ key: c.code, label: c.name, value: c.visitors, icon: <Flag code={c.code} /> }))} valueLabel="Visitors" empty="Nobody is on the site right now." />
                    </Panel>
                </div>

                <Panel title="Latest page views" description="Last 30 minutes" className="mt-6">
                    {r.recent.length === 0 ? (
                        <p className="py-6 text-center text-sm text-zinc-400">No page views in the last 30 minutes.</p>
                    ) : (
                        <ul className="divide-y divide-zinc-100">
                            {r.recent.map((v, i) => {
                                const Icon = DEVICE_ICONS[v.device] || Laptop;
                                return (
                                    <li key={i} className="flex items-center gap-3 py-2.5 text-sm">
                                        <Flag code={v.country} />
                                        <Link href={`/admin/analytics/visitors/${v.visitor_id}`} className="min-w-0 flex-1 hover:underline">
                                            <span className="block truncate text-zinc-800">{v.title || v.path}</span>
                                            <span className="block truncate text-xs text-zinc-500">
                                                {[v.city, v.country_name].filter(Boolean).join(', ') || 'Unknown location'} · {v.browser} · from {v.source || 'Direct'}
                                            </span>
                                        </Link>
                                        <Icon size={15} className="shrink-0 text-zinc-400" aria-label={v.device} />
                                        <span className="w-14 shrink-0 text-right text-xs text-zinc-500 tabular-nums">{v.ago}</span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Panel>

                <GeoNote geo={geo} />
            </AnalyticsShell>
        </AdminLayout>
    );
}
