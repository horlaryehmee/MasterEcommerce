import { Link, useForm } from '@inertiajs/react';
import { Banknote, Check, ChevronDown, Info, Landmark, Loader2, Lock, Package, Store, Truck } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { WhatsAppIcon } from '@/Components/Icons';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import CouponBox from '@/Components/Store/CouponBox';
import Img from '@/Components/Store/Img';
import StoreLayout from '@/Layouts/StoreLayout';
import { formatMoney } from '@/lib/money';

const PAYMENT_INFO = {
    whatsapp: { icon: WhatsAppIcon, text: 'Send your order to us on WhatsApp. We confirm it and agree payment with you there.' },
    delivery: { icon: Banknote, text: 'Pay with cash or card when your order arrives.' },
    bank_transfer: { icon: Landmark, text: 'Our account details are shown after you place the order.' },
};

function Field({ label, error, optional, children, htmlFor }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="mb-1.5 flex items-baseline justify-between text-sm font-medium text-stone-800">
                {label}
                {optional && <span className="text-xs font-normal text-stone-400">Optional</span>}
            </label>
            {children}
            {error && <p className="mt-1.5 text-xs font-medium text-red-600">{error}</p>}
        </div>
    );
}

const inputCls = (error) => `h-12 w-full rounded-xl border bg-white px-4 text-[15px] text-stone-900 placeholder:text-stone-400 focus:border-stone-900 focus:ring-0 focus:outline-none ${error ? 'border-red-500' : 'border-stone-300'}`;

function Input({ label, error, optional, ...props }) {
    const id = useId();
    return (
        <Field label={label} error={error} optional={optional} htmlFor={id}>
            <input id={id} className={inputCls(error)} {...props} />
        </Field>
    );
}

