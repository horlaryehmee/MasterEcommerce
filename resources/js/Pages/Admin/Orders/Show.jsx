import { router } from '@inertiajs/react';
import { ExternalLink, MapPin, Phone, Truck } from 'lucide-react';
import { useState } from 'react';
import { WhatsAppIcon } from '@/Components/Icons';
import { OrderStatusBadge, PaymentStatusBadge } from '@/Components/Admin/OrderBadges';
import { Card, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

function Row({ label, children }) {
    return (
        <div className="flex justify-between gap-4 py-1.5 text-sm">
            <dt className="min-w-0 text-zinc-500">{label}</dt>
            <dd className="shrink-0 text-right text-zinc-900">{children}</dd>
        </div>
    );
}

/** One-tap status changes; saves immediately. */
function StatusPicker({ label, field, value, options, orderId }) {
    const [saving, setSaving] = useState(null);
    const save = (v) => router.patch(`/admin/orders/${orderId}`, { [field]: v }, { preserveScroll: true, onStart: () => setSaving(v), onFinish: () => setSaving(null) });
    return (
        <div>
            <p className="mb-2 text-sm font-medium text-zinc-800">{label}</p>
            <div className="flex flex-wrap gap-2">
                {Object.entries(options).map(([k, l]) => (
                    <button key={k} type="button" disabled={!!saving} onClick={() => k !== value && save(k)} aria-pressed={k === value} className={`h-10 shrink-0 rounded-full border px-4 text-sm font-medium transition active:scale-95 disabled:opacity-60 sm:h-auto sm:rounded-lg sm:px-3 sm:py-1.5 ${k === value ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50'}`}>
                        {saving === k ? 'Saving…' : l}
                    </button>
                ))}
            </div>
        </div>
    );
}

export default function OrderShow({ order, statuses, paymentStatuses, customerWhatsapp }) {
    const tel = order.customer_phone.replace(/[^\d+]/g, '');

    return (
        <AdminLayout title={`Order #${order.number}`}>
            {/* Phones: one-tap contact, always within reach above the tab bar. */}
            <div className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 flex gap-2 border-t border-zinc-200 bg-white/95 px-4 py-2.5 shadow-[0_-10px_24px_-20px_rgba(24,24,27,0.45)] backdrop-blur-md lg:hidden">
                <a href={`tel:${tel}`} className="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-zinc-300 bg-white text-sm font-semibold text-zinc-800 transition active:scale-[0.97]">
                    <Phone size={17} /> Call
                </a>
                {customerWhatsapp && (
                    <a href={customerWhatsapp} target="_blank" rel="noopener noreferrer" className="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 text-sm font-semibold text-white transition active:scale-[0.97]">
                        <WhatsAppIcon size={17} /> WhatsApp
                    </a>
                )}
            </div>
            <PageHeader
                back={{ href: '/admin/orders', label: 'Orders' }}
                title={`Order #${order.number}`}
                description={`Placed ${order.created_at}`}
                actions={
                    <div className="flex items-center gap-2">
                        <OrderStatusBadge status={order.status} label={order.status_label} />
                        <PaymentStatusBadge status={order.payment_status} label={order.payment_status_label} />
                    </div>
                }
            />

            <div className="grid gap-5 pb-16 sm:gap-6 lg:grid-cols-[1fr_360px] lg:pb-0">
                <div className="space-y-6">
                    <Card title="Update order">
                        <div className="space-y-5">
                            <StatusPicker label="Order status" field="status" value={order.status} options={statuses} orderId={order.id} />
                            <StatusPicker label="Payment" field="payment_status" value={order.payment_status} options={paymentStatuses} orderId={order.id} />
                        </div>
                    </Card>

                    <Card title={`Items (${order.item_count})`} padded={false}>
                        <ul className="divide-y divide-zinc-100">
                            {order.items.map((i) => (
                                <li key={i.id} className="flex items-center gap-3 px-4 py-3 sm:px-5">
                                    <span className="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-zinc-100">{i.image && <img src={i.image} alt="" className="h-full w-full object-cover" loading="lazy" />}</span>
                                    <span className="min-w-0 flex-1">
                                        {i.url ? (
                                            <a href={i.url} target="_blank" rel="noopener noreferrer" className="line-clamp-2 text-sm font-medium text-zinc-900 hover:underline">
                                                {i.name}
                                            </a>
                                        ) : (
                                            <span className="line-clamp-2 text-sm font-medium text-zinc-900">{i.name}</span>
                                        )}
                                        <span className="text-xs text-zinc-500">
                                            {i.quantity} × {i.unit_price}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-sm font-semibold text-zinc-900">{i.line_total}</span>
                                </li>
                            ))}
                        </ul>
                        <dl className="border-t border-zinc-100 px-5 py-3">
                            <Row label="Subtotal">{order.subtotal}</Row>
                            <Row label={order.shipping_method || 'Delivery'}>{order.shipping_cost === 'To be confirmed' ? 'To be agreed' : order.shipping_cost}</Row>
                            <div className="mt-1 flex justify-between border-t border-zinc-100 pt-2.5 text-base font-semibold">
                                <dt>Total</dt>
                                <dd>{order.total}</dd>
                            </div>
                        </dl>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Customer">
                        <p className="font-medium text-zinc-900">{order.customer_name}</p>
                        <p className="mt-1 text-sm text-zinc-600">{order.customer_phone}</p>
                        {order.customer_email && (
                            <a href={`mailto:${order.customer_email}`} className="mt-0.5 block text-sm text-zinc-600 hover:underline">
                                {order.customer_email}
                            </a>
                        )}
                        <div className="mt-4 grid grid-cols-2 gap-2">
                            <a href={`tel:${tel}`} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-zinc-300 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                                <Phone size={15} /> Call
                            </a>
                            {customerWhatsapp ? (
                                <a href={customerWhatsapp} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 text-sm font-semibold text-white hover:bg-emerald-700">
                                    <WhatsAppIcon size={15} /> WhatsApp
                                </a>
                            ) : (
                                <span className="flex items-center text-xs text-zinc-400" title="The number isn't in international format (e.g. +234…), so a WhatsApp link can't be made.">
                                    No WhatsApp link
                                </span>
                            )}
                        </div>
                    </Card>

                    <Card title={order.fulfilment === 'pickup' ? 'Pickup' : 'Delivery'}>
                        <p className="flex items-start gap-2 text-sm text-zinc-700">
                            {order.fulfilment === 'pickup' ? <MapPin size={16} className="mt-0.5 shrink-0" /> : <Truck size={16} className="mt-0.5 shrink-0" />}
                            <span>
                                <span className="block">{order.fulfilment === 'pickup' ? `Pickup from ${order.address_line || 'your store'}` : order.address_line}</span>
                                {(order.shipping_method || order.shipping_zone) && (
                                    <span className="mt-1 block text-xs text-zinc-500">{[order.shipping_method, order.shipping_zone && `Zone: ${order.shipping_zone}`].filter(Boolean).join(' · ')}</span>
                                )}
                            </span>
                        </p>
                        {order.notes && (
                            <div className="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                <span className="font-semibold">Note: </span>
                                {order.notes}
                            </div>
                        )}
                    </Card>

                    <Card title="Payment">
                        <dl>
                            <Row label="Method">{order.payment_method_label}</Row>
                            <Row label="Status">
                                <PaymentStatusBadge status={order.payment_status} label={order.payment_status_label} />
                            </Row>
                        </dl>
                        <a href={order.public_url} target="_blank" rel="noopener noreferrer" className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-600 hover:text-zinc-900">
                            Customer's order page <ExternalLink size={14} />
                        </a>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
