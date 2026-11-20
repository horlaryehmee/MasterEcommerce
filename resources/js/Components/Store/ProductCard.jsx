import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, Loader2, ShoppingBag } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useAddToCart } from '@/lib/cart';
import Img from './Img';
import Reveal from './Reveal';

const AVAILABILITY_STYLES = {
    in_stock: 'bg-emerald-500',
    limited: 'bg-amber-500',
    on_request: 'bg-sky-500',
    pre_order: 'bg-violet-500',
    out_of_stock: 'bg-stone-400',
};

export function AvailabilityLabel({ value, label, className = '' }) {
    if (!label) return null;
    return (
        <span className={`inline-flex items-center gap-1.5 text-xs font-medium text-stone-600 ${className}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${AVAILABILITY_STYLES[value] || 'bg-stone-400'}`} aria-hidden />
            {label}
        </span>
    );
}

export function PriceTag({ price, compareAt, size = 'md' }) {
    const big = size === 'lg';
    return (
        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <span className={`font-bold text-stone-900 ${big ? 'text-3xl tracking-tight' : 'text-[15px] sm:text-base'}`}>{price}</span>
            {compareAt && (
                <span className={`text-stone-400 line-through ${big ? 'text-lg' : 'text-xs sm:text-sm'}`}>
                    <span className="sr-only">Previous price: </span>
                    {compareAt}
                </span>
            )}
        </div>
    );
}


/** Shared quick-add state: busy while posting, then a short "Added" tick. */
function useQuickAdd(product) {
    const { add, busy } = useAddToCart();
    const [done, setDone] = useState(false);

    useEffect(() => {
        if (!done) return;
        const t = setTimeout(() => setDone(false), 1500);
        return () => clearTimeout(t);
    }, [done]);

    return {
        busy: !!busy,
        done,
        props: {
            type: 'button',
            onClick: () => add(product.id, { onSuccess: () => setDone(true) }),
            disabled: !!busy,
            'aria-label': `Add ${product.name} to cart`,
        },
    };
}

function BagIcon({ busy, done, size = 18 }) {
    if (busy) return <Loader2 size={size} className="animate-spin" />;
    if (done) return <Check size={size} strokeWidth={2.5} />;
    return <ShoppingBag size={size} />;
}

// Class names are written out in full (no string building) so Tailwind can find them.

/** Modern: on the image. A round bag button on touch screens; a bar that slides up on hover elsewhere. */
function OverlayButton({ product, forceVisible = false }) {
    const q = useQuickAdd(product);
    return (
        <button
            {...q.props}
            className={`absolute right-2.5 bottom-2.5 z-10 flex h-10 min-w-10 items-center justify-center gap-2 rounded-full px-2.5 shadow-lg shadow-stone-900/15 backdrop-blur-md transition duration-300 ease-[cubic-bezier(.22,.61,.36,1)] active:scale-90 disabled:cursor-wait sm:right-3 sm:bottom-3 [@media(hover:hover)]:left-3 [@media(hover:hover)]:h-11 [@media(hover:hover)]:translate-y-3 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:translate-y-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:focus-visible:translate-y-0 [@media(hover:hover)]:focus-visible:opacity-100 ${
                q.done ? 'bg-emerald-600 text-white' : 'bg-white/95 text-stone-900 hover:bg-brand hover:text-on-brand'
            } ${q.done || forceVisible ? '[@media(hover:hover)]:translate-y-0! [@media(hover:hover)]:opacity-100!' : ''}`}
        >
            <span className="[@media(hover:hover)]:hidden">
                <BagIcon busy={q.busy} done={q.done} />
            </span>
            <span className="hidden items-center gap-2 text-sm font-semibold [@media(hover:hover)]:inline-flex">
                <BagIcon busy={q.busy} done={q.done} size={17} />
                {q.done ? 'Added' : 'Add to cart'}
            </span>
        </button>
    );
}

/** Classic: a full-width button under the details. */
function BlockButton({ product }) {
    const q = useQuickAdd(product);
    return (
        <button {...q.props} className={`relative z-10 mt-3 flex h-10 w-full items-center justify-center gap-2 rounded-full border text-[13px] font-semibold transition active:scale-[0.97] disabled:cursor-wait sm:h-11 sm:text-sm ${q.done ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-stone-900 text-stone-900 hover:bg-stone-900 hover:text-white'}`}>
            <BagIcon busy={q.busy} done={q.done} size={16} />
            {q.done ? 'Added' : 'Add to cart'}
        </button>
    );
}

