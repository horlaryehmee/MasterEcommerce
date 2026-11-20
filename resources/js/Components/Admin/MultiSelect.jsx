import { Check, X } from 'lucide-react';
import { useId, useMemo, useRef, useState } from 'react';

/**
 * Searchable multi-select with removable chips (countries, states…).
 * options: [{ value, label, state? }]; `state` options are indented under their country.
 */
export default function MultiSelect({ label, help, error, options, value, onChange, placeholder = 'Start typing to search…', maxResults = 60 }) {
    const id = useId();
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [highlight, setHighlight] = useState(0);
    const input = useRef(null);

    const byValue = useMemo(() => Object.fromEntries(options.map((o) => [o.value, o])), [options]);
    const selected = new Set(value);
    const results = useMemo(() => {
        const q = query.trim().toLowerCase();
        const list = q ? options.filter((o) => o.label.toLowerCase().includes(q) || o.value.toLowerCase() === q) : options.filter((o) => !o.state);
        return list.slice(0, maxResults);
    }, [options, query, maxResults]);

    const toggle = (v) => {
        onChange(selected.has(v) ? value.filter((x) => x !== v) : [...value, v]);
        setQuery('');
        input.current?.focus();
    };

    const onKeyDown = (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setOpen(true);
            setHighlight((h) => Math.min(h + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlight((h) => Math.max(h - 1, 0));
        } else if (e.key === 'Enter') {
            if (open && results[highlight]) {
                e.preventDefault();
                toggle(results[highlight].value);
            }
        } else if (e.key === 'Backspace' && !query && value.length) {
            onChange(value.slice(0, -1));
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div>
            {label && (
                <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-zinc-800">
                    {label}
                </label>
            )}
            <div className="relative">
                <div className={`flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border bg-white px-2 py-1.5 focus-within:border-zinc-900 focus-within:ring-1 focus-within:ring-zinc-900 ${error ? 'border-red-500' : 'border-zinc-300'}`} onClick={() => input.current?.focus()}>
                    {value.map((v) => (
                        <span key={v} className="inline-flex items-center gap-1 rounded-md bg-zinc-100 py-0.5 pr-1 pl-2 text-xs font-medium text-zinc-800">
                            {byValue[v]?.label ?? v}
                            <button type="button" onClick={(e) => (e.stopPropagation(), toggle(v))} className="rounded p-0.5 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900" aria-label={`Remove ${byValue[v]?.label ?? v}`}>
                                <X size={12} />
                            </button>
                        </span>
                    ))}
                    <input
                        ref={input}
                        id={id}
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setOpen(true);
                            setHighlight(0);
                        }}
                        onFocus={() => setOpen(true)}
                        onBlur={() => setTimeout(() => setOpen(false), 150)}
                        onKeyDown={onKeyDown}
                        placeholder={value.length ? '' : placeholder}
                        className="min-w-[8rem] flex-1 border-0 bg-transparent p-1 text-sm outline-none focus:ring-0"
                        role="combobox"
                        aria-expanded={open}
                        aria-controls={`${id}-list`}
                        autoComplete="off"
                    />
                </div>
                {open && (
                    <ul id={`${id}-list`} role="listbox" className="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                        {results.length === 0 && <li className="px-3 py-2 text-sm text-zinc-500">No matches</li>}
                        {results.map((o, i) => (
                            <li
                                key={o.value}
                                role="option"
                                aria-selected={selected.has(o.value)}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => toggle(o.value)}
                                onMouseEnter={() => setHighlight(i)}
                                className={`flex cursor-pointer items-center justify-between gap-2 px-3 py-1.5 text-sm ${i === highlight ? 'bg-zinc-100' : ''} ${o.state ? 'pl-6 text-zinc-600' : 'text-zinc-900'}`}
                            >
                                {o.label}
                                {selected.has(o.value) && <Check size={15} className="shrink-0 text-emerald-600" />}
                            </li>
                        ))}
                        {!query && <li className="border-t border-zinc-100 px-3 py-2 text-xs text-zinc-400">Type to search states and regions too.</li>}
                    </ul>
                )}
            </div>
            {help && !error && <p className="mt-1.5 text-xs text-zinc-500">{help}</p>}
            {error && <p className="mt-1.5 text-xs font-medium text-red-600">{error}</p>}
        </div>
    );
}
