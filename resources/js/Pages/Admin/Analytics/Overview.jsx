import { Link } from '@inertiajs/react';
import { Laptop, MousePointerClick, Smartphone, Tablet } from 'lucide-react';
import { useState } from 'react';
import { AnalyticsShell, Flag, fmt, fmtDuration, Funnel, GeoNote, Kpi, Panel, RankedList, TrendChart } from '@/Components/Admin/Analytics';
import AdminLayout from '@/Layouts/AdminLayout';

const DEVICE_ICONS = { mobile: Smartphone, tablet: Tablet, desktop: Laptop };
const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);

function Tabs({ tabs, active, onChange }) {
    return (
        <div className="flex flex-wrap gap-1" role="tablist">
            {tabs.map(([k, l]) => (
                <button key={k} type="button" role="tab" aria-selected={active === k} onClick={() => onChange(k)} className={`rounded-md px-2 py-1 text-xs font-medium ${active === k ? 'bg-zinc-900 text-white' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900'}`}>
                    {l}
                </button>
            ))}
        </div>
    );
}

export default function Overview({ periods, geo, enabled, summary, realtime, pages, entryPages, channels, referrers, campaigns, countries, cities, devices, browsers, os, searches, clicks, products, funnel }) {
    const t = summary.totals;
    const c = summary.change;
    const [pageTab, setPageTab] = useState('pages');
    const [sourceTab, setSourceTab] = useState('channels');
    const [geoTab, setGeoTab] = useState('countries');
    const [techTab, setTechTab] = useState('devices');
    const hasShop = funnel.some((s) => s.key !== 'visit' && s.sessions > 0) || t.orders > 0;

    const pageRows = (pageTab === 'pages' ? pages : entryPages).map((p) => ({ key: p.path, label: p.title || p.path, sub: p.title ? p.path : null, value: pageTab === 'pages' ? p.views : p.sessions }));
    const sourceRows = { channels, referrers, campaigns }[sourceTab].map((r) => ({ key: r.name, label: r.name, value: r.sessions }));
    const geoRows = geoTab === 'countries' ? countries.map((r) => ({ key: r.code || 'unknown', label: r.name, value: r.visitors, icon: <Flag code={r.code} />, sub: `${r.share}% of visitors` })) : cities.map((r) => ({ key: `${r.name}-${r.country}`, label: r.name, value: r.visitors, icon: <Flag code={r.country} /> }));
    const techRows = { devices, browsers, os }[techTab].map((r) => {
        const Icon = DEVICE_ICONS[r.name];
        return { key: r.name, label: techTab === 'devices' ? cap(r.name) : r.name, value: r.sessions, sub: `${r.share}% of visits`, icon: Icon ? <Icon size={16} className="text-zinc-400" /> : null };
    });

    return (
        <AdminLayout title="Analytics">
            <AnalyticsShell
                summary={summary}
                periods={periods}
                actions={
                    <Link href="/admin/analytics/realtime" className="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                        <span className="relative flex h-2.5 w-2.5">
                            {realtime.active5 > 0 && <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />}
                            <span className={`relative inline-flex h-2.5 w-2.5 rounded-full ${realtime.active5 > 0 ? 'bg-emerald-500' : 'bg-zinc-300'}`} />
                        </span>
                        {fmt(realtime.active5)} online now
                    </Link>
                }
            >
                {!enabled && <p className="mb-6 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">Visitor statistics are switched off. Turn them on in Website Settings → Tracking & ads.</p>}

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <Kpi label="Visitors" value={fmt(t.visitors)} change={c.visitors} hint="Unique people who visited in this period." />
                    <Kpi label="Views" value={fmt(t.pageviews)} change={c.pageviews} hint="Pages viewed." />
                    <Kpi label="Visits" value={fmt(t.sessions)} change={c.sessions} hint="Sessions: a visit ends after 30 minutes without activity." />
                    <Kpi label="Views per visit" value={t.views_per_session} change={c.views_per_session} />
                    <Kpi label="Avg. engagement time" value={fmtDuration(t.avg_engagement_seconds)} change={c.avg_engagement_seconds} hint="Time the site was actually on screen, per visitor (Google Analytics 4 definition)." />
                    <Kpi label="Bounce rate" value={`${t.bounce_rate}%`} change={c.bounce_rate} invert hint="Visits shorter than 10 seconds with one page and no order (Google Analytics 4 definition). Lower is better." />
                </div>

                <Panel title={`Views and visitors · ${summary.label}`} className="mt-6">
                    <div className="py-2">
                        <TrendChart series={summary.series} />
                    </div>
                    <p className="pb-1 text-xs text-zinc-500">
                        {fmt(t.new_visitors)} new and {fmt(t.returning_visitors)} returning visitors.
                    </p>
                </Panel>

                <div className="mt-6 grid gap-6 lg:grid-cols-2">
                    <Panel title="Pages" action={<Tabs tabs={[['pages', 'Top pages'], ['entry', 'Landing pages']]} active={pageTab} onChange={setPageTab} />}>
                        <RankedList rows={pageRows} valueLabel={pageTab === 'pages' ? 'Views' : 'Visits'} />
                    </Panel>
                    <Panel title="Traffic sources" action={<Tabs tabs={[['channels', 'Channels'], ['referrers', 'Referrers'], ['campaigns', 'Campaigns']]} active={sourceTab} onChange={setSourceTab} />}>
                        <RankedList rows={sourceRows} valueLabel="Visits" empty={sourceTab === 'campaigns' ? 'No campaign visits. Add ?utm_campaign=… to links in your ads and posts.' : undefined} />
                    </Panel>
                    <Panel title="Locations" action={<Tabs tabs={[['countries', 'Countries'], ['cities', 'Cities']]} active={geoTab} onChange={setGeoTab} />}>
                        <RankedList rows={geoRows} valueLabel="Visitors" empty={geoTab === 'cities' && geo.level !== 'city' ? 'City data needs the city database: php artisan stats:geoip-update --city' : undefined} />
                    </Panel>
                    <Panel title="Technology" action={<Tabs tabs={[['devices', 'Devices'], ['browsers', 'Browsers'], ['os', 'Systems']]} active={techTab} onChange={setTechTab} />}>
                        <RankedList rows={techRows} valueLabel="Visits" />
                    </Panel>
                    <Panel title="Clicks to outside links" description="WhatsApp, phone, email and other websites.">
                        <RankedList rows={clicks.map((r) => ({ key: r.name, label: r.name, value: r.count, sub: `${fmt(r.visitors)} visitors`, icon: <MousePointerClick size={15} className="text-zinc-400" /> }))} valueLabel="Clicks" />
                    </Panel>
                    <Panel title="Site searches" description="What visitors searched for in your shop.">
                        <RankedList rows={searches.map((r) => ({ key: r.name, label: r.name, value: r.count, sub: `${fmt(r.visitors)} visitors` }))} valueLabel="Searches" />
                    </Panel>
                    <Panel title="Most viewed products">
                        <RankedList rows={products.map((r) => ({ key: r.name, label: r.name, value: r.count, sub: `${fmt(r.visitors)} visitors` }))} valueLabel="Views" />
                    </Panel>
                    <Panel title="Shop funnel" description={hasShop ? `${fmt(t.orders)} orders · ${t.revenue_formatted} · ${t.conversion_rate}% of visits ordered` : 'Visits that reached each step.'}>
                        <Funnel steps={funnel} />
                    </Panel>
                </div>

                <GeoNote geo={geo} />
            </AnalyticsShell>
        </AdminLayout>
    );
}
