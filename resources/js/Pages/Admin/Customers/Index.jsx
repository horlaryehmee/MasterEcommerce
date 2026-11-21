import { Link, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { FilterDates, FilterNumber, FilterSelect, ListToolbar, activeFilters, useList } from '@/Components/Admin/ListToolbar';
import { AdminEmpty, AdminPagination, Badge, Button, FilterChips, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

const CONTACT = { email: 'Has an email', no_email: 'No email' };

function Avatar({ name, returning }) {
    return (
        <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${returning ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600'}`} aria-hidden>
            {(name || '?')
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w.charAt(0).toUpperCase())
                .join('')}
        </span>
    );
}

export default function CustomersIndex({ customers, filters, segments, sorts, counts, states }) {
    const list = useList('/admin/customers', filters, { sort: 'recent', per_page: 25 });
    const active = activeFilters(filters, {
        state: states,
        contact: CONTACT,
        min_spent: (v) => `Spent at least ${Number(v).toLocaleString()}`,
        from: (v) => `Ordered from ${v}`,
        to: (v) => `Ordered until ${v}`,
    });
    const filtered = filters.search || filters.segment || active.length > 0;
    const tabs = [['', 'All', counts['']], ...Object.entries(segments).map(([k, l]) => [k, l, counts[k] ?? 0])];
    const open = (c) => router.visit(`/admin/customers/${c.key}`);

    return (
        <AdminLayout title="Customers">
            <PageHeader title="Customers" description="Everyone who has ordered from your website, grouped by phone number. Name, email and address come from their latest order." />

            <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                <div className="border-b border-zinc-100 px-4 pt-4 pb-3">
                    <FilterChips label="Customer type" value={filters.segment} onChange={(key) => list.apply({ segment: key })} options={tabs} />
                </div>
                <ListToolbar list={list} placeholder="Name, phone, email or order number…" sorts={sorts} exportUrl="/admin/customers/export" active={active}>
                    {states.length > 0 && <FilterSelect list={list} name="state" label="State" options={states} placeholder="All states" />}
                    <FilterSelect list={list} name="contact" label="Email" options={CONTACT} />
                    <FilterNumber list={list} name="min_spent" label="Spent at least" placeholder="0" />
                    <FilterDates list={list} label="Ordered between" />
                </ListToolbar>

                {customers.data.length === 0 ? (
                    <AdminEmpty
                        icon={Users}
                        title={filtered ? 'No customers match' : 'No customers yet'}
                        message={filtered ? 'Try a different search or clear the filters.' : 'When people order from your website, they appear here with their order history.'}
                        action={
                            filtered && (
                                <Button variant="secondary" type="button" onClick={list.clear}>
                                    Clear filters
                                </Button>
                            )
                        }
                    />
                ) : (
                    <>
                        <table className="hidden w-full text-left text-sm md:table">
                            <thead className="border-b border-zinc-100 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
                                <tr>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Location</th>
                                    <th className="px-4 py-3 text-right">Orders</th>
                                    <th className="px-4 py-3 text-right">Total spent</th>
                                    <th className="px-4 py-3">Last order</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100">
                                {customers.data.map((c) => (
                                    <tr key={c.key} className="cursor-pointer hover:bg-zinc-50/60" onClick={() => open(c)}>
                                        <td className="px-4 py-3">
                                            <span className="flex items-center gap-3">
                                                <Avatar name={c.name} returning={c.orders_count > 1} />
                                                <span className="min-w-0">
                                                    <Link href={`/admin/customers/${c.key}`} className="block max-w-xs truncate font-semibold text-zinc-900 hover:underline" onClick={(e) => e.stopPropagation()}>
                                                        {c.name}
                                                    </Link>
                                                    <span className="block max-w-xs truncate text-xs text-zinc-500">{[c.phone, c.email].filter(Boolean).join(' · ')}</span>
                                                </span>
                                            </span>
                                        </td>
                                        <td className="max-w-[14rem] truncate px-4 py-3 text-zinc-600">{c.location || '—'}</td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <span className="font-medium text-zinc-900">{c.orders_count}</span>
                                            {c.orders_count > 1 && (
                                                <span className="ml-2">
                                                    <Badge color="green">Returning</Badge>
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <span className="font-medium text-zinc-900">{c.spent}</span>
                                            {c.average && c.orders_count > 1 && <span className="block text-xs text-zinc-500">avg. {c.average}</span>}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className="block text-zinc-700">{c.last_order_ago}</span>
                                            <span className="block text-xs text-zinc-500">{c.last_order}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <ul className="divide-y divide-zinc-100 md:hidden">
                            {customers.data.map((c) => (
                                <li key={c.key}>
                                    <Link href={`/admin/customers/${c.key}`} className="flex items-center gap-3.5 px-4 py-3.5 transition active:bg-zinc-50">
                                        <Avatar name={c.name} returning={c.orders_count > 1} />
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-baseline justify-between gap-2">
                                                <span className="truncate text-[15px] font-semibold text-zinc-900">{c.name}</span>
                                                <span className="shrink-0 text-[15px] font-semibold text-zinc-900">{c.spent}</span>
                                            </span>
                                            <span className="mt-0.5 flex items-baseline justify-between gap-2 text-xs text-zinc-500">
                                                <span className="truncate">{c.phone}</span>
                                                <span className="shrink-0">
                                                    {c.orders_count} {c.orders_count === 1 ? 'order' : 'orders'}
                                                </span>
                                            </span>
                                            <span className="mt-0.5 block truncate text-xs text-zinc-500">
                                                {[c.location, `last order ${c.last_order_ago}`].filter(Boolean).join(' · ')}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <AdminPagination paginator={customers} list={list} />
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