/** Boxed: a round bag button beside the price. */
function IconButton({ product }) {
    const q = useQuickAdd(product);
    return (
        <button {...q.props} className={`relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition active:scale-90 disabled:cursor-wait ${q.done ? 'bg-emerald-600 text-white' : 'bg-brand text-on-brand hover:opacity-90'}`}>
            <BagIcon busy={q.busy} done={q.done} size={17} />
        </button>
    );
}

function Badges({ product }) {
    return (
        <div className="pointer-events-none absolute top-2.5 left-2.5 flex flex-wrap items-start gap-1.5 sm:top-3 sm:left-3">
            {product.discount_percent > 0 && <span className="rounded-full bg-accent px-2.5 py-1 text-[11px] leading-none font-bold text-on-accent">-{product.discount_percent}%</span>}
            {/* At most two badges: discount, then "New" or "Featured". */}
            {product.is_new_arrival ? (
                <span className="rounded-full bg-white/90 px-2.5 py-1 text-[11px] leading-none font-semibold text-stone-900 backdrop-blur">New</span>
            ) : (
                product.is_featured && <span className="rounded-full bg-brand px-2.5 py-1 text-[11px] leading-none font-semibold text-on-brand">Featured</span>
            )}
        </div>
    );
}

function Picture({ product, eager, soldOut, className }) {
    return (
        <Img
            image={product.image}
            eager={eager}
            sizes="(min-width: 1280px) 300px, (min-width: 1024px) 25vw, (min-width: 768px) 33vw, 50vw"
            className={`h-full w-full object-cover transition-transform duration-700 ease-[cubic-bezier(.22,.61,.36,1)] group-hover:scale-[1.06] ${soldOut ? 'opacity-50 grayscale' : ''} ${className || ''}`}
        />
    );
}

/** Product name. Its link covers the whole card (after:inset-0); buttons sit above it with z-10. */
function Title({ product, rounded = 'after:rounded-3xl', className = '' }) {
    return (
        <h3 className={`line-clamp-2 text-sm leading-snug font-medium text-stone-800 sm:text-[15px] ${className}`}>
            <Link href={product.url} prefetch="hover" className={`underline-offset-4 after:absolute after:inset-0 ${rounded} group-hover:text-stone-950 group-hover:underline focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-accent focus-visible:after:ring-offset-4`}>
                {product.name}
            </Link>
        </h3>
    );
}

function Category({ product }) {
    return product.category ? <p className="text-[11px] font-medium tracking-wider text-stone-500 uppercase">{product.category}</p> : null;
}

function SoldOutChip() {
    return <span className="pointer-events-none absolute right-2.5 bottom-2.5 left-2.5 rounded-full bg-white/95 py-2 text-center text-xs font-semibold text-stone-700 backdrop-blur sm:right-3 sm:bottom-3 sm:left-3">Sold out</span>;
}

function ModernCard({ product, eager, quickAdd, soldOut, note, preview }) {
    return (
        <article className="group relative flex flex-col transition-transform duration-200 ease-out [@media(hover:none)]:active:scale-[0.98]">
            <div className="relative aspect-square overflow-hidden rounded-3xl bg-stone-100 ring-1 ring-stone-900/5 ring-inset">
                <Picture product={product} eager={eager} soldOut={soldOut} />
                <Badges product={product} />
                {soldOut && <SoldOutChip />}
                {quickAdd && <OverlayButton product={product} forceVisible={preview} />}
            </div>
            <div className="mt-3 flex flex-1 flex-col px-1">
                <Category product={product} />
                <Title product={product} className="mt-1" />
                <div className="mt-1.5">
                    <PriceTag price={product.price} compareAt={product.compare_at_price} />
                </div>
                {note && <AvailabilityLabel value={product.availability} label={note} className="mt-1.5" />}
            </div>
        </article>
    );
}

