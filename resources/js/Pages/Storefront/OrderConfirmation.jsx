import { Link } from '@inertiajs/react';
import { Banknote, Check, CheckCircle2, Copy, Landmark, MapPin, Truck } from 'lucide-react';
import { useState } from 'react';
import { WhatsAppIcon } from '@/Components/Icons';
import Img from '@/Components/Store/Img';
import StoreLayout from '@/Layouts/StoreLayout';

function CopyButton({ text }) {
    const [copied, setCopied] = useState(false);
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            /* Clipboard unavailable (e.g. insecure context): the number stays selectable. */
        }
    };
    return (
        <button type="button" onClick={copy} className="inline-flex items-center gap-1.5 rounded-full border border-stone-300 bg-white px-3 py-1.5 text-xs font-semibold text-stone-800 hover:border-stone-900">
            {copied ? <Check size={14} className="text-emerald-600" /> : <Copy size={14} />}
            {copied ? 'Copied' : 'Copy'}
        </button>
    );
}

/** What the customer should do next, per payment method. */
function NextStep({ order, whatsappUrl, bank }) {
    if (order.payment_method === 'whatsapp') {
        return (
            <div className="rounded-3xl bg-emerald-50 p-5 sm:p-6">
                <h2 className="flex items-center gap-2 text-lg font-semibold text-emerald-950">
                    <WhatsAppIcon size={20} /> One last step: send your order
                </h2>
                <p className="mt-2 text-sm text-emerald-900">Tap the button to send your order to us on WhatsApp. We will confirm it and agree payment with you there.</p>
                {whatsappUrl && (
                    <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="btn-whatsapp mt-4 h-13 w-full text-base sm:w-auto sm:px-8">
                        <WhatsAppIcon size={20} /> Send order on WhatsApp
                    </a>
                )}
            </div>
        );
    }

    if (order.payment_method === 'bank_transfer' && bank) {
        return (
            <div className="rounded-3xl border border-stone-200 p-5 sm:p-6">
                <h2 className="flex items-center gap-2 text-lg font-semibold text-stone-900">
                    <Landmark size={20} /> Complete your payment
                </h2>
                <p className="mt-2 text-sm text-stone-600">
                    Transfer <strong className="text-stone-900">{order.total}</strong> to the account below. Use <strong className="text-stone-900">#{order.number}</strong> as the reference.
                </p>
                <dl className="mt-4 divide-y divide-stone-200 rounded-2xl bg-stone-50 text-sm">
                    {bank.name && (
                        <div className="flex justify-between gap-4 px-4 py-3">
                            <dt className="text-stone-500">Bank</dt>
                            <dd className="text-right font-medium text-stone-900">{bank.name}</dd>
                        </div>
                    )}
                    {bank.account_name && (
                        <div className="flex justify-between gap-4 px-4 py-3">
                            <dt className="text-stone-500">Account name</dt>
                            <dd className="text-right font-medium text-stone-900">{bank.account_name}</dd>
                        </div>
                    )}
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-stone-500">Account number</dt>
                        <dd className="flex items-center gap-3">
                            <span className="text-lg font-bold tracking-wider text-stone-900 tabular-nums">{bank.account_number}</span>
                            <CopyButton text={bank.account_number} />
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4 px-4 py-3">
                        <dt className="text-stone-500">Amount</dt>
                        <dd className="font-bold text-stone-900">{order.total}</dd>
                    </div>
                </dl>
                {whatsappUrl && (
                    <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="btn-whatsapp mt-4 h-12 w-full sm:w-auto sm:px-6">
                        <WhatsAppIcon size={18} /> Send payment confirmation on WhatsApp
                    </a>
                )}
            </div>
        );
    }

    return (
        <div className="rounded-3xl border border-stone-200 p-5 sm:p-6">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-stone-900">
                <Banknote size={20} /> Pay on {order.fulfilment === 'pickup' ? 'pickup' : 'delivery'}
            </h2>
            <p className="mt-2 text-sm text-stone-600">
                We will contact you on <strong className="text-stone-900">{order.customer_phone}</strong> to confirm your order. Pay <strong className="text-stone-900">{order.total}</strong> when you receive it.
            </p>
            {whatsappUrl && (
                <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-whatsapp-dark hover:underline">
                    <WhatsAppIcon size={17} /> Message us about this order
                </a>
            )}
        </div>
    );
}

