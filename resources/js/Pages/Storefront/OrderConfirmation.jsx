import { Link, router } from '@inertiajs/react';
import { AlertTriangle, Banknote, Check, CheckCircle2, Copy, CreditCard, Landmark, Loader2, Lock, MapPin, ShieldAlert, Truck, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { WhatsAppIcon } from '@/Components/Icons';
import Img from '@/Components/Store/Img';
import ReceiptUpload from '@/Components/Store/ReceiptUpload';
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

function BankDetails({ bank, total }) {
    return (
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
                <dd className="font-bold text-stone-900">{total}</dd>
            </div>
        </dl>
    );
}

function PayOnline({ order, payUrl }) {
    const [busy, setBusy] = useState(false);
    return (
        <div className="rounded-3xl border border-stone-200 p-5 sm:p-6">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-stone-900">
                <CreditCard size={20} /> Complete your payment
            </h2>
            <p className="mt-2 text-sm text-stone-600">
                Pay <strong className="text-stone-900">{order.total}</strong> securely with card, bank transfer or USSD on Paystack. We confirm your order as soon as the payment is verified.
            </p>
            {payUrl ? (
                <button type="button" disabled={busy} onClick={() => router.post(payUrl, {}, { onStart: () => setBusy(true), onFinish: () => setBusy(false) })} className="btn-primary mt-4 h-13 w-full text-base disabled:opacity-60 sm:w-auto sm:px-8">
                    <Lock size={18} /> {busy ? 'Opening secure payment…' : `Pay ${order.total} now`}
                </button>
            ) : (
                <p className="mt-4 text-sm text-stone-600">Online payment isn’t available right now. Please contact us to pay for this order.</p>
            )}
            <p className="mt-3 flex items-center gap-1.5 text-xs text-stone-500">
                <Lock size={12} /> Your card details are entered on Paystack’s page and never reach this website.
            </p>
            <p className="mt-1 text-xs text-stone-500">Already sent a bank transfer? Please don’t pay again: we’ll confirm it automatically and email you.</p>
        </div>
    );
}

/** What the customer should do next, per payment method. */
function NextStep({ order, whatsappUrl, bank, payUrl }) {
    if (order.payment_method === 'paystack') {
        return <PayOnline order={order} payUrl={payUrl} />;
    }

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
                <BankDetails bank={bank} total={order.total} />
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
                We will contact you on <strong className="text-stone-900">{order.customer_whatsapp || order.customer_phone}</strong> to confirm your order. Pay <strong className="text-stone-900">{order.total}</strong> with cash or bank transfer when you receive it.
            </p>
            {bank && (
                <>
                    <p className="mt-4 text-sm font-medium text-stone-900">Paying by transfer? Use these details when your order {order.fulfilment === 'pickup' ? 'is ready' : 'arrives'}, with <strong>#{order.number}</strong> as the reference.</p>
                    <BankDetails bank={bank} total={order.total} />
                </>
            )}
            {whatsappUrl && (
                <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-whatsapp-dark hover:underline">
                    <WhatsAppIcon size={17} /> Message us about this order
                </a>
            )}
        </div>
    );
}

/**
 * The top of the page for online (Paystack) orders: says plainly whether the
 * payment went through, so nobody thinks they've paid when they haven't.
 */
