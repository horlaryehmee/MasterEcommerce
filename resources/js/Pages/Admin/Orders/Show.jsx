import { Link, router } from '@inertiajs/react';
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

const PAYMENT_COLORS = { success: 'bg-emerald-50 text-emerald-700', flagged: 'bg-red-50 text-red-700', failed: 'bg-zinc-100 text-zinc-600', abandoned: 'bg-zinc-100 text-zinc-600', pending: 'bg-amber-50 text-amber-800' };

export default function OrderShow({ order, statuses, paymentStatuses, customerWhatsapp, customer, payments = [], paidAt }) {
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

            {order.status === 'awaiting_payment' && (
                <div role="alert" className="mb-5 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <strong>Not paid. Don’t send this order yet.</strong> The customer started paying online but Paystack hasn’t confirmed a payment. It becomes a New order automatically if they pay, and is cancelled after 24 hours if they don’t.
                </div>
            )}
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
                                        {i.variant && (
                                            <span className="block text-xs font-medium text-zinc-700">
                                                {i.variant}
                                                {i.sku && <span className="font-normal text-zinc-500"> · SKU {i.sku}</span>}
                                            </span>
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
                            {order.coupon_code && (
                                <Row label={`Coupon · ${order.coupon_code}`}>
                                    <span className="text-emerald-700">{order.discount ? `−${order.discount}` : 'Applied'}</span>
                                </Row>
                            )}
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
                        {order.customer_whatsapp && order.customer_whatsapp !== order.customer_phone && (
                            <p className="mt-0.5 flex items-center gap-1.5 text-sm text-zinc-600">
                                <WhatsAppIcon size={14} className="text-emerald-600" /> {order.customer_whatsapp}
                            </p>
                        )}
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
                                <span className="flex items-center text-xs text-zinc-400" title="This number can't be turned into a WhatsApp link. Check it's a full phone number.">
                                    No WhatsApp link
                                </span>
                            )}
                        </div>
                        {customer && (
                            <Link href={customer.url} className="mt-3 block border-t border-zinc-100 pt-3 text-sm font-semibold text-zinc-900 hover:underline">
                                {customer.orders_count > 1 ? `Returning customer · ${customer.orders_count} orders →` : 'View customer →'}
                            </Link>
                        )}
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
                            {paidAt && <Row label="Paid on">{paidAt}</Row>}
                        </dl>
                        {payments.length > 0 && (
                            <div className="mt-3 border-t border-zinc-100 pt-3">
                                <p className="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Online payments</p>
                                <ul className="mt-2 space-y-2">
                                    {payments.map((p) => (
                                        <li key={p.id} className="rounded-lg border border-zinc-100 p-2.5 text-xs">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className={`rounded-full px-2 py-0.5 font-semibold ${PAYMENT_COLORS[p.status] ?? 'bg-zinc-100 text-zinc-600'}`}>{p.status_label}</span>
                                                <span className="font-semibold text-zinc-900">{p.amount}</span>
                                            </div>
                                            <p className="mt-1.5 font-mono break-all text-zinc-500">{p.reference}</p>
                                            <p className="mt-1 text-zinc-500">
                                                {[p.at, p.channel, p.mode === 'test' ? 'TEST MODE' : null].filter(Boolean).join(' · ')}
                                            </p>
                                            {p.amount_paid && <p className="mt-1 font-medium text-red-700">Paystack reported {p.amount_paid}</p>}
                                            {p.message && <p className="mt-1 text-zinc-600">{p.message}</p>}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <a href={order.public_url} target="_blank" rel="noopener noreferrer" className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-600 hover:text-zinc-900">
                            Customer's order page <ExternalLink size={14} />
                        </a>
                        <a href={`/admin/orders/${order.id}/email/placed`} target="_blank" rel="noopener noreferrer" className="mt-2 flex items-center gap-1.5 text-sm font-medium text-zinc-600 hover:text-zinc-900">
                            Preview order email <ExternalLink size={14} />
                        </a>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
