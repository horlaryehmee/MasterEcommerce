import { ChevronDown, ImageIcon, Layers, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { guessColor } from '@/lib/colors';
import { Card } from './ui';

const MAX_OPTIONS = 3;

const NAME_SUGGESTIONS = ['Size', 'Colour', 'Material', 'Style', 'Weight', 'Length'];

/** One-tap values for common options. Colours come with a swatch. */
const PRESETS = {
    size: [{ label: 'XS' }, { label: 'S' }, { label: 'M' }, { label: 'L' }, { label: 'XL' }, { label: 'XXL' }],
    colour: [
        { label: 'Black', color: '#111111' },
        { label: 'White', color: '#FFFFFF' },
        { label: 'Grey', color: '#9CA3AF' },
        { label: 'Red', color: '#DC2626' },
        { label: 'Blue', color: '#2563EB' },
        { label: 'Green', color: '#16A34A' },
        { label: 'Pink', color: '#EC4899' },
        { label: 'Gold', color: '#D4A017' },
        { label: 'Silver', color: '#C0C0C0' },
        { label: 'Brown', color: '#8B5E3C' },
    ],
};

const isColour = (name) => /colou?r|shade/i.test(name || '');
const presetsFor = (name) => (isColour(name) ? PRESETS.colour : /size/i.test(name || '') ? PRESETS.size : []);
const comboKey = (values) => values.map((v) => String(v).toLowerCase()).join('\u001f');

/** Every combination of the options' values, in order. */
function combinations(options) {
    return options.reduce((acc, o) => acc.flatMap((combo) => o.values.map((v) => [...combo, v.label])), [[]]);
}

/**
 * Rebuild the variation list for new options, keeping what was already
 * entered for combinations that still exist (price, SKU, photo…).
 */
export function regenerate(options, current, defaults) {
    const usable = options.filter((o) => o.values.length > 0);
    if (!usable.length) return [];
    const byKey = new Map(current.map((v) => [comboKey(v.values), v]));
    // A new value added to an option: reuse the row it extends (e.g. "M" → "M / Red"), so prices carry over.
    const byPrefix = new Map(current.map((v) => [comboKey(v.values.slice(0, -1)), v]));
    return combinations(usable).map((values) => {
        const existing = byKey.get(comboKey(values));
        if (existing) return { ...existing, values };
        const template = byPrefix.get(comboKey(values.slice(0, -1))) || byKey.get(comboKey(values.slice(0, -1)));
        return {
            id: null,
            values,
            price: template?.price ?? defaults.price ?? '',
            compare_at_price: template?.compare_at_price ?? '',
            sku: '',
            availability: template?.availability ?? 'in_stock',
            image: '',
            is_enabled: true,
        };
    });
}

/** Values as removable chips; type and press Enter (or comma), or paste "S, M, L". */
function ValuesInput({ option, onChange, error }) {
    const [text, setText] = useState('');
    const input = useRef(null);
    const swatches = isColour(option.name);
    const has = (label) => option.values.some((v) => v.label.toLowerCase() === label.toLowerCase());

    const addMany = (labels) => {
        const fresh = [];
        labels
            .map((l) => (typeof l === 'string' ? { label: l.trim() } : l))
            .filter((v) => v.label && !has(v.label) && !fresh.some((f) => f.label.toLowerCase() === v.label.toLowerCase()))
            .forEach((v) =>
                fresh.push({
                    label: v.label.slice(0, 60),
                    // A colour option guesses the swatch from the name ("Yello" → yellow); the picker can change it.
                    color: v.color ?? (swatches ? guessColor(v.label) || '#D6D3D1' : null),
                }),
            );
        if (fresh.length) onChange([...option.values, ...fresh]);
    };

    const commit = () => {
        if (!text.trim()) return;
        addMany(text.split(','));
        setText('');
    };

    const remaining = presetsFor(option.name).filter((p) => !has(p.label));

    return (
        <div>
            <div
                onClick={() => input.current?.focus()}
                className={`flex min-h-12 flex-wrap items-center gap-1.5 rounded-xl border bg-white p-1.5 focus-within:border-zinc-900 sm:min-h-11 sm:rounded-lg ${error ? 'border-red-500' : 'border-zinc-300'}`}
            >
                {option.values.map((v, i) => (
                    <span key={v.label} className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-zinc-100 pr-1 pl-2 text-sm font-medium text-zinc-800">
                        {swatches && (
                            <label
                                className="relative h-4 w-4 cursor-pointer overflow-hidden rounded-full border border-zinc-300"
                                style={{
                                    backgroundColor: v.color || '#D6D3D1',
                                }}
                                title={`Pick a colour for ${v.label}`}
                            >
                                <input
                                    type="color"
                                    className="absolute inset-0 cursor-pointer opacity-0"
                                    value={v.color || '#D6D3D1'}
                                    onChange={(e) =>
                                        onChange(
                                            option.values.map((x, j) =>
                                                j === i
                                                    ? {
                                                          ...x,
                                                          color: e.target.value.toUpperCase(),
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                    aria-label={`Colour for ${v.label}`}
                                />
                            </label>
                        )}
                        {v.label}
                        <button
                            type="button"
                            onClick={() => onChange(option.values.filter((_, j) => j !== i))}
                            className="rounded p-0.5 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900"
                            aria-label={`Remove ${v.label}`}
                        >
                            <X size={14} />
                        </button>
                    </span>
                ))}
                <input
                    ref={input}
                    value={text}
                    onChange={(e) => (e.target.value.includes(',') ? (addMany(e.target.value.split(',')), setText('')) : setText(e.target.value))}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            commit();
                        } else if (e.key === 'Backspace' && !text && option.values.length) {
                            onChange(option.values.slice(0, -1));
                        }
                    }}
                    onBlur={commit}
                    maxLength={60}
                    placeholder={option.values.length ? 'Add another…' : swatches ? 'Type a colour, e.g. Black, and press Enter' : 'Type a value, e.g. Small, and press Enter'}
                    className="h-8 min-w-36 flex-1 border-0 bg-transparent px-1.5 text-[15px] outline-none focus:ring-0 sm:text-sm"
                    aria-label={`${option.name || 'Option'} values`}
                />
                {swatches && text.trim() && guessColor(text) && (
                    <span className="flex shrink-0 items-center gap-1.5 pr-1.5 text-xs text-zinc-500" aria-live="polite">
                        <span className="h-4 w-4 rounded-full border border-zinc-300" style={{ backgroundColor: guessColor(text) }} />
                        Enter to add
                    </span>
                )}
            </div>
            {error && <p className="mt-1.5 text-xs font-medium text-red-600">{error}</p>}
            {remaining.length > 0 && (
                <div className="mt-2 flex flex-wrap items-center gap-1.5">
                    <span className="text-xs text-zinc-500">Quick add:</span>
                    {remaining.map((p) => (
                        <button
                            key={p.label}
                            type="button"
                            onClick={() => addMany([p])}
                            className="inline-flex items-center gap-1 rounded-full border border-dashed border-zinc-300 px-2.5 py-1 text-xs font-medium text-zinc-600 hover:border-zinc-500 hover:text-zinc-900"
                        >
                            {p.color && <span className="h-2.5 w-2.5 rounded-full border border-zinc-300" style={{ backgroundColor: p.color }} />}
                            {p.label}
                        </button>
                    ))}
                    {remaining.length > 1 && (
                        <button type="button" onClick={() => addMany(remaining)} className="text-xs font-semibold text-zinc-700 underline-offset-2 hover:underline">
                            Add all
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

/** Thumbnail button that opens a small grid of the product's photos. */
function ImageChooser({ value, images, onChange, label }) {
    const [open, setOpen] = useState(false);
    const box = useRef(null);
    const current = images.find((img) => img.ref === value);

    useEffect(() => {
        if (!open) return;
        const close = (e) => box.current && !box.current.contains(e.target) && setOpen(false);
        document.addEventListener('pointerdown', close);
        return () => document.removeEventListener('pointerdown', close);
    }, [open]);

    return (
        <div ref={box} className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="flex h-11 w-11 items-center justify-center overflow-hidden rounded-lg border border-zinc-300 bg-zinc-50 text-zinc-400 hover:border-zinc-500"
                aria-label={`Photo for ${label}`}
                title={images.length ? 'Choose the photo for this variation' : 'Add product images first'}
            >
                {current ? <img src={current.url} alt="" className="h-full w-full object-cover" /> : <ImageIcon size={18} />}
            </button>
            {open && (
                <div className="absolute top-12 left-0 z-30 w-64 rounded-xl border border-zinc-200 bg-white p-3 shadow-xl">
                    {images.length ? (
                        <>
                            <p className="mb-2 text-xs font-medium text-zinc-600">Photo shown when a customer picks {label}</p>
                            <div className="grid grid-cols-4 gap-2">
                                {images.map((img) => (
                                    <button
                                        key={img.ref}
                                        type="button"
                                        onClick={() => {
                                            onChange(img.ref);
                                            setOpen(false);
                                        }}
                                        className={`aspect-square overflow-hidden rounded-lg border-2 ${img.ref === value ? 'border-zinc-900' : 'border-transparent hover:border-zinc-300'}`}
                                    >
                                        <img src={img.url} alt="" className="h-full w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                            {value && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        onChange('');
                                        setOpen(false);
                                    }}
                                    className="mt-2 text-xs font-medium text-zinc-600 underline"
                                >
                                    No specific photo
                                </button>
                            )}
                        </>
                    ) : (
                        <p className="text-xs text-zinc-600">Add product images in the Images section above, then pick one here.</p>
                    )}
                </div>
            )}
        </div>
    );
}

function BulkBar({ options, onApply, availabilityOptions, count }) {
    const [scope, setScope] = useState('all');
    const [price, setPrice] = useState('');
    const [availability, setAvailability] = useState('');
    const scopes = [
        { value: 'all', label: `All ${count} variations` },
        ...options.flatMap((o, i) =>
            o.values.map((v) => ({
                value: `${i}:${v.label}`,
                label: `${o.name || 'Option'}: ${v.label}`,
            })),
        ),
    ];

    return (
        <div className="flex flex-col gap-2 rounded-xl bg-zinc-50 p-3 sm:flex-row sm:items-end">
            <label className="min-w-0 flex-1 text-xs font-medium text-zinc-600">
                Edit
                <select className="input mt-1" value={scope} onChange={(e) => setScope(e.target.value)}>
                    {scopes.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </select>
            </label>
            <div className="grid grid-cols-2 gap-2 sm:flex">
                <label className="text-xs font-medium text-zinc-600 sm:w-32">
                    Price
                    <input className="input mt-1" type="number" min="0" step="0.01" inputMode="decimal" value={price} onChange={(e) => setPrice(e.target.value)} placeholder="Keep" />
                </label>
                <label className="text-xs font-medium text-zinc-600 sm:w-40">
                    Availability
                    <select className="input mt-1" value={availability} onChange={(e) => setAvailability(e.target.value)}>
                        <option value="">Keep</option>
                        {Object.entries(availabilityOptions).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>
            <button
                type="button"
                disabled={price === '' && !availability}
                onClick={() => {
                    onApply(scope, {
                        ...(price !== '' ? { price } : {}),
                        ...(availability ? { availability } : {}),
                    });
                    setPrice('');
                    setAvailability('');
                }}
                className="h-12 shrink-0 rounded-xl bg-zinc-900 px-4 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:opacity-40 sm:h-10 sm:rounded-lg"
            >
                Apply
            </button>
        </div>
    );
}

/**
 * "Variations" card on the product form: options (Size, Colour…) with their
 * values, and one row per combination with its own price, previous price,
 * SKU, availability, photo and an on/off switch.
 */
export default function VariantsEditor({ form, existingImages, availabilityOptions, maxVariants }) {
    const { options, variants, images: newFiles, remove_images: removed } = form.data;
    const errors = form.errors;
    const [showMore, setShowMore] = useState(() => variants.some((v) => v.sku || v.compare_at_price));

    // Photos a variation can use: saved ones (not being removed) and ones just added.
    const previews = useMemo(() => newFiles.map((f) => URL.createObjectURL(f)), [newFiles]);
    useEffect(() => () => previews.forEach(URL.revokeObjectURL), [previews]);
    const photoChoices = [
        ...existingImages.filter((img) => !removed.includes(img.id)).map((img) => ({ ref: `existing:${img.id}`, url: img.url })),
        ...previews.map((url, i) => ({ ref: `new:${i}`, url })),
    ];

    const setOptions = (next) =>
        form.setData((d) => ({
            ...d,
            options: next,
            variants: regenerate(next, d.variants, { price: d.price }),
        }));
    const setOption = (i, patch) => setOptions(options.map((o, j) => (j === i ? { ...o, ...patch } : o)));
    const setVariant = (i, patch) =>
        form.setData(
            'variants',
            variants.map((v, j) => (j === i ? { ...v, ...patch } : v)),
        );
    const applyBulk = (scope, patch) => {
        const [o, value] = scope === 'all' ? [null, null] : [Number(scope.split(':')[0]), scope.slice(scope.indexOf(':') + 1)];
        form.setData(
            'variants',
            variants.map((v) => (o === null || v.values[o] === value ? { ...v, ...patch } : v)),
        );
    };

    const total = combinations(options.filter((o) => o.values.length)).length;
    const tooMany = total > maxVariants;
    const err = (key) => errors[key];

    if (!options.length) {
        return (
            <Card title="Variations" description="Does this product come in different sizes, colours or styles? Each one can have its own price, photo and availability.">
                <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center">
                    <span className="flex h-11 w-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-600">
                        <Layers size={20} />
                    </span>
                    <p className="max-w-sm text-sm text-zinc-600">Add options like Size or Colour. Customers choose them before adding to cart.</p>
                    <div className="flex flex-wrap justify-center gap-2">
                        {['Size', 'Colour'].map((name) => (
                            <button
                                key={name}
                                type="button"
                                onClick={() => setOptions([{ name, values: [] }])}
                                className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 hover:bg-zinc-50"
                            >
                                <Plus size={15} /> {name}
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={() => setOptions([{ name: '', values: [] }])}
                            className="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-zinc-600 hover:text-zinc-900"
                        >
                            Other option
                        </button>
                    </div>
                </div>
            </Card>
        );
    }

    return (
        <Card title="Variations" description="Customers choose these on the product page. Prices and availability are set per variation below.">
            <div className="space-y-4">
                {options.map((option, i) => (
                    <div key={i} className="rounded-xl border border-zinc-200 p-3 sm:p-4">
                        <div className="mb-3 flex items-end gap-2">
                            <label className="min-w-0 flex-1 text-sm font-medium text-zinc-800">
                                Option name
                                <input
                                    className={`input mt-1.5 ${err(`options.${i}.name`) ? 'input-error' : ''}`}
                                    value={option.name}
                                    maxLength={40}
                                    list="variant-option-names"
                                    placeholder="e.g. Size"
                                    onChange={(e) => {
                                        const name = e.target.value;
                                        // Becoming a colour option: give the values swatches.
                                        setOption(i, {
                                            name,
                                            values:
                                                isColour(name) && !isColour(option.name)
                                                    ? option.values.map((v) => ({
                                                          ...v,
                                                          color: v.color || guessColor(v.label) || '#D6D3D1',
                                                      }))
                                                    : option.values,
                                        });
                                    }}
                                />
                            </label>
                            <button
                                type="button"
                                onClick={() => setOptions(options.filter((_, j) => j !== i))}
                                className="h-12 rounded-lg px-3 text-zinc-500 hover:bg-red-50 hover:text-red-600 sm:h-10"
                                aria-label={`Remove ${option.name || 'option'}`}
                                title="Remove option"
                            >
                                <Trash2 size={17} />
                            </button>
                        </div>
                        {err(`options.${i}.name`) && <p className="-mt-2 mb-2 text-xs font-medium text-red-600">{err(`options.${i}.name`)}</p>}
                        <ValuesInput option={option} onChange={(values) => setOption(i, { values })} error={err(`options.${i}.values`)} />
                    </div>
                ))}
                <datalist id="variant-option-names">
                    {NAME_SUGGESTIONS.filter((n) => !options.some((o) => o.name.toLowerCase() === n.toLowerCase())).map((n) => (
                        <option key={n} value={n} />
                    ))}
                </datalist>
                {options.length < MAX_OPTIONS && (
                    <button
                        type="button"
                        onClick={() => setOptions([...options, { name: '', values: [] }])}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-800 hover:underline"
                    >
                        <Plus size={16} /> Add another option
                    </button>
                )}

                {tooMany && (
                    <p className="rounded-xl bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                        That makes {total} combinations — the most a product can have is {maxVariants}. Remove some values.
                    </p>
                )}
                {err('variants') && <p className="rounded-xl bg-red-50 px-3 py-2.5 text-sm font-medium text-red-700">{err('variants')}</p>}

                {variants.length > 0 && !tooMany && (
                    <div className="space-y-3 border-t border-zinc-100 pt-4">
                        <div className="flex items-center justify-between gap-3">
                            <h3 className="text-sm font-semibold text-zinc-900">
                                {variants.length} variation
                                {variants.length === 1 ? '' : 's'}
                                <span className="ml-2 font-normal text-zinc-500">{variants.filter((v) => v.is_enabled).length} on sale</span>
                            </h3>
                            <button type="button" onClick={() => setShowMore((s) => !s)} className="inline-flex items-center gap-1 text-xs font-semibold text-zinc-700 hover:text-zinc-900">
                                {showMore ? 'Fewer fields' : 'Previous price & SKU'} <ChevronDown size={14} className={showMore ? 'rotate-180' : ''} />
                            </button>
                        </div>

                        {variants.length > 1 && <BulkBar options={options.filter((o) => o.values.length)} onApply={applyBulk} availabilityOptions={availabilityOptions} count={variants.length} />}

                        <ul className="divide-y divide-zinc-100 rounded-xl border border-zinc-200">
                            {variants.map((v, i) => {
                                const label = v.values.join(' / ');
                                const rowErrors = ['price', 'compare_at_price', 'sku', 'availability', 'values'].map((k) => err(`variants.${i}.${k}`)).filter(Boolean);
                                return (
                                    <li key={comboKey(v.values)} className={`p-3 transition-opacity ${v.is_enabled ? '' : 'bg-zinc-50/80'}`}>
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                            <ImageChooser value={v.image} images={photoChoices} onChange={(image) => setVariant(i, { image })} label={label} />
                                            <div className={`min-w-0 flex-1 basis-32 ${v.is_enabled ? '' : 'opacity-50'}`}>
                                                <p className="truncate text-sm font-semibold text-zinc-900">{label}</p>
                                                {!v.is_enabled && <p className="text-xs text-zinc-500">Hidden from customers</p>}
                                            </div>
                                            <label className="flex items-center gap-2 text-xs font-medium text-zinc-600" title={v.is_enabled ? 'Customers can choose this' : 'Hidden from customers'}>
                                                <span className="sm:hidden">{v.is_enabled ? 'On sale' : 'Off'}</span>
                                                <span className="relative inline-flex">
                                                    <input
                                                        type="checkbox"
                                                        className="peer sr-only"
                                                        checked={v.is_enabled}
                                                        onChange={(e) =>
                                                            setVariant(i, {
                                                                is_enabled: e.target.checked,
                                                            })
                                                        }
                                                        aria-label={`Offer ${label}`}
                                                    />
                                                    <span className="h-6 w-11 rounded-full bg-zinc-300 transition-colors peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-zinc-900/30" />
                                                    <span className="pointer-events-none absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5" />
                                                </span>
                                            </label>
                                        </div>
                                        <div className={`mt-2.5 grid grid-cols-2 gap-2 ${showMore ? 'sm:grid-cols-4' : 'sm:grid-cols-2'} ${v.is_enabled ? '' : 'opacity-60'}`}>
                                            <label className="text-xs font-medium text-zinc-600">
                                                Price
                                                <input
                                                    className={`input mt-1 ${err(`variants.${i}.price`) ? 'input-error' : ''}`}
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    inputMode="decimal"
                                                    value={v.price ?? ''}
                                                    onChange={(e) =>
                                                        setVariant(i, {
                                                            price: e.target.value,
                                                        })
                                                    }
                                                    required
                                                />
                                            </label>
                                            <label className="text-xs font-medium text-zinc-600">
                                                Availability
                                                <select
                                                    className="input mt-1"
                                                    value={v.availability}
                                                    onChange={(e) =>
                                                        setVariant(i, {
                                                            availability: e.target.value,
                                                        })
                                                    }
                                                >
                                                    {Object.entries(availabilityOptions).map(([value, text]) => (
                                                        <option key={value} value={value}>
                                                            {text}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            {showMore && (
                                                <>
                                                    <label className="text-xs font-medium text-zinc-600">
                                                        Previous price
                                                        <input
                                                            className={`input mt-1 ${err(`variants.${i}.compare_at_price`) ? 'input-error' : ''}`}
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            inputMode="decimal"
                                                            value={v.compare_at_price ?? ''}
                                                            onChange={(e) =>
                                                                setVariant(i, {
                                                                    compare_at_price: e.target.value,
                                                                })
                                                            }
                                                            placeholder="Optional"
                                                        />
                                                    </label>
                                                    <label className="text-xs font-medium text-zinc-600">
                                                        SKU
                                                        <input
                                                            className="input mt-1"
                                                            value={v.sku ?? ''}
                                                            maxLength={64}
                                                            onChange={(e) =>
                                                                setVariant(i, {
                                                                    sku: e.target.value,
                                                                })
                                                            }
                                                            placeholder="Optional"
                                                        />
                                                    </label>
                                                </>
                                            )}
                                        </div>
                                        {rowErrors.length > 0 && <p className="mt-1.5 text-xs font-medium text-red-600">{rowErrors[0]}</p>}
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </div>
        </Card>
    );
}
