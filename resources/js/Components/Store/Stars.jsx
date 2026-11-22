import { Star } from 'lucide-react';
import { useState } from 'react';

/** Read-only stars (supports halves via a clipped overlay). */
export function Stars({ value = 0, size = 16, className = '' }) {
    const pct = Math.max(0, Math.min(100, (value / 5) * 100));
    const row = (cls) => (
        <span className={`flex ${cls}`}>
            {[0, 1, 2, 3, 4].map((i) => (
                <Star key={i} size={size} className="shrink-0" fill="currentColor" strokeWidth={0} />
            ))}
        </span>
    );
    return (
        <span className={`relative inline-flex ${className}`} role="img" aria-label={`${Number(value).toFixed(1)} out of 5 stars`}>
            {row('text-stone-200')}
            <span className="absolute inset-y-0 left-0 overflow-hidden" style={{ width: `${pct}%` }} aria-hidden>
                {row('text-amber-400')}
            </span>
        </span>
    );
}

/** "★★★★☆ 4.5 (12)" for product cards. */
export function RatingLine({ rating, className = '' }) {
    if (!rating) return null;
    return (
        <span className={`flex items-center gap-1.5 text-xs text-stone-500 ${className}`}>
            <Stars value={rating.average} size={13} />
            <span>
                {rating.average.toFixed(1)} ({rating.count})
            </span>
        </span>
    );
}

const LABELS = ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'];

/** Tap-to-rate stars (keyboard: arrow keys, as a radio group). */
export function StarInput({ value, onChange, error }) {
    const [hover, setHover] = useState(0);
    const shown = hover || value;
    return (
        <div>
            <div role="radiogroup" aria-label="Your rating" className="flex items-center gap-1" onMouseLeave={() => setHover(0)}>
                {[1, 2, 3, 4, 5].map((n) => (
                    <button
                        key={n}
                        type="button"
                        role="radio"
                        aria-checked={value === n}
                        aria-label={`${n} star${n === 1 ? '' : 's'}: ${LABELS[n]}`}
                        onClick={() => onChange(n)}
                        onMouseEnter={() => setHover(n)}
                        className="rounded-md p-0.5 transition active:scale-90"
                    >
                        <Star size={30} className={n <= shown ? 'text-amber-400' : 'text-stone-300'} fill="currentColor" strokeWidth={0} />
                    </button>
                ))}
                <span className="ml-2 text-sm font-medium text-stone-600">{LABELS[shown]}</span>
            </div>
            {error && <p className="mt-1 text-xs font-medium text-red-600">{error}</p>}
        </div>
    );
}