function OnlinePaymentHero({ state, order, justPlaced, whatsappUrl }) {
    const [waited, setWaited] = useState(0);

    // Confirming: ask again every 5 seconds for up to 2 minutes.
    useEffect(() => {
        if (state !== 'confirming' || waited >= 24) return;
        const t = setTimeout(() => router.reload({ preserveScroll: true, onFinish: () => setWaited((w) => w + 1) }), 5000);
        return () => clearTimeout(t);
    }, [state, waited]);

    const first = order.customer_name.split(' ')[0];
    const views = {
        paid: {
            icon: <CheckCircle2 size={34} strokeWidth={1.8} />,
            tone: 'bg-emerald-100 text-emerald-700',
            title: justPlaced ? `Thank you, ${first}!` : `Order #${order.number}`,
            text: (
                <>
                    Payment received. Your order <strong className="text-stone-900">#{order.number}</strong> is confirmed.
                </>
            ),
            note: justPlaced && order.customer_email ? `A confirmation has been sent to ${order.customer_email}.` : null,
        },
        confirming: {
            icon: <Loader2 size={32} className="animate-spin" />,
            tone: 'bg-sky-100 text-sky-700',
            title: 'Confirming your payment…',
            text: waited >= 24 ? 'Your bank is taking longer than usual. You can close this page: we’ll email you as soon as the payment is confirmed. Please don’t pay again.' : 'This usually takes a few seconds. Please don’t close this page or pay again.',
        },
        not_paid: {
            icon: <AlertTriangle size={32} strokeWidth={1.8} />,
            tone: 'bg-amber-100 text-amber-700',
            title: 'Payment not completed',
            text: (
                <>
                    You haven’t been charged. Your order <strong className="text-stone-900">#{order.number}</strong> is saved: complete the payment below to confirm it.
                </>
            ),
        },
        review: {
            icon: <ShieldAlert size={32} strokeWidth={1.8} />,
            tone: 'bg-amber-100 text-amber-700',
            title: 'We’re checking your payment',
            text: 'The payment we received doesn’t match this order’s total. We’ll look into it and contact you. Please don’t pay again.',
        },
        cancelled: {
            icon: <XCircle size={32} strokeWidth={1.8} />,
            tone: 'bg-stone-200 text-stone-600',
            title: 'Order cancelled',
            text: 'The payment wasn’t completed, so this order was cancelled. You weren’t charged.',
        },
    };
    const v = views[state] ?? views.not_paid;

    return (
        <div className="text-center" aria-live="polite">
            <span className={`mx-auto flex h-16 w-16 items-center justify-center rounded-full ${v.tone}`}>{v.icon}</span>
            <h1 className="mt-5 text-3xl font-bold tracking-tight text-balance text-stone-950 sm:text-4xl">{v.title}</h1>
            <p className="mx-auto mt-2 max-w-xl text-stone-600">{v.text}</p>
            {v.note && <p className="mt-1 text-sm text-stone-500">{v.note}</p>}
            {state === 'cancelled' && (
                <Link href="/products" className="btn-primary mt-5 inline-flex h-12 px-6">
                    Continue shopping
                </Link>
            )}
            {state === 'review' && whatsappUrl && (
                <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="btn-whatsapp mt-5 inline-flex h-12 px-6">
                    <WhatsAppIcon size={18} /> Message us
                </a>
            )}
        </div>
    );
}