function ClassicCard({ product, eager, quickAdd, soldOut, note }) {
    return (
        <article className="group relative flex flex-col transition-transform duration-200 ease-out [@media(hover:none)]:active:scale-[0.98]">
            <div className="relative aspect-square overflow-hidden rounded-2xl bg-stone-100">
                <Picture product={product} eager={eager} soldOut={soldOut} />
                <Badges product={product} />
            </div>
            <div className="mt-3 flex flex-1 flex-col">
                <Category product={product} />
                <Title product={product} rounded="after:rounded-2xl" className="mt-1" />
                <div className="mt-1.5">
                    <PriceTag price={product.price} compareAt={product.compare_at_price} />
                </div>
                {(note || soldOut) && <AvailabilityLabel value={product.availability} label={soldOut ? 'Out of stock' : note} className="mt-1.5" />}
                <div className="mt-auto">
                    {quickAdd ? (
                        <BlockButton product={product} />
                    ) : (
                        // Not a link itself: the title's link already covers the card.
                        <span className="mt-3 flex h-10 w-full items-center justify-center gap-1.5 rounded-full border border-stone-300 text-[13px] font-semibold text-stone-700 transition-colors group-hover:border-stone-900 group-hover:text-stone-900 sm:h-11 sm:text-sm">
                            View details <ArrowRight size={15} />
                        </span>
                    )}
                </div>
            </div>
        </article>
    );
}

function BoxedCard({ product, eager, quickAdd, soldOut, note }) {
    return (
        <article className="group relative flex flex-col rounded-3xl border border-stone-200 bg-white p-2 transition duration-300 hover:-translate-y-0.5 [@media(hover:none)]:active:scale-[0.98] hover:border-stone-300 hover:shadow-xl hover:shadow-stone-900/5 sm:p-2.5">
            <div className="relative aspect-square overflow-hidden rounded-2xl bg-stone-100">
                <Picture product={product} eager={eager} soldOut={soldOut} />
                <Badges product={product} />
                {soldOut && <SoldOutChip />}
            </div>
            <div className="flex flex-1 flex-col px-1.5 pt-3 pb-1.5">
                <Category product={product} />
                <Title product={product} className="mt-1" />
                {note && <AvailabilityLabel value={product.availability} label={note} className="mt-1.5" />}
                <div className="mt-auto flex items-end justify-between gap-2 pt-3">
                    <PriceTag price={product.price} compareAt={product.compare_at_price} />
                    {quickAdd ? (
                        <IconButton product={product} />
                    ) : (
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-stone-100 text-stone-700 transition-colors group-hover:bg-stone-900 group-hover:text-white" aria-hidden>
                            <ArrowRight size={17} />
                        </span>
                    )}
                </div>
            </div>
        </article>
    );
}

const CARDS = { modern: ModernCard, classic: ClassicCard, boxed: BoxedCard };

/**
 * Product card in the design chosen in Admin → Website Settings → Branding.
 * `variant`, `quickAdd` and `preview` (buttons always visible) are for the admin previews.
 */
export default function ProductCard({ product, eager = false, variant, quickAdd, preview = false }) {
    const { store } = usePage().props;
    const Card = CARDS[variant || store?.card_style] || ModernCard;
    const soldOut = product.availability === 'out_of_stock';
    const note = !soldOut && product.availability !== 'in_stock' ? product.availability_label : null;
    const canQuickAdd = quickAdd ?? (!!store?.shop_enabled && !!product.purchasable);

    return <Card product={product} eager={eager} quickAdd={canQuickAdd} soldOut={soldOut} note={note} preview={preview} />;
}

export function ProductGrid({ products, eagerCount = 0 }) {
    return (
        <div className="grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-6 sm:gap-y-10 md:grid-cols-3 lg:grid-cols-4">
            {products.map((p, i) => (
                <Reveal key={p.id} delay={(i % 4) * 70} className="flex flex-col [&>article]:flex-1">
                    <ProductCard product={p} eager={i < eagerCount} />
                </Reveal>
            ))}
        </div>
    );
}

/**
 * Phones: a swipeable row with the next card peeking in (snaps card by card).
 * Larger screens: the usual grid.
 */
export function ProductRail({ products, label }) {
    return (
        <div
            className="no-scrollbar -mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto overscroll-x-contain px-4 pb-2 sm:mx-0 sm:grid sm:snap-none sm:grid-cols-3 sm:gap-x-6 sm:gap-y-10 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-4"
            role="list"
            aria-label={label}
        >
            {products.map((p, i) => (
                <Reveal key={p.id} role="listitem" delay={(i % 4) * 70} className="flex w-[46%] shrink-0 snap-start flex-col sm:w-auto [&>article]:flex-1">
                    <ProductCard product={p} />
                </Reveal>
            ))}
        </div>
    );
}
