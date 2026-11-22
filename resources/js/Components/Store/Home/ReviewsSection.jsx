import { Link } from '@inertiajs/react';
import { BadgeCheck, Quote } from 'lucide-react';
import SectionHeading from '../SectionHeading';
import { Stars } from '../Stars';

function Card({ review, hidden = false }) {
    return (
        <figure className="flex w-[300px] shrink-0 flex-col rounded-3xl border border-stone-200/80 bg-white p-5 shadow-sm sm:w-[340px]" aria-hidden={hidden || undefined}>
            <div className="flex items-center justify-between">
                <Stars value={review.rating} size={16} />
                <Quote size={20} className="text-stone-200" aria-hidden />
            </div>
            {review.title && <p className="mt-3 text-[15px] font-semibold text-stone-900">{review.title}</p>}
            <blockquote className="mt-2 line-clamp-5 flex-1 text-sm leading-relaxed text-stone-700">“{review.body}”</blockquote>
            <figcaption className="mt-4 border-t border-stone-100 pt-4">
                <span className="flex items-center gap-1.5 text-sm font-semibold text-stone-900">
                    {review.name}
                    {review.verified && <BadgeCheck size={15} className="text-emerald-600" aria-label="Verified purchase" />}
                </span>
                <Link href={review.product.url} tabIndex={hidden ? -1 : undefined} className="mt-2 flex items-center gap-2.5 text-xs text-stone-500 hover:text-stone-900">
                    {review.product.image && <img src={review.product.image} alt="" loading="lazy" className="h-8 w-8 rounded-lg object-cover" />}
                    <span className="line-clamp-1">{review.product.name}</span>
                </Link>
            </figcaption>
        </figure>
    );
}

/**
 * "What our customers say": a slow, endless row of 4–5 star reviews (pauses on
 * hover or focus). People who prefer less motion get a swipeable row instead.
 */
export default function ReviewsSection({ reviews }) {
    if (!reviews?.items?.length) return null;
    const seconds = Math.max(30, reviews.items.length * 7);

    return (
        <section className="overflow-hidden bg-surface py-14 sm:py-20" aria-label="Customer reviews">
            <div className="container-page">
                <SectionHeading
                    title="What our customers say"
                    subtitle={
                        <span className="inline-flex flex-wrap items-center gap-2">
                            <Stars value={reviews.average} size={16} />
                            <span>
                                Rated {reviews.average.toFixed(1)} out of 5 from {reviews.total} {reviews.total === 1 ? 'review' : 'reviews'}
                            </span>
                        </span>
                    }
                />
            </div>

            {/* Moving row (two copies for a seamless loop). */}
            <div className="group relative motion-reduce:hidden">
                <div className="flex w-max gap-4 px-2 group-hover:[animation-play-state:paused] group-focus-within:[animation-play-state:paused]" style={{ animation: `marquee ${seconds}s linear infinite` }}>
                    {reviews.items.map((r) => (
                        <Card key={`a${r.id}`} review={r} />
                    ))}
                    {reviews.items.map((r) => (
                        <Card key={`b${r.id}`} review={r} hidden />
                    ))}
                </div>
                <div className="pointer-events-none absolute inset-y-0 left-0 w-10 bg-gradient-to-r from-[var(--color-surface,#f5f5f4)] to-transparent sm:w-24" aria-hidden />
                <div className="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-[var(--color-surface,#f5f5f4)] to-transparent sm:w-24" aria-hidden />
            </div>

            {/* Reduced motion: swipe instead. */}
            <div className="no-scrollbar hidden snap-x gap-4 overflow-x-auto px-4 motion-reduce:flex">
                {reviews.items.map((r) => (
                    <div key={r.id} className="snap-start">
                        <Card review={r} />
                    </div>
                ))}
            </div>
        </section>
    );
}
