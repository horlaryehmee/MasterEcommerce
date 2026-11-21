import { Link } from '@inertiajs/react';
import { ExternalLink, Mail, MapPin, Phone } from 'lucide-react';
import { WhatsAppIcon } from '@/Components/Icons';
import { OrderStatusBadge, PaymentStatusBadge } from '@/Components/Admin/OrderBadges';
import { Button, Card, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

function Stat({ label, value, hint }) {
    return (
        <div className="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs">
            <p className="text-xs font-medium text-zinc-500">{label}</p>
            <p className="mt-1 text-lg font-bold tracking-tight text-zinc-900 sm:text-xl">{value ?? '—'}</p>
            {hint && <p className="mt-0.5 text-xs text-zinc-500">{hint}</p>}
        </div>
    );
}

export default function CustomerShow({ customer, orders, more_orders, products }) {
    return (
        <AdminLayout title={customer.name}>
            {/* Phones: one-tap contact above the tab bar. */}
            <div className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 flex gap-2 border-t border-zinc-200 bg-white/95 px-4 py-2.5 backdrop-blur-md lg:hidden">
                <a href={customer.call} className="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-zinc-300 bg-white text-sm font-semibold text-zinc-800 active:scale-[0.97]">
                    <Phone size={17} /> Call
                </a>
                {customer.whatsapp && (
                    <a href={customer.whatsapp} target="_blank" rel="noopener noreferrer" className="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 text-sm font-semibold text-white active:scale-[0.97]">
                        <WhatsAppIcon size={17} /> WhatsApp
                    </a>
                )}
            </div>

            <PageHeader
                back={{ href: '/admin/customers', label: 'Customers' }}
                title={customer.name}
                description={`Customer since ${customer.first_order} · last order ${customer.last_order_ago}`}
                actions={
                    <div className="hidden items-center gap-2 lg:flex">
                        <Button variant="secondary" href={`/admin/orders?customer=${customer.key}`}>
                            All their orders
                        </Button>
                        {customer.whatsapp && (
                            <a href={customer.whatsapp} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">
                                <WhatsAppIcon size={16} /> WhatsApp
                            </a>
                        )}
                    </div>
                }
            />

            <div className="mb-5 grid grid-cols-2 gap-3 sm:mb-6 lg:grid-cols-4">
                <Stat label="Orders" value={customer.orders_count} hint={customer.cancelled_count > 0 ? `${customer.cancelled_count} cancelled` : null} />
                <Stat label="Total spent" value={customer.spent} hint="Excludes cancelled orders" />
                <Stat label="Average order" value={customer.average} />
                <Stat label="Last order" value={customer.last_order} hint={customer.last_order_ago} />
            </div>

            <div className="grid gap-5 pb-16 sm:gap-6 lg:grid-cols-[1fr_340px] lg:pb-0">
                <Card title={`Orders (${customer.orders_count})`} padded={false}>
                    <ul className="divide-y divide-zinc-100">
                        {orders.map((o) => (
                            <li key={o.id}>
                                <Link href={`/admin/orders/${o.id}`} className="flex items-center gap-3 px-4 py-3 transition hover:bg-zinc-50/60 active:bg-zinc-50 sm:px-5">
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-semibold text-zinc-900">#{o.number}</span>
                                        <span className="block text-xs text-zinc-500">{o.created_at}</span>
                                    </span>
                                    <span className="hidden flex-wrap justify-end gap-1.5 sm:flex">
                                        <OrderStatusBadge status={o.status} label={o.status_label} />
                                        <PaymentStatusBadge status={o.payment_status} label={o.payment_status_label} />
                                    </span>
                                    <span className="w-24 shrink-0 text-right text-sm font-semibold text-zinc-900">{o.total}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                    {more_orders > 0 && (
                        <div className="border-t border-zinc-100 px-4 py-3 text-sm sm:px-5">
                            <Link href={`/admin/orders?customer=${customer.key}`} className="font-semibold text-zinc-900 underline underline-offset-2">
                                See all {customer.orders_count} orders
                            </Link>
                        </div>
                    )}
                </Card>

                <div className="space-y-5 sm:space-y-6">
                    <Card title="Contact">
                        <ul className="space-y-3 text-sm">
                            <li className="flex items-start gap-2.5">
                                <Phone size={16} className="mt-0.5 shrink-0 text-zinc-400" />
                                <a href={customer.call} className="text-zinc-900 hover:underline">
                                    {customer.phone}
                                </a>
                            </li>
                            {customer.whatsapp_number && (
                                <li className="flex items-start gap-2.5">
                                    <WhatsAppIcon size={16} className="mt-0.5 shrink-0 text-emerald-600" />
                                    <span className="text-zinc-900">{customer.whatsapp_number}</span>
                                </li>
                            )}
                            {customer.email && (
                                <li className="flex items-start gap-2.5">
                                    <Mail size={16} className="mt-0.5 shrink-0 text-zinc-400" />
                                    <a href={`mailto:${customer.email}`} className="break-all text-zinc-900 hover:underline">
                                        {customer.email}
                                    </a>
                                </li>
                            )}
                            {(customer.address || customer.location) && (
                                <li className="flex items-start gap-2.5">
                                    <MapPin size={16} className="mt-0.5 shrink-0 text-zinc-400" />
                                    <span className="text-zinc-700">{customer.address || customer.location}</span>
                                </li>
                            )}
                        </ul>
                        {customer.other_names.length > 0 && <p className="mt-4 border-t border-zinc-100 pt-3 text-xs text-zinc-500">Also ordered as: {customer.other_names.join(', ')}</p>}
                    </Card>

                    {products.length > 0 && (
                        <Card title="Bought most" padded={false}>
                            <ul className="divide-y divide-zinc-100">
                                {products.map((p) => (
                                    <li key={p.name} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm sm:px-5">
                                        {p.url ? (
                                            <a href={p.url} target="_blank" rel="noopener noreferrer" className="inline-flex min-w-0 items-center gap-1 truncate text-zinc-900 hover:underline">
                                                <span className="truncate">{p.name}</span> <ExternalLink size={12} className="shrink-0 text-zinc-400" />
                                            </a>
                                        ) : (
                                            <span className="truncate text-zinc-900">{p.name}</span>
                                        )}
                                        <span className="shrink-0 text-zinc-500">× {p.quantity}</span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
