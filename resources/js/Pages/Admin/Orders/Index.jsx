import { Link, router } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import { FilterDates, FilterSelect, ListToolbar, activeFilters, useList } from '@/Components/Admin/ListToolbar';
import { OrderStatusBadge, PaymentStatusBadge } from '@/Components/Admin/OrderBadges';
import { AdminEmpty, AdminPagination, Button, FilterChips, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

export default function OrdersIndex({ orders, filters, statuses, paymentStatuses, paymentMethods, fulfilments, sorts, counts, customerName }) {
    const list = useList('/admin/orders', filters, { sort: 'newest', per_page: 25 });
    const apply = list.apply;
    const active = activeFilters(filters, {
        customer: () => `Customer: ${customerName || 'unknown'}`,
        payment_status: paymentStatuses,
        payment_method: paymentMethods,
        fulfilment: fulfilments,
        from: (v) => `From ${v}`,
        to: (v) => `Until ${v}`,
    });
    const total = Object.values(counts).reduce((a, b) => a + Number(b), 0);
    const filtered = filters.search || filters.status || active.length > 0;
    const tabs = [['', 'All', total], ...Object.entries(statuses).map(([k, l]) => [k, l, counts[k] ?? 0])];

    return (
        <AdminLayout title="Orders">
            <PageHeader title="Orders" description="Orders placed through your website's checkout." />

            <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                <div className="border-b border-zinc-100 px-4 pt-4 pb-3">
                    <FilterChips label="Filter by status" value={filters.status} onChange={(key) => apply({ status: key })} options={tabs} />
                </div>
                <ListToolbar list={list} placeholder="Order number, name, phone, email or coupon…" sorts={sorts} exportUrl="/admin/orders/export" active={active}>
                    <FilterSelect list={list} name="payment_status" label="Payment" options={paymentStatuses} />
                    <FilterSelect list={list} name="payment_method" label="Payment method" options={paymentMethods} />
                    <FilterSelect list={list} name="fulfilment" label="Delivery or pickup" options={fulfilments} />
                    <FilterDates list={list} label="Placed between" />
                </ListToolbar>

                {orders.data.length === 0 ? (
                    <AdminEmpty
                        icon={ShoppingBag}
                        title={filtered ? 'No orders match' : 'No orders yet'}
                        message={filtered ? 'Try a different search or status.' : 'When customers check out on your website, their orders appear here.'}
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
                                    <th className="px-4 py-3">Order</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Total</th>
                                    <th className="px-4 py-3">Payment</th>
                                    <th className="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100">
                                {orders.data.map((o) => (
                                    <tr key={o.id} className="cursor-pointer hover:bg-zinc-50/60" onClick={() => router.visit(`/admin/orders/${o.id}`)}>
                                        <td className="px-4 py-3">
                                            <Link href={`/admin/orders/${o.id}`} className="font-semibold text-zinc-900 hover:underline" onClick={(e) => e.stopPropagation()}>
                                                #{o.number}
                                            </Link>
                                            <span className="block text-xs text-zinc-500" title={o.created_at}>
                                                {o.created_ago}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="block font-medium text-zinc-900">{o.customer_name}</span>
                                            <span className="block text-xs text-zinc-500">{o.customer_phone}</span>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className="font-medium text-zinc-900">{o.total}</span>
                                            <span className="block text-xs text-zinc-500">
                                                {o.item_count} {o.item_count === 1 ? 'item' : 'items'} · {o.fulfilment_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="block text-xs text-zinc-600">{o.payment_method_label}</span>
                                            <span className="mt-1 block">
                                                <PaymentStatusBadge status={o.payment_status} label={o.payment_status_label} />
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <OrderStatusBadge status={o.status} label={o.status_label} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <ul className="divide-y divide-zinc-100 md:hidden">
                            {orders.data.map((o) => (
                                <li key={o.id}>
                                    <Link href={`/admin/orders/${o.id}`} className="flex items-center gap-3.5 px-4 py-3.5 transition active:bg-zinc-50">
                                        <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-base font-semibold ${o.status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-zinc-100 text-zinc-700'}`} aria-hidden>
                                            {(o.customer_name || '#').charAt(0).toUpperCase()}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-baseline justify-between gap-2">
                                                <span className="truncate text-[15px] font-semibold text-zinc-900">{o.customer_name}</span>
                                                <span className="shrink-0 text-[15px] font-semibold text-zinc-900">{o.total}</span>
                                            </span>
                                            <span className="mt-0.5 flex items-baseline justify-between gap-2 text-xs text-zinc-500">
                                                <span className="truncate">
                                                    #{o.number} · {o.item_count} {o.item_count === 1 ? 'item' : 'items'} · {o.fulfilment_label}
                                                </span>
                                                <span className="shrink-0">{o.created_ago}</span>
                                            </span>
                                            <span className="mt-2 flex flex-wrap gap-1.5">
                                                <OrderStatusBadge status={o.status} label={o.status_label} />
                                                <PaymentStatusBadge status={o.payment_status} label={o.payment_status_label} />
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <AdminPagination paginator={orders} list={list} />
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
