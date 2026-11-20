import { Link, router } from '@inertiajs/react';
import { ArrowRight, Info, ShoppingCart, Trash2, Truck } from 'lucide-react';
import { useState } from 'react';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import Img from '@/Components/Store/Img';
import QuantityStepper from '@/Components/Store/QuantityStepper';
import StoreLayout from '@/Layouts/StoreLayout';

function CartLine({ item }) {
    const [busy, setBusy] = useState(false);
    const opts = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) };
    const setQuantity = (quantity) => router.patch(`/cart/${item.id}`, { quantity }, opts);
    const remove = () => router.delete(`/cart/${item.id}`, opts);

    return (
        <li className={`flex gap-4 py-5 transition-opacity ${busy ? 'opacity-60' : ''}`}>
            <Link href={item.url} className="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-stone-100 sm:h-28 sm:w-28">
                <Img image={item.image} sizes="112px" className="h-full w-full object-cover" />
            </Link>
            <div className="flex min-w-0 flex-1 flex-col">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        {item.category && <p className="text-[11px] font-medium tracking-wider text-stone-500 uppercase">{item.category}</p>}
                        <Link href={item.url} className="mt-0.5 line-clamp-2 text-sm font-medium text-stone-900 hover:underline sm:text-base">
                            {item.name}
                        </Link>
                        <p className="mt-1 text-sm text-stone-500">{item.unit_price}</p>
                        {item.availability === 'pre_order' && <p className="mt-1 text-xs font-medium text-violet-700">{item.availability_label}</p>}
                    </div>
                    <p className="shrink-0 text-sm font-bold text-stone-900 sm:text-base">{item.line_total}</p>
                </div>
                <div className="mt-auto flex items-center justify-between pt-3">
                    <QuantityStepper value={item.quantity} onChange={setQuantity} min={1} size="sm" disabled={busy} label={`Quantity of ${item.name}`} />
                    <button type="button" onClick={remove} disabled={busy} className="inline-flex items-center gap-1.5 rounded-full px-2 py-1.5 text-xs font-medium text-stone-500 hover:bg-red-50 hover:text-red-700">
                        <Trash2 size={15} /> Remove
                    </button>
                </div>
            </div>
        </li>
    );
}

function FreeDelivery({ info }) {
    if (!info) return null;
    return (
        <div className="rounded-2xl bg-surface px-4 py-3 text-sm text-on-surface">
            <p className="flex items-center gap-2 font-medium">
                <Truck size={17} className="shrink-0" />
                {info.remaining ? (
                    <span>
                        Add <strong>{info.remaining}</strong> more for free delivery
                    </span>
                ) : (
                    <span>You qualify for free delivery!</span>
                )}
            </p>
            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/70">
                <div className="h-full rounded-full bg-brand transition-[width] duration-500" style={{ width: `${info.progress}%` }} />
            </div>
        </div>
    );
}

export default function Cart({ items, subtotal, freeDelivery, notice }) {
    const count = items.reduce((n, i) => n + i.quantity, 0);

    return (
        <StoreLayout>
            <div className="container-page pt-6 pb-16 sm:pt-8">
                <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: 'Cart' }]} />
                <h1 className="mt-4 text-3xl font-bold tracking-tight text-stone-950 sm:text-4xl">Your cart</h1>

                {notice && (
                    <p className="mt-5 flex items-start gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <Info size={17} className="mt-0.5 shrink-0" /> {notice}
                    </p>
                )}

                {items.length === 0 ? (
                    <div className="mt-8 flex flex-col items-center rounded-3xl border border-dashed border-stone-300 bg-stone-50/60 px-6 py-16 text-center">
                        <span className="flex h-14 w-14 items-center justify-center rounded-full bg-white text-stone-500 shadow-sm">
                            <ShoppingCart size={26} strokeWidth={1.6} />
                        </span>
                        <h2 className="mt-5 text-lg font-semibold text-stone-900">Your cart is empty</h2>
                        <p className="mt-2 max-w-md text-sm text-stone-600">Browse the shop and add the products you like.</p>
                        <Link href="/products" className="btn-primary mt-6 h-12 px-6">
                            Start shopping <ArrowRight size={17} />
                        </Link>
                    </div>
                ) : (
                    <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_380px] lg:gap-12">
                        <ul className="divide-y divide-stone-100 border-y border-stone-100">
                            {items.map((item) => (
                                <CartLine key={item.id} item={item} />
                            ))}
                        </ul>

                        <aside className="lg:sticky lg:top-28 lg:self-start">
                            <div className="space-y-4 rounded-3xl border border-stone-200 p-5 sm:p-6">
                                <h2 className="text-lg font-semibold text-stone-900">Order summary</h2>
                                <dl className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <dt className="text-stone-600">
                                            Subtotal ({count} {count === 1 ? 'item' : 'items'})
                                        </dt>
                                        <dd className="font-semibold text-stone-900">{subtotal}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-stone-600">Delivery</dt>
                                        <dd className="text-stone-500">Calculated at checkout</dd>
                                    </div>
                                </dl>
                                <FreeDelivery info={freeDelivery} />
                                <Link href="/checkout" className="btn-primary h-12 w-full text-base">
                                    Checkout <ArrowRight size={17} />
                                </Link>
                                <Link href="/products" className="block text-center text-sm font-medium text-stone-600 hover:text-stone-900">
                                    Continue shopping
                                </Link>
                            </div>
                        </aside>
                    </div>
                )}
            </div>
        </StoreLayout>
    );
}