export default function OrderConfirmation({ order, whatsappUrl, bank, justPlaced }) {
    return (
        <StoreLayout>
            <div className="container-page max-w-3xl pt-8 pb-16 sm:pt-12">
                <div className="text-center">
                    <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <CheckCircle2 size={34} strokeWidth={1.8} />
                    </span>
                    <h1 className="mt-5 text-3xl font-bold tracking-tight text-balance text-stone-950 sm:text-4xl">{justPlaced ? `Thank you, ${order.customer_name.split(' ')[0]}!` : `Order #${order.number}`}</h1>
                    <p className="mt-2 text-stone-600">
                        {justPlaced ? (
                            <>
                                Your order <strong className="text-stone-900">#{order.number}</strong> has been received.
                            </>
                        ) : (
                            <>
                                Placed {order.created_at} · Status: <strong className="text-stone-900">{order.status_label}</strong>
                            </>
                        )}
                    </p>
                    {justPlaced && order.customer_email && <p className="mt-1 text-sm text-stone-500">A confirmation has been sent to {order.customer_email}.</p>}
                </div>

                <div className="mt-8 space-y-6">
                    {order.status !== 'cancelled' && order.payment_status !== 'paid' && <NextStep order={order} whatsappUrl={whatsappUrl} bank={bank} />}

                    <section className="rounded-3xl border border-stone-200 p-5 sm:p-6">
                        <h2 className="text-lg font-semibold text-stone-900">Order details</h2>
                        <ul className="mt-4 divide-y divide-stone-100">
                            {order.items.map((i) => (
                                <li key={i.id} className="flex items-center gap-3 py-3">
                                    <span className="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-stone-100">{i.image && <Img image={{ src: i.image, alt: '' }} className="h-full w-full object-cover" />}</span>
                                    <span className="min-w-0 flex-1">
                                        {i.url ? (
                                            <Link href={i.url} className="line-clamp-2 text-sm text-stone-800 hover:underline">
                                                {i.name}
                                            </Link>
                                        ) : (
                                            <span className="line-clamp-2 text-sm text-stone-800">{i.name}</span>
                                        )}
                                        {i.variant && <span className="block text-xs font-medium text-stone-600">{i.variant}</span>}
                                        <span className="text-xs text-stone-500">
                                            {i.quantity} × {i.unit_price}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-sm font-semibold text-stone-900">{i.line_total}</span>
                                </li>
                            ))}
                        </ul>
                        <dl className="mt-2 space-y-2 border-t border-stone-100 pt-4 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-stone-600">Subtotal</dt>
                                <dd className="font-medium text-stone-900">{order.subtotal}</dd>
                            </div>
                            {order.coupon_code && (
                                <div className="flex justify-between gap-3">
                                    <dt className="min-w-0 truncate text-stone-600">Coupon · {order.coupon_code}</dt>
                                    <dd className="shrink-0 font-medium text-emerald-700">{order.discount ? `−${order.discount}` : 'Applied'}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-stone-600">{order.shipping_method || 'Delivery'}</dt>
                                <dd className="text-stone-900">{order.shipping_cost}</dd>
                            </div>
                            <div className="flex items-baseline justify-between border-t border-stone-100 pt-3">
                                <dt className="text-base font-semibold text-stone-900">Total</dt>
                                <dd className="text-xl font-bold text-stone-950">{order.total}</dd>
                            </div>
                        </dl>
                    </section>

                    <section className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-3xl border border-stone-200 p-5">
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-stone-900">
                                {order.fulfilment === 'pickup' ? <MapPin size={16} /> : <Truck size={16} />}
                                {order.fulfilment === 'pickup' ? 'Pickup from' : 'Delivering to'}
                            </h3>
                            <p className="mt-2 text-sm whitespace-pre-line text-stone-600">{order.address_line || 'We will send you the pickup details.'}</p>
                        </div>
                        <div className="rounded-3xl border border-stone-200 p-5">
                            <h3 className="text-sm font-semibold text-stone-900">Contact & payment</h3>
                            <p className="mt-2 text-sm text-stone-600">
                                {order.customer_name}
                                <br />
                                {order.customer_phone}
                                <br />
                                {order.payment_method_label} · {order.payment_status_label}
                            </p>
                        </div>
                    </section>

                    <p className="text-center text-xs text-stone-500">Bookmark this page to check your order later. Keep the link private: anyone with it can see your order.</p>

                    <div className="text-center">
                        <Link href="/products" className="btn-outline h-12 px-6">
                            Continue shopping
                        </Link>
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