/** Large tappable radio card. */
function Choice({ name, value, checked, onChange, icon: Icon, title, text, aside }) {
    return (
        <label className={`flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-colors ${checked ? 'border-stone-900 bg-stone-50 ring-1 ring-stone-900' : 'border-stone-200 hover:border-stone-400'}`}>
            <input type="radio" name={name} value={value} checked={checked} onChange={() => onChange(value)} className="sr-only" />
            <span className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${checked ? 'border-stone-900 bg-stone-900 text-white' : 'border-stone-300'}`}>{checked && <Check size={12} strokeWidth={3} />}</span>
            <span className="min-w-0 flex-1">
                <span className="flex items-center justify-between gap-2">
                    <span className="flex items-center gap-2 text-sm font-semibold text-stone-900">
                        {Icon && <Icon size={17} />} {title}
                    </span>
                    {aside && <span className="text-sm font-medium text-stone-600">{aside}</span>}
                </span>
                {text && <span className="mt-1 block text-[13px] leading-relaxed text-stone-500">{text}</span>}
            </span>
        </label>
    );
}

function Section({ step, title, children }) {
    return (
        <section className="rounded-3xl border border-stone-200 p-5 sm:p-6">
            <h2 className="flex items-center gap-3 text-lg font-semibold text-stone-900">
                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-on-brand">{step}</span>
                {title}
            </h2>
            <div className="mt-5 space-y-4">{children}</div>
        </section>
    );
}

function SelectField({ label, error, optional, children, ...props }) {
    const id = useId();
    return (
        <Field label={label} error={error} optional={optional} htmlFor={id}>
            <div className="relative">
                <select id={id} className={`${inputCls(error)} appearance-none pr-10`} {...props}>
                    {children}
                </select>
                <ChevronDown size={18} className="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-stone-500" aria-hidden />
            </div>
        </Field>
    );
}

const RATE_ICONS = { flat_rate: Truck, free_shipping: Package, local_pickup: Store };

/**
 * Shipping options for the address typed so far, fetched from the server
 * (which also re-checks them when the order is placed). Postcode changes are
 * debounced; stale responses are ignored. `refreshKey` changes when a coupon
 * is applied or removed (it can unlock free shipping).
 */
function useShippingRates(country, state, postcode, initial, refreshKey) {
    const [rates, setRates] = useState(initial);
    const [loading, setLoading] = useState(false);
    const first = useRef(true);
    const latest = useRef(0);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        if (!country) return setRates([]);
        const request = ++latest.current;
        setLoading(true);
        const t = setTimeout(async () => {
            try {
                const params = new URLSearchParams({ country, state: state || '', postcode: postcode || '' });
                const res = await fetch(`/checkout/rates?${params}`, { headers: { Accept: 'application/json' } });
                const json = res.ok ? await res.json() : { rates: [] };
                if (request === latest.current) setRates(json.rates);
            } catch {
                if (request === latest.current) setRates([]);
            } finally {
                if (request === latest.current) setLoading(false);
            }
        }, 350);
        return () => clearTimeout(t);
    }, [country, state, postcode, refreshKey]);

    return { rates, loading };
}

export default function Checkout({ items, subtotal, discount = 0, coupon, couponsEnabled, couponNotice, countries, states, defaultCountry, shippingConfigured, initialRates, paymentMethods, money }) {
    const form = useForm({
        customer_name: '',
        customer_phone: '',
        customer_email: '',
        country: defaultCountry,
        state: '',
        city: '',
        address: '',
        postcode: '',
        shipping_method: initialRates[0]?.id ?? '',
        notes: '',
        payment_method: paymentMethods[0]?.value ?? 'delivery',
    });
    const d = form.data;
    // Clear a field's error as soon as the customer changes it.
    const set = (key, value) => {
        form.setData(key, value);
        if (form.errors[key]) form.clearErrors(key);
    };
    const fmt = (n) => formatMoney(n, money);
    const err = form.errors;

    const countryStates = states[d.country] || null;
    const { rates, loading } = useShippingRates(d.country, d.state, d.postcode, initialRates, `${coupon?.code ?? ''}:${discount}`);

    // A coupon problem from placing the order goes once the coupon is changed.
    useEffect(() => {
        if (form.errors.coupon) form.clearErrors('coupon');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [coupon?.code]);

    // Keep the chosen option if it is still offered for the new address; otherwise pick the first.
    useEffect(() => {
        if (!rates.some((r) => r.id === Number(d.shipping_method))) form.setData('shipping_method', rates[0]?.id ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rates]);

    const rate = rates.find((r) => r.id === Number(d.shipping_method));
    const pickup = rate?.type === 'local_pickup';
    const fee = rate?.cost ?? 0;
    const total = Math.max(0, subtotal - discount) + fee;
    const count = items.reduce((n, i) => n + i.quantity, 0);
    const needsState = !!countryStates && !d.state;

    let shippingLabel;
    if (!shippingConfigured) shippingLabel = 'To be confirmed';
    else if (loading) shippingLabel = 'Calculating…';
    else if (rate) shippingLabel = rate.cost > 0 ? fmt(rate.cost) : 'Free';
    else shippingLabel = needsState ? 'Choose your state' : 'Not available';

    const submit = (e) => {
        e.preventDefault();
        form.post('/checkout', {
            preserveScroll: true,
            // After the re-render, bring the first invalid field into view.
            onError: () => setTimeout(() => document.querySelector('.border-red-500, [data-error]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50),
        });
    };

    return (
        <StoreLayout hideBottomNav hideFloatingWhatsApp>
            <div className="container-page pt-6 pb-16 sm:pt-8">
                <Breadcrumbs items={[{ label: 'Cart', href: '/cart' }, { label: 'Checkout' }]} />
                <h1 className="mt-4 text-3xl font-bold tracking-tight text-stone-950 sm:text-4xl">Checkout</h1>

                <form onSubmit={submit} noValidate className="mt-6 grid gap-6 lg:grid-cols-[1fr_400px] lg:gap-10">
                    <div className="space-y-6">
                        <Section step={1} title="Your details">
                            <Input label="Full name" autoComplete="name" required maxLength={120} value={d.customer_name} onChange={(e) => set('customer_name', e.target.value)} error={err.customer_name} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Input label="Phone number" type="tel" autoComplete="tel" inputMode="tel" required maxLength={40} placeholder="0801 234 5678" value={d.customer_phone} onChange={(e) => set('customer_phone', e.target.value)} error={err.customer_phone} />
                                <Input label="Email" type="email" autoComplete="email" optional maxLength={160} placeholder="For your order confirmation" value={d.customer_email} onChange={(e) => set('customer_email', e.target.value)} error={err.customer_email} />
                            </div>
                        </Section>

                        <Section step={2} title="Delivery">
                            <div className="grid gap-4 sm:grid-cols-2">
                                {countries.length === 1 ? (
                                    <Field label="Country">
                                        <p className="flex h-12 items-center rounded-xl bg-stone-50 px-4 text-[15px] text-stone-900">{countries[0].name}</p>
                                    </Field>
                                ) : (
                                    <SelectField
                                        label="Country"
                                        autoComplete="country"
                                        value={d.country}
                                        onChange={(e) => {
                                            const country = e.target.value;
                                            // A new country resets the state.
                                            form.setData((data) => ({ ...data, country, state: '' }));
                                            form.clearErrors('country', 'state');
                                        }}
                                        error={err.country}
                                    >
                                        {countries.map((c) => (
                                            <option key={c.code} value={c.code}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </SelectField>
                                )}
                                {countryStates ? (
                                    <SelectField label="State" autoComplete="address-level1" value={d.state} onChange={(e) => set('state', e.target.value)} error={err.state}>
                                        <option value="">Choose your state…</option>
                                        {Object.entries(countryStates).map(([code, name]) => (
                                            <option key={code} value={code}>
                                                {name}
                                            </option>
                                        ))}
                                    </SelectField>
                                ) : (
                                    <Input label="State / region" autoComplete="address-level1" optional maxLength={80} value={d.state} onChange={(e) => set('state', e.target.value)} error={err.state} />
                                )}
                            </div>

                            {!pickup && (
                                <>
                                    <Input label="Street address" autoComplete="street-address" required maxLength={500} placeholder="House number, street, landmark" value={d.address} onChange={(e) => set('address', e.target.value)} error={err.address} />
                                    <div className="grid gap-4 sm:grid-cols-[1fr_12rem]">
                                        <Input label="Town / City" autoComplete="address-level2" required maxLength={120} value={d.city} onChange={(e) => set('city', e.target.value)} error={err.city} />
                                        <Input label="Postcode" autoComplete="postal-code" optional maxLength={20} value={d.postcode} onChange={(e) => set('postcode', e.target.value)} error={err.postcode} />
                                    </div>
                                </>
                            )}

                            {shippingConfigured ? (
                                <div data-error={err.shipping_method ? true : undefined}>
                                    <p className="mb-2 flex items-center gap-2 text-sm font-medium text-stone-800">
                                        Delivery option {loading && <Loader2 size={14} className="animate-spin text-stone-400" />}
                                    </p>
                                    {rates.length > 0 ? (
                                        <div className={`space-y-3 transition-opacity ${loading ? 'opacity-60' : ''}`}>
                                            {rates.map((r) => (
                                                <Choice
                                                    key={r.id}
                                                    name="shipping_method"
                                                    value={r.id}
                                                    checked={Number(d.shipping_method) === r.id}
                                                    onChange={(v) => set('shipping_method', v)}
                                                    icon={RATE_ICONS[r.type]}
                                                    title={r.title}
                                                    aside={r.cost_label}
                                                    text={r.type === 'local_pickup' ? r.address || 'We will send you the pickup details.' : null}
                                                />
                                            ))}
                                        </div>
                                    ) : (
                                        !loading && (
                                            <p className="flex items-start gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                                <Info size={17} className="mt-0.5 shrink-0" />
                                                {needsState ? 'Choose your state to see delivery options.' : 'Sorry, we don’t deliver to this address yet. Check your country and state, or message us.'}
                                            </p>
                                        )
                                    )}
                                    {err.shipping_method && <p className="mt-1.5 text-xs font-medium text-red-600">{err.shipping_method}</p>}
                                </div>
                            ) : (
                                <p className="text-sm text-stone-500">We will confirm the delivery fee with you after you order.</p>
                            )}

                            <Field label="Order note" optional error={err.notes}>
                                <textarea rows={2} maxLength={1000} className={`${inputCls(err.notes)} h-auto py-3`} placeholder="Anything we should know?" value={d.notes} onChange={(e) => set('notes', e.target.value)} />
                            </Field>
                        </Section>

                        <Section step={3} title="Payment">
                            <div className="space-y-3">
                                {paymentMethods.map((m) => (
                                    <Choice key={m.value} name="payment_method" value={m.value} checked={d.payment_method === m.value} onChange={(v) => set('payment_method', v)} icon={PAYMENT_INFO[m.value]?.icon} title={m.label} text={PAYMENT_INFO[m.value]?.text} />
                                ))}
                            </div>
                            {err.payment_method && <p className="text-xs font-medium text-red-600">{err.payment_method}</p>}
                        </Section>
                    </div>

                    <aside className="lg:sticky lg:top-28 lg:self-start">
                        <div className="rounded-3xl border border-stone-200 p-5 sm:p-6">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-stone-900">Order summary</h2>
                                <Link href="/cart" className="text-sm font-medium text-stone-600 underline-offset-2 hover:underline">
                                    Edit cart
                                </Link>
                            </div>
                            <ul className="mt-4 max-h-80 space-y-3 overflow-y-auto">
                                {items.map((i) => (
                                    <li key={i.key} className="flex items-center gap-3">
                                        <span className="relative h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-stone-100">
                                            <Img image={i.image} sizes="56px" className="h-full w-full object-cover" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="line-clamp-2 text-sm text-stone-800">{i.name}</span>
                                            {i.variant && <span className="block text-xs font-medium text-stone-600">{i.variant}</span>}
                                            <span className="text-xs text-stone-500">
                                                {i.quantity} × {i.unit_price}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-sm font-semibold text-stone-900">{i.line_total}</span>
                                    </li>
                                ))}
                            </ul>
                            {(couponsEnabled || couponNotice || err.coupon) && (
                                <div className="mt-5 border-t border-stone-100 pt-4">
                                    <CouponBox coupon={coupon} notice={couponNotice} error={err.coupon} />
                                </div>
                            )}
                            <dl className="mt-5 space-y-2 border-t border-stone-100 pt-4 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-stone-600">
                                        Subtotal ({count} {count === 1 ? 'item' : 'items'})
                                    </dt>
                                    <dd className="font-medium text-stone-900">{fmt(subtotal)}</dd>
                                </div>
                                {coupon && (
                                    <div className="flex justify-between gap-3">
                                        <dt className="min-w-0 truncate text-stone-600">Coupon · {coupon.code}</dt>
                                        <dd className="shrink-0 font-medium text-emerald-700">{discount > 0 ? `−${fmt(discount)}` : 'Applied'}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between gap-3">
                                    <dt className="min-w-0 truncate text-stone-600">{rate ? rate.title : 'Delivery'}</dt>
                                    <dd className={`shrink-0 ${fee > 0 ? 'font-medium text-stone-900' : 'text-stone-500'}`}>{shippingLabel}</dd>
                                </div>
                                <div className="flex items-baseline justify-between border-t border-stone-100 pt-3">
                                    <dt className="text-base font-semibold text-stone-900">Total</dt>
                                    <dd className="text-2xl font-bold tracking-tight text-stone-950">{fmt(total)}</dd>
                                </div>
                            </dl>

                            {Object.keys(err).length > 0 && <p className="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">Please check the highlighted fields.</p>}

                            <button type="submit" disabled={form.processing || (shippingConfigured && (loading || !rate))} className="btn-primary mt-5 h-13 w-full text-base">
                                {form.processing ? <Loader2 size={18} className="animate-spin" /> : <Lock size={16} />}
                                Place order · {fmt(total)}
                            </button>
                            <p className="mt-3 text-center text-xs text-stone-500">No card details needed. You can review your order on the next page.</p>
                        </div>
                    </aside>
                </form>
            </div>
        </StoreLayout>
    );
}
