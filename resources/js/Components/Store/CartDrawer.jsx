import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, ShoppingCart, Trash2, Truck, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { announceCartState, onOpenCart, updateCartItem } from '@/lib/cart';
import Img from './Img';
import QuantityStepper from './QuantityStepper';

function Line({ item, highlight }) {
    const [busy, setBusy] = useState(false);
    const opts = { onStart: () => setBusy(true), onFinish: () => setBusy(false) };

    return (
        <li className={`flex gap-3 rounded-2xl p-2 transition-colors duration-700 ${highlight ? 'bg-emerald-50' : ''} ${busy ? 'opacity-60' : ''}`}>
            <Link href={item.url} className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-stone-100">
                <Img image={item.image} sizes="80px" className="h-full w-full object-cover" />
            </Link>
            <div className="flex min-w-0 flex-1 flex-col">
                <div className="flex items-start justify-between gap-2">
                    <Link href={item.url} className="line-clamp-2 text-sm leading-snug font-medium text-stone-900 hover:underline">
                        {item.name}
                    </Link>
                    <button type="button" onClick={() => updateCartItem(item.key, 0, opts)} disabled={busy} className="-mt-1 -mr-1 shrink-0 rounded-full p-1.5 text-stone-400 hover:bg-red-50 hover:text-red-600" aria-label={`Remove ${item.name}`}>
                        <Trash2 size={15} />
                    </button>
                </div>
                {item.variant && <p className="mt-0.5 text-xs font-medium text-stone-700">{item.variant}</p>}
                <p className="mt-0.5 text-xs text-stone-500">{item.unit_price}</p>
                <div className="mt-auto flex items-center justify-between pt-2">
                    <QuantityStepper value={item.quantity} onChange={(q) => updateCartItem(item.key, q, opts)} size="sm" disabled={busy} label={`Quantity of ${item.name}`} />
                    <span className="text-sm font-semibold text-stone-900">{item.line_total}</span>
                </div>
            </div>
        </li>
    );
}

/**
 * Slide-in cart. Opens when something is added (so the customer can keep
 * shopping or check out straight away) and from the header cart icon.
 */
export default function CartDrawer() {
    const { props, url } = usePage();
    const cart = props.cart;
    const added = props.flash?.cart_added;
    const [open, setOpen] = useState(false);
    const [justAdded, setJustAdded] = useState(null);
    const closeRef = useRef(null);
    const returnFocus = useRef(null);

    const show = useCallback((addedItem = null) => {
        returnFocus.current = document.activeElement;
        setJustAdded(addedItem);
        setOpen(true);
    }, []);
    const close = useCallback(() => setOpen(false), []);

    // Close when the visitor navigates to another page. Declared before the
    // "added" effect so an add that lands on a new URL still opens the drawer.
    useEffect(() => setOpen(false), [url]);
    useEffect(() => onOpenCart(() => show()), [show]);
    useEffect(() => {
        if (added) show(added);
    }, [added, show]);

    useEffect(() => announceCartState(open), [open]);

    useEffect(() => {
        if (!open) {
            returnFocus.current?.focus?.();
            return;
        }
        closeRef.current?.focus();
        document.body.style.overflow = 'hidden';
        const esc = (e) => e.key === 'Escape' && close();
        window.addEventListener('keydown', esc);
        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', esc);
        };
    }, [open, close]);

    if (!cart) return null;
    const empty = cart.items.length === 0;
    const free = cart.freeDelivery;

    return (
        <div className={`fixed inset-0 z-50 ${open ? '' : 'pointer-events-none'}`} aria-hidden={!open} inert={!open ? true : undefined}>
            <div className={`absolute inset-0 bg-stone-950/40 backdrop-blur-[2px] transition-opacity duration-300 ${open ? 'opacity-100' : 'opacity-0'}`} onClick={close} />

            <aside
                role="dialog"
                aria-modal="true"
                aria-label="Your cart"
                className={`absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] motion-reduce:transition-none ${open ? 'translate-x-0' : 'translate-x-full'}`}
            >
                <header className="flex h-16 shrink-0 items-center justify-between border-b border-stone-100 px-5">
                    <h2 className="flex items-center gap-2 text-lg font-semibold text-stone-900">
                        Your cart
                        {cart.count > 0 && <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">{cart.count}</span>}
                    </h2>
                    <button ref={closeRef} type="button" onClick={close} className="rounded-full p-2 text-stone-500 hover:bg-stone-100 hover:text-stone-900" aria-label="Close cart">
                        <X size={20} />
                    </button>
                </header>

                {justAdded && !empty && (
                    <p className="flex shrink-0 items-center gap-2 bg-emerald-50 px-5 py-2.5 text-sm text-emerald-900" role="status">
                        <CheckCircle2 size={17} className="shrink-0 text-emerald-600" />
                        <span className="min-w-0 truncate">
                            <strong className="font-semibold">{justAdded.name}</strong>
                            {justAdded.variant && ` (${justAdded.variant})`} added
                        </span>
                    </p>
                )}

                {empty ? (
                    <div className="flex flex-1 flex-col items-center justify-center px-6 text-center">
                        <span className="flex h-16 w-16 items-center justify-center rounded-full bg-stone-100 text-stone-500">
                            <ShoppingCart size={28} strokeWidth={1.6} />
                        </span>
                        <p className="mt-4 text-base font-semibold text-stone-900">Your cart is empty</p>
                        <p className="mt-1 text-sm text-stone-500">Add products and they will show up here.</p>
                        <Link href="/products" className="btn-primary mt-6 h-11 px-6">
                            Start shopping
                        </Link>
                    </div>
                ) : (
                    <>
                        {free && (
                            <div className="shrink-0 border-b border-stone-100 px-5 py-3">
                                <p className="flex items-center gap-2 text-[13px] text-stone-700">
                                    <Truck size={16} className="shrink-0" />
                                    {free.remaining ? (
                                        <span>
                                            Add <strong className="text-stone-900">{free.remaining}</strong> more for free delivery
                                        </span>
                                    ) : (
                                        <span className="font-medium text-emerald-700">You qualify for free delivery!</span>
                                    )}
                                </p>
                                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100">
                                    <div className={`h-full rounded-full transition-[width] duration-500 ${free.remaining ? 'bg-brand' : 'bg-emerald-500'}`} style={{ width: `${free.progress}%` }} />
                                </div>
                            </div>
                        )}

                        <ul className="flex-1 space-y-1 overflow-y-auto overscroll-contain px-3 py-3">
                            {cart.items.map((item) => (
                                <Line key={item.key} item={item} highlight={(justAdded?.line ?? String(justAdded?.id)) === item.key} />
                            ))}
                        </ul>

                        <footer className="shrink-0 space-y-3 border-t border-stone-100 bg-white px-5 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                            <div className="flex items-baseline justify-between">
                                <span className="text-sm text-stone-600">Subtotal</span>
                                <span className="text-xl font-bold tracking-tight text-stone-950">{cart.subtotal}</span>
                            </div>
                            <p className="-mt-2 text-xs text-stone-500">Delivery is calculated at checkout.</p>
                            <Link href="/checkout" className="btn-primary h-12 w-full text-base">
                                Checkout <ArrowRight size={17} />
                            </Link>
                            <div className="grid grid-cols-2 gap-2">
                                <button type="button" onClick={close} className="btn-outline h-11">
                                    Keep shopping
                                </button>
                                <Link href="/cart" className="btn h-11 border border-transparent text-stone-700 hover:bg-stone-100">
                                    View cart
                                </Link>
                            </div>
                        </footer>
                    </>
                )}
            </aside>
        </div>
    );
}
