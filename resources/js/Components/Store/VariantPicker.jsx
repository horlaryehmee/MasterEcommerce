import { useEffect, useMemo, useState } from 'react';

const matches = (variant, selected, skip = -1) => selected.every((value, i) => i === skip || value === null || variant.values[i] === value);

/** Light colours need a border to be visible on white. */
function isLight(hex) {
    if (!/^#[0-9a-f]{6}$/i.test(hex || '')) return false;
    const n = parseInt(hex.slice(1), 16);
    return (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255 > 0.85;
}

/**
 * Selection state for a variable product. Options with one value are chosen
 * automatically; the others wait for the customer (no silently pre-picked
 * sizes). `?variant=ID` in the address preselects a variation and is kept in
 * step with the choice, so links to a specific size or colour can be shared.
 */
export function useVariantSelection(product) {
    const { options = [], variants = [] } = product;
    const [selected, setSelected] = useState(() => {
        const id = typeof window !== 'undefined' ? Number(new URLSearchParams(window.location.search).get('variant')) : 0;
        const fromUrl = variants.find((v) => v.id === id);
        if (fromUrl) return [...fromUrl.values];
        return options.map((o) => (o.values.length === 1 ? o.values[0].label : null));
    });

    const variant = useMemo(() => (selected.every((v) => v !== null) ? variants.find((v) => matches(v, selected)) || null : null), [selected, variants]);

    useEffect(() => {
        if (!options.length || typeof window === 'undefined') return;
        const url = new URL(window.location.href);
        if (variant) url.searchParams.set('variant', variant.id);
        else url.searchParams.delete('variant');
        if (url.href !== window.location.href) window.history.replaceState(window.history.state, '', url);
    }, [variant, options.length]);

    /**
     * Choose a value. Other choices that no longer make a real combination
     * are cleared, so the customer never lands on something that doesn't exist.
     */
    const choose = (index, value) =>
        setSelected((current) => {
            const next = [...current];
            next[index] = next[index] === value && options[index].values.length > 1 ? null : value;
            if (next[index] !== null && !variants.some((v) => matches(v, next))) {
                // Keep only the other choices that exist together with the new one.
                return next.map((x, j) => (j === index || x === null || variants.some((v) => v.values[index] === next[index] && v.values[j] === x) ? x : null));
            }
            return next;
        });

    const missing = options.filter((_, i) => selected[i] === null).map((o) => o.name);

    // A photo for what's chosen so far: the variation's, or the first match with one (picking a colour shows it).
    const imageIndex = variant?.image_index ?? variants.find((v) => v.image_index !== null && matches(v, selected))?.image_index ?? null;

    return {
        options,
        variants,
        selected,
        choose,
        variant,
        missing,
        imageIndex,
        hasVariants: options.length > 0,
    };
}

/** One row of buttons per option: colour swatches for colours, pills for everything else. */
export default function VariantPicker({ selection, highlightMissing = false, id }) {
    const { options, variants, selected, choose } = selection;

    return (
        <div id={id} className="space-y-5">
            {options.map((option, i) => {
                const swatches = option.values.some((v) => v.color);
                const chosen = selected[i];
                const missing = highlightMissing && chosen === null;
                return (
                    // The key changes on every nudge, so the shake replays each time.
                    <fieldset key={`${option.name}-${missing ? highlightMissing : 0}`} className={missing ? 'motion-safe:animate-[nudge_0.4s_ease-in-out]' : ''}>
                        <legend className="mb-2.5 flex flex-wrap items-baseline gap-x-2 text-sm">
                            <span className="font-semibold text-stone-900">{option.name}</span>
                            {chosen ? (
                                <span className="text-stone-600">{chosen}</span>
                            ) : (
                                <span className={missing ? 'font-medium text-red-600' : 'text-stone-500'}>
                                    {missing ? `Please choose a ${option.name.toLowerCase()}` : `Choose a ${option.name.toLowerCase()}`}
                                </span>
                            )}
                        </legend>
                        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={option.name}>
                            {option.values.map((value) => {
                                const on = chosen === value.label;
                                // Does this value lead to something that can be bought, given the other choices?
                                const fits = variants.filter((v) => v.values[i] === value.label && matches(v, selected, i));
                                const available = fits.some((v) => v.purchasable);
                                const exists = fits.length > 0;
                                const state = available ? 'available' : exists ? 'Sold out' : 'Unavailable with your other choices';
                                const crossed = !available;

                                if (swatches) {
                                    return (
                                        <button
                                            key={value.label}
                                            type="button"
                                            role="radio"
                                            aria-checked={on}
                                            aria-label={`${value.label}${crossed ? ` (${state})` : ''}`}
                                            title={value.label + (crossed ? ` — ${state}` : '')}
                                            onClick={() => choose(i, value.label)}
                                            className={`relative flex h-11 w-11 items-center justify-center rounded-full transition ${on ? 'ring-2 ring-stone-900 ring-offset-2' : missing ? 'ring-1 ring-red-300 hover:ring-stone-400' : 'hover:ring-2 hover:ring-stone-300 hover:ring-offset-2'}`}
                                        >
                                            <span
                                                className={`h-9 w-9 rounded-full ${isLight(value.color) || !value.color ? 'border border-stone-300' : ''} ${crossed ? 'opacity-40' : ''}`}
                                                style={{
                                                    backgroundColor: value.color || '#f5f5f4',
                                                }}
                                            />
                                            {!value.color && <span className="absolute text-[10px] font-semibold text-stone-700">{value.label.slice(0, 2)}</span>}
                                            {crossed && <span className="pointer-events-none absolute h-px w-10 rotate-45 bg-stone-500" aria-hidden />}
                                        </button>
                                    );
                                }

                                return (
                                    <button
                                        key={value.label}
                                        type="button"
                                        role="radio"
                                        aria-checked={on}
                                        aria-label={`${value.label}${crossed ? ` (${state})` : ''}`}
                                        onClick={() => choose(i, value.label)}
                                        className={`relative min-w-12 overflow-hidden rounded-xl border px-4 py-2.5 text-sm font-medium transition active:scale-[0.97] ${
                                            on
                                                ? 'border-stone-900 bg-stone-900 text-white'
                                                : missing
                                                  ? 'border-red-300 bg-white text-stone-900 hover:border-stone-900'
                                                  : 'border-stone-300 bg-white text-stone-900 hover:border-stone-900'
                                        } ${crossed && !on ? 'text-stone-400 line-through decoration-stone-400' : ''}`}
                                    >
                                        {value.label}
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>
                );
            })}
        </div>
    );
}
