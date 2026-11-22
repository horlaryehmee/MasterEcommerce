import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { BadgeCheck, CheckCircle2, Loader2, PenLine, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StarInput, Stars } from './Stars';

function ReviewCard({ review }) {
    return (
        <li className="border-b border-stone-100 py-6 last:border-0">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <Stars value={review.rating} size={15} />
                {review.title && <h3 className="text-[15px] font-semibold text-stone-900">{review.title}</h3>}
            </div>
            <p className="mt-2 text-[15px] leading-relaxed whitespace-pre-line text-stone-700">{review.body}</p>
            <p className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-500">
                <span className="font-semibold text-stone-800">{review.name}</span>
                {review.verified && (
                    <span className="inline-flex items-center gap-1 font-medium text-emerald-700">
                        <BadgeCheck size={14} /> Verified purchase
                    </span>
                )}
                <span>· {review.date}</span>
            </p>
        </li>
    );
}

/** Star breakdown; each row filters the list to that rating (click again to clear). */
function Breakdown({ breakdown, count, rating, onPick }) {
    return (
        <ul className="space-y-0.5">
            {[5, 4, 3, 2, 1].map((r) => {
                const n = breakdown[r] ?? 0;
                const pct = count ? Math.round((n / count) * 100) : 0;
                const active = rating === r;
                return (
                    <li key={r}>
                        <button
                            type="button"
                            disabled={!n}
                            onClick={() => onPick(active ? 0 : r)}
                            aria-pressed={active}
                            aria-label={`${r} star reviews: ${n}`}
                            className={`flex w-full items-center gap-2 rounded-lg px-1.5 py-1 text-xs text-stone-600 transition enabled:hover:bg-stone-100 disabled:cursor-default disabled:opacity-60 ${active ? 'bg-stone-100 font-semibold text-stone-900' : ''}`}
                        >
                            <span className="w-10 shrink-0 text-left">{r} star</span>
                            <span className="h-2 flex-1 overflow-hidden rounded-full bg-stone-200/70">
                                <span className="block h-full rounded-full bg-amber-400" style={{ width: `${pct}%` }} />
                            </span>
                            <span className="w-10 shrink-0 text-right tabular-nums">{n.toLocaleString()}</span>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

/** The write-a-review form. `website` is a hidden honeypot; `form_token` times the form. */
function ReviewForm({ slug, token, onClose }) {
    const form = useForm({ rating: 0, name: '', email: '', title: '', body: '', website: '', form_token: token });
    const field = 'h-11 w-full rounded-xl border border-stone-300 bg-white px-3.5 text-[15px] text-stone-900 focus:border-stone-900 focus:ring-0 focus:outline-none';
    const err = (k) => form.errors[k] && <p className="mt-1 text-xs font-medium text-red-600">{form.errors[k]}</p>;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/products/${slug}/reviews`, { preserveScroll: true, onSuccess: onClose });
            }}
            className="space-y-4 rounded-3xl border border-stone-200 bg-white p-5 sm:p-6"
        >
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-stone-900">Write a review</h3>
                <button type="button" onClick={onClose} className="rounded-full p-1.5 text-stone-500 hover:bg-stone-100" aria-label="Close">
                    <X size={18} />
                </button>
            </div>

            <StarInput value={form.data.rating} onChange={(v) => form.setData('rating', v)} error={form.errors.rating} />

            {/* Honeypot: hidden from people (and screen readers); bots fill it in. */}
            <div aria-hidden="true" style={{ position: 'absolute', left: '-10000px', width: 1, height: 1, overflow: 'hidden' }}>
                <label>
                    Website
                    <input type="text" name="website" tabIndex={-1} autoComplete="off" value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} />
                </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                    <span className="mb-1.5 block text-sm font-medium text-stone-800">Your name</span>
                    <input className={field} maxLength={60} autoComplete="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    {err('name')}
                </label>
                <label className="block">
                    <span className="mb-1.5 block text-sm font-medium text-stone-800">
                        Email <span className="font-normal text-stone-400">(optional, never shown)</span>
                    </span>
                    <input type="email" className={field} maxLength={160} autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    {err('email')}
                    <span className="mt-1 block text-xs text-stone-500">Use the email you ordered with to get a “Verified purchase” badge.</span>
                </label>
            </div>
            <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-stone-800">
                    Title <span className="font-normal text-stone-400">(optional)</span>
                </span>
                <input className={field} maxLength={100} placeholder="Sum it up in a few words" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                {err('title')}
            </label>
            <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-stone-800">Your review</span>
                <textarea
                    rows={5}
                    maxLength={1500}
                    required
                    placeholder="What did you like or dislike? How are you using it?"
                    className="w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 text-[15px] text-stone-900 focus:border-stone-900 focus:ring-0 focus:outline-none"
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                />
                <span className="mt-1 flex justify-between text-xs text-stone-500">
                    <span>{form.errors.body ? <span className="font-medium text-red-600">{form.errors.body}</span> : 'Please don’t include links or contact details.'}</span>
                    <span className="tabular-nums">{form.data.body.length}/1500</span>
                </span>
            </label>
            <button type="submit" disabled={form.processing} className="btn-primary h-12 w-full text-base sm:w-auto sm:px-8">
                {form.processing && <Loader2 size={18} className="animate-spin" />} Submit review
            </button>
        </form>
    );
}

/** Reviews on the product page: summary, list (sort + "Show more") and the form. */
export default function ProductReviews({ reviews, slug }) {
    const [items, setItems] = useState(reviews.items);
    const [next, setNext] = useState(reviews.next);
    const [sort, setSort] = useState('newest');
    const [rating, setRating] = useState(0);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [writing, setWriting] = useState(false);
    const [thanks, setThanks] = useState(reviews.submitted);
    const latest = useRef(0);

    useEffect(() => setThanks(reviews.submitted), [reviews.submitted]);

    // When the reviews themselves change (e.g. a new one is published), start again from the top.
    // Other page reloads (adding to cart…) keep what the shopper has scrolled through.
    const signature = `${reviews.count}:${reviews.items[0]?.id ?? ''}`;
    const first = useRef(signature);
    useEffect(() => {
        if (first.current === signature) return;
        first.current = signature;
        setItems(reviews.items);
        setNext(reviews.next);
        setSort('newest');
        setRating(0);
    }, [signature]); // eslint-disable-line react-hooks/exhaustive-deps

    const lastArgs = useRef({});
    /** cursor = null loads the first page (new sort / filter); otherwise appends the next page. */
    const load = ({ cursor = null, newSort = sort, newRating = rating } = {}) => {
        lastArgs.current = { cursor, newSort, newRating };
        const request = ++latest.current; // only the most recent request may update the list
        setLoading(true);
        setFailed(false);
        axios
            .get(`/products/${slug}/reviews`, { params: { sort: newSort, rating: newRating || undefined, cursor: cursor || undefined } })
            .then(({ data }) => {
                if (request !== latest.current) return;
                setItems((prev) => (cursor ? [...prev, ...data.reviews.filter((r) => !prev.some((p) => p.id === r.id))] : data.reviews));
                setNext(data.next);
            })
            .catch(() => request === latest.current && setFailed(true))
            .finally(() => request === latest.current && setLoading(false));
    };

    const total = rating ? (reviews.breakdown[rating] ?? 0) : reviews.count;
    const pickRating = (r) => {
        setRating(r);
        load({ newRating: r });
    };

    return (
        <section id="reviews" className="mt-14 border-t border-stone-100 pt-10 sm:mt-16">
            <div className="grid gap-10 lg:grid-cols-[300px_1fr] lg:gap-14">
                <div>
                    <h2 className="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Customer reviews</h2>
                    {reviews.count > 0 ? (
                        <>
                            <div className="mt-4 flex items-center gap-3">
                                <span className="text-4xl font-bold tracking-tight text-stone-950">{reviews.average.toFixed(1)}</span>
                                <span>
                                    <Stars value={reviews.average} size={18} />
                                    <span className="mt-0.5 block text-sm text-stone-500">
                                        {reviews.count} {reviews.count === 1 ? 'review' : 'reviews'}
                                    </span>
                                </span>
                            </div>
                            <div className="mt-5">
                                <Breakdown breakdown={reviews.breakdown} count={reviews.count} rating={rating} onPick={pickRating} />
                            </div>
                        </>
                    ) : (
                        <p className="mt-3 text-sm text-stone-600">No reviews yet. Be the first to share what you think.</p>
                    )}
                    {!writing && (
                        <button type="button" onClick={() => (setWriting(true), setThanks(null))} className="btn-outline mt-6 h-11 w-full">
                            <PenLine size={16} /> Write a review
                        </button>
                    )}
                </div>

                <div className="min-w-0">
                    {thanks && (
                        <p className="mb-6 flex items-start gap-2 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                            <CheckCircle2 size={18} className="mt-0.5 shrink-0" />
                            {thanks === 'published' ? 'Thank you! Your review is now live.' : 'Thank you! Your review has been sent and will appear once it’s approved.'}
                        </p>
                    )}
                    {writing && (
                        <div className="mb-8">
                            <ReviewForm slug={slug} token={reviews.form_token} onClose={() => setWriting(false)} />
                        </div>
                    )}
                    {reviews.count > 0 && (
                        <>
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 pb-3">
                                <span className="flex flex-wrap items-center gap-2 text-sm text-stone-500" aria-live="polite">
                                    Showing {Math.min(items.length, total).toLocaleString()} of {total.toLocaleString()}
                                    {rating > 0 && (
                                        <button type="button" onClick={() => pickRating(0)} className="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-800 hover:bg-stone-200">
                                            {rating} star only <X size={12} aria-label="Show all ratings" />
                                        </button>
                                    )}
                                </span>
                                <select
                                    value={sort}
                                    onChange={(e) => {
                                        setSort(e.target.value);
                                        load({ newSort: e.target.value });
                                    }}
                                    className="h-9 rounded-lg border border-stone-200 bg-white px-2 text-sm text-stone-800"
                                    aria-label="Sort reviews"
                                >
                                    {Object.entries(reviews.sorts).map(([v, l]) => (
                                        <option key={v} value={v}>
                                            {l}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <ul className={loading && !next ? 'opacity-60 transition-opacity' : undefined}>
                                {items.map((r) => (
                                    <ReviewCard key={r.id} review={r} />
                                ))}
                            </ul>
                            {!loading && items.length === 0 && <p className="py-8 text-center text-sm text-stone-500">No reviews with this rating yet.</p>}
                            {failed && (
                                <p className="mt-3 text-sm text-red-600" role="alert">
                                    Couldn’t load reviews. Check your connection and try again.
                                </p>
                            )}
                            {(next || failed) && (
                                <button type="button" onClick={() => load(failed ? lastArgs.current : { cursor: next })} disabled={loading} className="btn-outline mt-2 h-11 w-full sm:w-auto sm:px-6">
                                    {loading && <Loader2 size={16} className="animate-spin" />} {failed ? 'Try again' : `Show more reviews (${Math.max(0, total - items.length).toLocaleString()} more)`}
                                </button>
                            )}
                        </>
                    )}
                </div>
            </div>
        </section>
    );
}
