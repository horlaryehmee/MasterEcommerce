import { Link, router, usePage } from '@inertiajs/react';
import { CircleDollarSign, ListChecks, ShoppingBag, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BulkBar, BulkDialog, SelectAll, SelectRow, plural, useBulkAction, useSelection } from '@/Components/Admin/Bulk';
import { FilterDates, FilterSelect, ListToolbar, activeFilters, useList } from '@/Components/Admin/ListToolbar';
import { OrderStatusBadge, PaymentStatusBadge } from '@/Components/Admin/OrderBadges';
import { AdminEmpty, AdminPagination, Button, FilterChips, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

function ReceiptBadge() {
    return (
        <span className="rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-sky-900 uppercase" title="The customer says they’ve paid by bank transfer: check your bank">
            Check payment
        </span>
    );
}

function TestBadge() {
    return (
        <span className="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-amber-900 uppercase" title="Paid with Paystack test keys: no real money was received">
            Test
        </span>
    );
}

export default function OrdersIndex({ orders, filters, statuses, paymentStatuses, paymentMethods, fulfilments, sorts, counts, customerName }) {
    const list = useList('/admin/orders', filters, { sort: 'newest', per_page: 25 });
    const receiptsWaiting = usePage().props.admin?.payments_to_check ?? 0;
    const apply = list.apply;
    const active = activeFilters(filters, {
        customer: () => `Customer: ${customerName || 'unknown'}`,
        payment_status: paymentStatuses,
        payment_method: paymentMethods,
        fulfilment: fulfilments,
        receipt: () => 'Payment to check',
        from: (v) => `From ${v}`,
        to: (v) => `Until ${v}`,
    });
    const total = Object.values(counts).reduce((a, b) => a + Number(b), 0);
    const filtered = filters.search || filters.status || active.length > 0;
    const sel = useSelection(orders.data);
    const bulk = useBulkAction('/admin/orders/bulk', sel);
    const [dialog, setDialog] = useState(null);
    const n = plural(sel.count, 'order');
    const ask = (d) => {
        bulk.setErrors({});
        setDialog(d);
    };
    const bulkActions = [
        { label: 'Change status…', icon: ListChecks, onClick: () => ask({ action: 'status', title: `Change status of ${n}`, confirmLabel: 'Update', message: 'Customers with an email address are told about the change, as when you update one order.', choose: { label: 'New status', options: Object.entries(statuses) } }) },
        { label: 'Payment…', icon: CircleDollarSign, onClick: () => ask({ action: 'payment_status', title: `Change payment status of ${n}`, confirmLabel: 'Update', choose: { label: 'Payment status', options: Object.entries(paymentStatuses) } }) },
        {
            label: 'Delete',
            icon: Trash2,
            danger: true,
            onClick: () => ask({ action: 'delete', title: `Delete ${n}?`, danger: true, confirmLabel: `Delete ${n}`, message: `${n} will be permanently deleted with their items. Paid orders are kept (they are your sales record): cancel or refund them instead.` }),
        },
    ];
    const tabs = [['', 'All', total], ...Object.entries(statuses).map(([k, l]) => [k, l, counts[k] ?? 0])];

    return (
        <AdminLayout title="Orders">
            <PageHeader title="Orders" description="Orders placed through your website's checkout." />
            {receiptsWaiting > 0 && !filters.receipt && (
                <button type="button" onClick={() => apply({ receipt: 'pending' })} className="mb-4 flex w-full items-center justify-between gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-left text-sm text-sky-950 hover:bg-sky-100">
                    <span>
                        <strong>{plural(receiptsWaiting, 'bank transfer')} to check.</strong> Customers say they’ve paid. Check your bank, then confirm.
                    </span>
                    <span className="shrink-0 font-semibold underline">View</span>
                </button>
            )}

            <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                <div className="border-b border-zinc-100 px-4 pt-4 pb-3">
                    <FilterChips label="Filter by status" value={filters.status} onChange={(key) => apply({ status: key })} options={tabs} />
                </div>
                <ListToolbar list={list} placeholder="Order number, name, phone, email or coupon…" sorts={sorts} exportUrl="/admin/orders/export" active={active}>
                    <FilterSelect list={list} name="payment_status" label="Payment" options={paymentStatuses} />
                    <FilterSelect list={list} name="payment_method" label="Payment method" options={paymentMethods} />
                    <FilterSelect list={list} name="fulfilment" label="Delivery or pickup" options={fulfilments} />
                    <FilterSelect list={list} name="receipt" label="Bank transfers" options={{ pending: 'Payment to check' }} placeholder="Any" />
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
                                    <th className="w-10 py-3 pl-4">
                                        <SelectAll selection={sel} />
                                    </th>
                                    <th className="px-4 py-3">Order</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Total</th>
                                    <th className="px-4 py-3">Payment</th>
                                    <th className="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100">
                                {orders.data.map((o) => (
                                    <tr key={o.id} className={`cursor-pointer ${sel.has(o.id) ? 'bg-zinc-50' : 'hover:bg-zinc-50/60'}`} onClick={() => router.visit(`/admin/orders/${o.id}`)}>
                                        <td className="py-3 pl-4">
                                            <SelectRow selection={sel} id={o.id} label={`Select order #${o.number}`} />
                                        </td>
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
                                            <span className="mt-1 flex items-center gap-1">
                                                <PaymentStatusBadge status={o.payment_status} label={o.payment_status_label} />
                                                {o.paid_in_test && <TestBadge />}
                                                {o.payment_to_check && <ReceiptBadge />}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <OrderStatusBadge status={o.status} label={o.status_label} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="flex items-center gap-3 border-b border-zinc-100 px-4 py-2.5 md:hidden">
                            <SelectAll selection={sel} />
                            <span className="text-xs font-medium text-zinc-500">{sel.count ? `${sel.count} selected` : 'Select all'}</span>
                        </div>
                        <ul className="divide-y divide-zinc-100 md:hidden">
                            {orders.data.map((o) => (
                                <li key={o.id} className={`flex items-center pl-4 ${sel.has(o.id) ? 'bg-zinc-50' : ''}`}>
                                    <SelectRow selection={sel} id={o.id} label={`Select order #${o.number}`} />
                                    <Link href={`/admin/orders/${o.id}`} className="flex min-w-0 flex-1 items-center gap-3.5 py-3.5 pr-4 pl-3 transition active:bg-zinc-50">
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
                                                {o.paid_in_test && <TestBadge />}
                                                {o.payment_to_check && <ReceiptBadge />}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <BulkBar selection={sel} noun="order" actions={bulkActions} exportUrl="/admin/orders/export" processing={bulk.processing} />
                        <AdminPagination paginator={orders} list={list} />
                    </>
                )}
            </div>

            <BulkDialog
                open={!!dialog}
                onClose={() => setDialog(null)}
                onConfirm={(value) => bulk.run(dialog.action, value, { onSuccess: () => setDialog(null) })}
                processing={bulk.processing}
                error={bulk.errors.value || bulk.errors.ids}
                {...dialog}
            />
        </AdminLayout>
    );
}
