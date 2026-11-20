import { Minus, Plus } from 'lucide-react';

/** − 2 + control. `min` 0 lets the minus button remove an item from the cart. */
export default function QuantityStepper({ value, onChange, min = 1, max = 99, size = 'md', disabled = false, label = 'Quantity' }) {
    const sm = size === 'sm';
    const btn = `flex items-center justify-center text-stone-700 transition-colors hover:bg-stone-100 disabled:opacity-40 disabled:hover:bg-transparent ${sm ? 'h-9 w-9' : 'h-12 w-12'}`;

    return (
        <div className={`inline-flex items-center rounded-full border border-stone-300 bg-white ${disabled ? 'opacity-60' : ''}`} role="group" aria-label={label}>
            <button type="button" className={`${btn} rounded-l-full`} onClick={() => onChange(Math.max(min, value - 1))} disabled={disabled || value <= min} aria-label="Decrease quantity">
                <Minus size={sm ? 15 : 17} />
            </button>
            <span className={`text-center font-semibold text-stone-900 tabular-nums ${sm ? 'w-7 text-sm' : 'w-9 text-base'}`} aria-live="polite">
                {value}
            </span>
            <button type="button" className={`${btn} rounded-r-full`} onClick={() => onChange(Math.min(max, value + 1))} disabled={disabled || value >= max} aria-label="Increase quantity">
                <Plus size={sm ? 15 : 17} />
            </button>
        </div>
    );
}