function Steps({ current }) {
    const steps = ['Order placed', 'Payment', 'Done'];
    return (
        <ol className="mx-auto flex max-w-sm items-center justify-between gap-2 text-xs font-medium" aria-label="Checkout progress">
            {steps.map((s, i) => {
                const done = i < current;
                const on = i === current;
                return (
                    <li key={s} className="flex flex-1 items-center gap-2 last:flex-none" aria-current={on ? 'step' : undefined}>
                        <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full ${done ? 'bg-emerald-600 text-white' : on ? 'bg-stone-900 text-white' : 'bg-stone-200 text-stone-500'}`}>{done ? <Check size={15} /> : i + 1}</span>
                        <span className={`whitespace-nowrap ${on ? 'text-stone-900' : 'text-stone-500'}`}>{s}</span>
                        {i < steps.length - 1 && <span className={`h-px flex-1 ${done ? 'bg-emerald-600' : 'bg-stone-200'}`} />}
                    </li>
                );
            })}
        </ol>
    );
}

/** Bank transfer, step 2: the bank details, the receipt upload and "I've made the payment". */
function BankTransferStep({ order, bank, reportUrl, receipts, receiptUpload, whatsappUrl, privateUrl }) {
    const [busy, setBusy] = useState(false);
    const [showItems, setShowItems] = useState(false);
    const hasReceipt = receipts.some((r) => r.status === 'pending');

    return (
        <StoreLayout>
            <div className="container-page max-w-xl pt-8 pb-16 sm:pt-12">
                <Steps current={1} />
                <div className="mt-8 text-center">
                    <h1 className="text-3xl font-bold tracking-tight text-balance text-stone-950 sm:text-4xl">Complete your payment</h1>
                    <p className="mt-2 text-stone-600">
                        Transfer the exact amount below to finish order <strong className="text-stone-900">#{order.number}</strong>.
                    </p>
                </div>

                <div className="mt-8 space-y-6">
                    <section className="rounded-3xl border border-stone-200 p-5 sm:p-6">
                        <p className="text-center text-sm text-stone-500">Amount to pay</p>
                        <p className="mt-1 text-center text-4xl font-bold tracking-tight text-stone-950 tabular-nums">{order.total}</p>
                        <BankDetails bank={bank} total={order.total} />
                        <div className="mt-3 flex items-center justify-between gap-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm">
                            <span className="text-amber-950">
                                Use <strong>#{order.number}</strong> as the transfer reference / narration
                            </span>
                            <CopyButton text={order.number} />
                        </div>
                    </section>

                    <ReceiptUpload upload={receiptUpload} receipts={receipts} inStep />

                    <div>
                        <button type="button" disabled={busy} onClick={() => router.post(reportUrl, {}, { onStart: () => setBusy(true), onFinish: () => setBusy(false) })} className="btn-primary h-14 w-full text-base disabled:opacity-60">
                            {busy ? <Loader2 size={20} className="animate-spin" /> : <CheckCircle2 size={20} />} I’ve made the payment
                        </button>
                        <p className="mt-2 text-center text-xs text-stone-500">{hasReceipt ? 'Thanks for the receipt. Press the button once your transfer has gone through.' : 'No receipt? That’s fine: we’ll check our bank account. A receipt just helps us confirm faster.'}</p>
                    </div>

                    <section className="rounded-3xl border border-stone-200">
                        <button type="button" onClick={() => setShowItems((v) => !v)} aria-expanded={showItems} className="flex w-full items-center justify-between gap-3 p-5 text-left">
                            <span className="text-sm font-semibold text-stone-900">
                                Order summary · {order.item_count} {order.item_count === 1 ? 'item' : 'items'}
                            </span>
                            <span className="text-sm font-bold text-stone-900">{order.total}</span>
                        </button>
                        {showItems && (
                            <ul className="divide-y divide-stone-100 border-t border-stone-100 px-5">
                                {order.items.map((i) => (
                                    <li key={i.id} className="flex items-center gap-3 py-3 text-sm">
                                        <span className="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-stone-100">{i.image && <Img image={{ src: i.image, alt: '' }} className="h-full w-full object-cover" />}</span>
                                        <span className="min-w-0 flex-1 text-stone-800">
                                            <span className="line-clamp-2">{i.name}</span>
                                            <span className="text-xs text-stone-500">
                                                {i.variant ? `${i.variant} · ` : ''}
                                                {i.quantity} × {i.unit_price}
                                            </span>
                                        </span>
                                        <span className="shrink-0 font-semibold text-stone-900">{i.line_total}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    {privateUrl && <OrderLink url={privateUrl} />}

                    <p className="text-center text-xs text-stone-500">
                        Your order is saved. You can come back to this page any time from the link in your order email.
                        {whatsappUrl && (
                            <>
                                {' '}
                                Need help?{' '}
                                <a href={whatsappUrl} target="_blank" rel="noopener noreferrer" className="font-medium underline">
                                    Message us on WhatsApp
                                </a>
                                .
                            </>
                        )}
                    </p>
                </div>
            </div>
        </StoreLayout>
    );
}

/**
 * Shown on the thank-you address (/order/thanks), which only works in this browser for a while:
 * the private order link to keep. A full page load (not an in-app visit), so no tracking follows it.
 */
function OrderLink({ url }) {
    return (
        <section className="rounded-3xl border border-stone-200 p-5 sm:p-6">
            <h2 className="text-sm font-semibold text-stone-900">Your order link</h2>
            <p className="mt-1 text-sm text-stone-600">Open it any time to check your order. Keep it private: anyone with it can see your order. We’ve also sent it in your order email.</p>
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <a href={url} className="btn-outline h-10 px-4 text-sm">
                    Open my order page
                </a>
                <CopyButton text={url} />
            </div>
        </section>
    );
}

/** Bank transfer, step 3: thank you, we're verifying the payment. */
function TransferThanks({ order }) {
    return (
        <div className="text-center">
            <Steps current={3} />
            <span className="mx-auto mt-8 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <CheckCircle2 size={34} strokeWidth={1.8} />
            </span>
            <h1 className="mt-5 text-3xl font-bold tracking-tight text-balance text-stone-950 sm:text-4xl">Thank you, {order.customer_name.split(' ')[0]}!</h1>
            <p className="mx-auto mt-3 max-w-md text-stone-600">
                We’re verifying your transfer of <strong className="text-stone-900">{order.total}</strong>. Your order <strong className="text-stone-900">#{order.number}</strong> will be confirmed as soon as the payment is verified.
            </p>
            <p className="mt-2 text-sm text-stone-500">{order.customer_email ? `We’ll email ${order.customer_email} once it’s confirmed.` : 'We’ll contact you once it’s confirmed.'} You don’t need to pay again.</p>
        </div>
    );
}

export default function OrderConfirmation({ order, whatsappUrl, bank, justPlaced, payUrl, paymentState, receipts = [], receiptUpload = null, transferStep = null, paymentReported = false, privateUrl = null }) {
    if (transferStep && bank) {
        return <BankTransferStep order={order} bank={bank} reportUrl={transferStep} receipts={receipts} receiptUpload={receiptUpload} whatsappUrl={whatsappUrl} privateUrl={privateUrl} />;
    }

    return (
        <StoreLayout>
            <div className="container-page max-w-3xl pt-8 pb-16 sm:pt-12">
                {paymentState ? (
                    <OnlinePaymentHero state={paymentState} order={order} justPlaced={justPlaced} whatsappUrl={whatsappUrl} />
                ) : paymentReported && order.status !== 'cancelled' ? (
                    <TransferThanks order={order} />
                ) : (
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
                )}

                <div className="mt-8 space-y-6">
                    {/* Online orders: the pay button only while it's safe to pay (not while confirming or under review). */}
                    {order.status !== 'cancelled' && order.payment_status !== 'paid' && !paymentReported && (!paymentState || paymentState === 'not_paid') && <NextStep order={order} whatsappUrl={whatsappUrl} bank={bank} payUrl={payUrl} />}
                    {order.payment_status === 'paid' && (
                        <div className="flex items-center gap-3 rounded-3xl bg-emerald-50 p-5 text-emerald-950 sm:p-6">
                            <CheckCircle2 size={22} className="shrink-0 text-emerald-700" />
                            <p className="text-sm">
                                <strong>Paid.</strong> We’ve received your payment of {order.total}.
                            </p>
                        </div>
                    )}

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
                                {order.payment_method_label} · {paymentReported ? 'Payment being verified' : order.payment_status_label}
                            </p>
                        </div>
                    </section>

                    {privateUrl ? <OrderLink url={privateUrl} /> : <p className="text-center text-xs text-stone-500">Bookmark this page to check your order later. Keep the link private: anyone with it can see your order.</p>}

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
