import { Link, router } from '@inertiajs/react';
import { Laptop, Search, ShoppingBag, Smartphone, Tablet, Users } from 'lucide-react';
import { useState } from 'react';
import { AnalyticsShell, Flag, fmt, GeoNote } from '@/Components/Admin/Analytics';
import { AdminEmpty, AdminPagination, Badge } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

const DEVICE_ICONS = { mobile: Smartphone, tablet: Tablet, desktop: Laptop };

function Location({ v }) {
    return (
        <span className="flex min-w-0 items-center gap-2">
            <Flag code={v.country} />
            <span className="truncate">{[v.city, v.country_name].filter(Boolean).join(', ')}</span>
        </span>
    );
}

export default function Visitors({ periods, geo, summary, visitors, filters, countries, channels }) {
    const [search, setSearch] = useState(filters.search);
    const apply = (changes) => {
        const params = { ...Object.fromEntries(new URLSearchParams(window.location.search)), ...changes, page: undefined };
        Object.keys(params).forEach((k) => (params[k] === '' || params[k] === undefined) && delete params[k]);
        router.get('/admin/analytics/visitors', params, { preserveState: true, preserveScroll: true, replace: true });
    };
    const filtered = filters.country || filters.channel || filters.search;

    return (
        <AdminLayout title="Visitors">
            <AnalyticsShell title="Visitors" summary={summary} periods={periods}>
                <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                    <div className="flex flex-col gap-3 border-b border-zinc-100 p-4 md:flex-row">
                        <form
                            className="relative flex-1"
                            onSubmit={(e) => {
                                e.preventDefault();
                                apply({ search });
                            }}
                        >
                            <Search size={16} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-400" />
                            <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by city, order number or customer name…" className="input pl-9" aria-label="Search visitors" />
                        </form>
                        <div className="grid grid-cols-2 gap-3 md:flex">
                            <select value={filters.country} onChange={(e) => apply({ country: e.target.value })} className="input md:w-48" aria-label="Filter by country">
                                <option value="">All countries</option>
                                {countries.map((c) => (
                                    <option key={c.code} value={c.code}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                            <select value={filters.channel} onChange={(e) => apply({ channel: e.target.value })} className="input md:w-44" aria-label="Filter by first source">
                                <option value="">All sources</option>
                                {channels.map((c) => (
                                    <option key={c} value={c}>
                                        {c}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {visitors.data.length === 0 ? (
                        <AdminEmpty icon={Users} title={filtered ? 'No visitors match' : 'No visitors in this period'} message={filtered ? 'Try a different filter or period.' : 'Visitors appear here as soon as people browse your website.'} />
                    ) : (
                        <>
                            <table className="hidden w-full text-left text-sm lg:table">
                                <thead className="border-b border-zinc-100 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Visitor</th>
                                        <th className="px-4 py-3">Location</th>
                                        <th className="px-4 py-3">Device</th>
                                        <th className="px-4 py-3">First came from</th>
                                        <th className="px-4 py-3 text-right">Visits</th>
                                        <th className="px-4 py-3 text-right">Views</th>
                                        <th className="px-4 py-3">Last seen</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-100">
                                    {visitors.data.map((v) => {
                                        const Icon = DEVICE_ICONS[v.device] || Laptop;
                                        return (
                                            <tr key={v.id} className="cursor-pointer hover:bg-zinc-50/60" onClick={() => router.visit(`/admin/analytics/visitors/${v.id}`)}>
                                                <td className="px-4 py-3">
                                                    <Link href={`/admin/analytics/visitors/${v.id}`} className="flex items-center gap-2 font-medium text-zinc-900 hover:underline" onClick={(e) => e.stopPropagation()}>
                                                        {v.online && <span className="h-2 w-2 rounded-full bg-emerald-500" title="Online now" />}
                                                        {v.name}
                                                    </Link>
                                                    {v.orders > 0 && (
                                                        <span className="mt-1 inline-flex items-center gap-1 text-xs text-emerald-700">
                                                            <ShoppingBag size={12} /> {v.orders} {v.orders === 1 ? 'order' : 'orders'} · {v.revenue}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="max-w-56 px-4 py-3 text-zinc-700">
                                                    <Location v={v} />
                                                </td>
                                                <td className="px-4 py-3 text-zinc-600">
                                                    <span className="flex items-center gap-1.5">
                                                        <Icon size={15} className="text-zinc-400" aria-label={v.device} /> {v.browser} · {v.os}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-zinc-600">
                                                    {v.first_source || 'Direct'}
                                                    {v.first_channel && v.first_source !== v.first_channel && <span className="block text-xs text-zinc-400">{v.first_channel}</span>}
                                                </td>
                                                <td className="px-4 py-3 text-right text-zinc-900 tabular-nums">{fmt(v.sessions)}</td>
                                                <td className="px-4 py-3 text-right text-zinc-900 tabular-nums">{fmt(v.pageviews)}</td>
                                                <td className="px-4 py-3 text-zinc-600">{v.online ? <Badge color="green">Online now</Badge> : v.last_seen}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>

                            <ul className="divide-y divide-zinc-100 lg:hidden">
                                {visitors.data.map((v) => (
                                    <li key={v.id}>
                                        <Link href={`/admin/analytics/visitors/${v.id}`} className="block p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                                                    {v.online && <span className="h-2 w-2 rounded-full bg-emerald-500" />}
                                                    {v.name}
                                                </span>
                                                <span className="text-xs text-zinc-500">{v.online ? 'Online now' : v.last_seen}</span>
                                            </div>
                                            <div className="mt-1 text-xs text-zinc-600">
                                                <Location v={v} />
                                            </div>
                                            <p className="mt-1 text-xs text-zinc-500">
                                                {v.sessions} visits · {v.pageviews} views · {v.browser} on {v.device} · from {v.first_source || 'Direct'}
                                                {v.orders > 0 && ` · ${v.orders} orders`}
                                            </p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            <AdminPagination paginator={visitors} />
                        </>
                    )}
                </div>
                <GeoNote geo={geo} />
            </AnalyticsShell>
        </AdminLayout>
    );
}
