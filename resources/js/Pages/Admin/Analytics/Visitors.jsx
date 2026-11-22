import { Link, router } from '@inertiajs/react';
import { Laptop, ShoppingBag, Smartphone, Tablet, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { BulkBar, BulkDialog, SelectAll, SelectRow, plural, useBulkAction, useSelection } from '@/Components/Admin/Bulk';
import { FilterSelect, ListToolbar, activeFilters, useList } from '@/Components/Admin/ListToolbar';
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
    const list = useList('/admin/analytics/visitors', filters, { per_page: 25 }, ['period', 'from', 'to']);
    const active = activeFilters(filters, { country: countries.map((c) => ({ value: c.code, label: c.name })), channel: (v) => `From ${v}` });
    const filtered = filters.country || filters.channel || filters.search;

    const sel = useSelection(visitors.data);
    const bulk = useBulkAction('/admin/analytics/visitors/bulk', sel);
    const [dialog, setDialog] = useState(null);
    const n = plural(sel.count, 'visitor');
    const bulkActions = [
        {
            label: 'Delete',
            icon: Trash2,
            danger: true,
            onClick: () => setDialog({ action: 'delete', title: `Delete ${n}?`, danger: true, confirmLabel: `Delete ${n}`, message: `The visit history of ${n} (visits, page views and events) will be permanently deleted, and your statistics will no longer count them. Their orders are kept.` }),
        },
    ];

    return (
        <AdminLayout title="Visitors">
            <AnalyticsShell title="Visitors" summary={summary} periods={periods}>
                <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                    <ListToolbar list={list} placeholder="Search by city, order number or customer name…" exportUrl="/admin/analytics/visitors/export" active={active}>
                        <FilterSelect list={list} name="country" label="Country" options={countries.map((c) => ({ value: c.code, label: c.name }))} placeholder="All countries" />
                        <FilterSelect list={list} name="channel" label="First came from" options={channels.map((c) => ({ value: c, label: c }))} placeholder="All sources" />
                    </ListToolbar>

                    {visitors.data.length === 0 ? (
                        <AdminEmpty icon={Users} title={filtered ? 'No visitors match' : 'No visitors in this period'} message={filtered ? 'Try a different filter or period.' : 'Visitors appear here as soon as people browse your website.'} />
                    ) : (
                        <>
                            <table className="hidden w-full text-left text-sm lg:table">
                                <thead className="border-b border-zinc-100 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
                                    <tr>
                                        <th className="w-10 py-3 pl-4">
                                            <SelectAll selection={sel} />
                                        </th>
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
                                            <tr key={v.id} className={`cursor-pointer ${sel.has(v.id) ? 'bg-zinc-50' : 'hover:bg-zinc-50/60'}`} onClick={() => router.visit(`/admin/analytics/visitors/${v.id}`)}>
                                                <td className="py-3 pl-4">
                                                    <SelectRow selection={sel} id={v.id} label={`Select ${v.name}`} />
                                                </td>
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

                            <div className="flex items-center gap-3 border-b border-zinc-100 px-4 py-2.5 lg:hidden">
                                <SelectAll selection={sel} />
                                <span className="text-xs font-medium text-zinc-500">{sel.count ? `${sel.count} selected` : 'Select all'}</span>
                            </div>
                            <ul className="divide-y divide-zinc-100 lg:hidden">
                                {visitors.data.map((v) => (
                                    <li key={v.id} className={`flex items-start pl-4 ${sel.has(v.id) ? 'bg-zinc-50' : ''}`}>
                                        <SelectRow selection={sel} id={v.id} label={`Select ${v.name}`} className="mt-3.5" />
                                        <Link href={`/admin/analytics/visitors/${v.id}`} className="block min-w-0 flex-1 py-4 pr-4 pl-3">
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
                            <BulkBar selection={sel} noun="visitor" actions={bulkActions} exportUrl="/admin/analytics/visitors/export" exportQuery={list.query()} processing={bulk.processing} />
                            <AdminPagination paginator={visitors} list={list} />
                        </>
                    )}
                </div>
                <GeoNote geo={geo} />
            <BulkDialog
                    open={!!dialog}
                    onClose={() => setDialog(null)}
                    onConfirm={(value) => bulk.run(dialog.action, value, { onSuccess: () => setDialog(null) })}
                    processing={bulk.processing}
                    error={bulk.errors.value || bulk.errors.ids}
                    {...dialog}
                />
            </AnalyticsShell>
        </AdminLayout>
    );
}
