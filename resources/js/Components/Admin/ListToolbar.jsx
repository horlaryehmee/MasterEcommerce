import { router } from '@inertiajs/react';
import { Download, Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const EMPTY = (v) => v === '' || v === null || v === undefined || v === false;

/**
 * Shared state for an admin list: the filters in the URL, a search box that
 * searches as you type, and helpers to change, clear and export them.
 *
 *   const list = useList('/admin/orders', filters, { sort: 'newest', per_page: 25 });
 *
 * `keep`: other URL parameters to carry along (e.g. the analytics period).
 */
export function useList(url, filters, defaults = {}, keep = []) {
    const [search, setSearch] = useState(filters.search ?? '');
    const sent = useRef(filters.search ?? '');

    const params = (changes = {}) => {
        const current = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
        const kept = Object.fromEntries(keep.filter((k) => current.get(k)).map((k) => [k, current.get(k)]));
        const p = { ...kept, ...filters, search, ...changes };
        Object.keys(p).forEach((k) => (EMPTY(p[k]) || String(p[k]) === String(defaults[k] ?? '\u0000')) && delete p[k]);
        return p;
    };

    const apply = (changes = {}) => {
        if ('search' in changes) setSearch(changes.search);
        sent.current = changes.search ?? search;
        router.get(url, params(changes), { preserveState: true, preserveScroll: true, replace: true });
    };

    // Search as you type (after a short pause).
    useEffect(() => {
        if (search === sent.current) return;
        const t = setTimeout(() => apply({ search }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const clear = () => {
        const keep = {};
        Object.keys(filters).forEach((k) => (keep[k] = ['sort', 'per_page'].includes(k) ? filters[k] : ''));
        apply({ ...keep, search: '' });
    };

    const query = (changes = {}) => new URLSearchParams(params(changes)).toString();

    return { url, filters, defaults, search, setSearch, apply, clear, query };
}

/** Text for the active-filter chips, e.g. activeFilters(filters, { status: statuses, from: (v) => `From ${v}` }). */
export function activeFilters(filters, spec) {
    return Object.entries(spec)
        .filter(([key]) => !EMPTY(filters[key]))
        .map(([key, how]) => {
            const v = filters[key];
            const text = typeof how === 'function' ? how(v) : Array.isArray(how) ? how.find((o) => String(o.value) === String(v))?.label ?? v : how?.[v] ?? v;
            return [key, text];
        });
}

/**
 * Search box + "Filters" toggle + sort + "Export CSV", with a panel for the
 * filters (children) and removable chips for the ones that are on.
 */
export function ListToolbar({ list, placeholder = 'Search…', sorts, exportUrl, active = [], children }) {
    const [open, setOpen] = useState(active.length > 0);
    const count = active.length;

    return (
        <div className="border-b border-zinc-100">
            <div className="flex flex-wrap items-center gap-2 p-4">
                <form
                    role="search"
                    className="relative min-w-0 flex-1 basis-40"
                    onSubmit={(e) => {
                        e.preventDefault();
                        list.apply({ search: list.search });
                    }}
                >
                    <Search size={16} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-400" />
                    <input type="search" value={list.search} onChange={(e) => list.setSearch(e.target.value)} placeholder={placeholder} enterKeyHint="search" className="input pl-9" aria-label={placeholder} />
                </form>
                {children && (
                    <button
                        type="button"
                        onClick={() => setOpen(!open)}
                        aria-expanded={open}
                        className={`inline-flex h-11 items-center gap-2 rounded-xl border px-3.5 text-sm font-semibold transition sm:h-10 sm:rounded-lg ${open || count ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50'}`}
                    >
                        <SlidersHorizontal size={16} />
                        Filters
                        {count > 0 && <span className="rounded-full bg-white/20 px-1.5 text-xs leading-5">{count}</span>}
                    </button>
                )}
                {sorts && (
                    <select value={list.filters.sort} onChange={(e) => list.apply({ sort: e.target.value })} className="input hidden w-auto md:block" aria-label="Sort by">
                        {Object.entries(sorts).map(([v, l]) => (
                            <option key={v} value={v}>
                                {l}
                            </option>
                        ))}
                    </select>
                )}
                {exportUrl && (
                    <a
                        href={`${exportUrl}?${list.query()}`}
                        className="inline-flex h-11 items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3.5 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50 sm:h-10 sm:rounded-lg"
                        title="Download the list as a spreadsheet (CSV), with the current search and filters"
                    >
                        <Download size={16} />
                        <span className="hidden sm:inline">Export CSV</span>
                        <span className="sr-only sm:hidden">Export CSV</span>
                    </a>
                )}
            </div>

            {open && children && (
                <div className="grid gap-3 border-t border-zinc-100 bg-zinc-50/60 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    {children}
                    {sorts && (
                        <FilterSelect list={list} name="sort" label="Sort by" options={sorts} className="md:hidden" allowEmpty={false} />
                    )}
                </div>
            )}

            {count > 0 && (
                <div className="flex flex-wrap items-center gap-2 border-t border-zinc-100 px-4 py-2.5">
                    {active.map(([key, text]) => (
                        <span key={key} className="inline-flex items-center gap-1 rounded-full bg-zinc-100 py-1 pr-1 pl-3 text-xs font-medium text-zinc-700">
                            {text}
                            <button type="button" onClick={() => list.apply({ [key]: '' })} className="rounded-full p-1 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900" aria-label={`Remove filter: ${text}`}>
                                <X size={12} />
                            </button>
                        </span>
                    ))}
                    <button type="button" onClick={list.clear} className="text-xs font-semibold text-zinc-600 underline underline-offset-2 hover:text-zinc-900">
                        Clear all
                    </button>
                </div>
            )}
        </div>
    );
}

function Label({ children }) {
    return <span className="mb-1.5 block text-xs font-semibold text-zinc-600">{children}</span>;
}

/** options: { value: label } or [{ value, label }]. */
export function FilterSelect({ list, name, label, options, placeholder = 'Any', className = '', allowEmpty = true }) {
    const entries = Array.isArray(options) ? options.map((o) => [o.value, o.label]) : Object.entries(options);
    return (
        <label className={`block min-w-0 ${className}`}>
            <Label>{label}</Label>
            <select value={list.filters[name] ?? ''} onChange={(e) => list.apply({ [name]: e.target.value })} className="input">
                {allowEmpty && <option value="">{placeholder}</option>}
                {entries.map(([v, l]) => (
                    <option key={v} value={v}>
                        {l}
                    </option>
                ))}
            </select>
        </label>
    );
}

/** From / to dates (whole days in the store's timezone). */
export function FilterDates({ list, label = 'Date', from = 'from', to = 'to', className = 'sm:col-span-2' }) {
    return (
        <div className={`min-w-0 ${className}`}>
            <Label>{label}</Label>
            <div className="flex items-center gap-2">
                <input type="date" value={list.filters[from] ?? ''} max={list.filters[to] || undefined} onChange={(e) => list.apply({ [from]: e.target.value })} className="input min-w-0" aria-label={`${label}: from`} />
                <span className="text-sm text-zinc-400">to</span>
                <input type="date" value={list.filters[to] ?? ''} min={list.filters[from] || undefined} onChange={(e) => list.apply({ [to]: e.target.value })} className="input min-w-0" aria-label={`${label}: to`} />
            </div>
        </div>
    );
}

/** A number applied when the field loses focus or Enter is pressed. */
export function FilterNumber({ list, name, label, placeholder, prefix }) {
    const [value, setValue] = useState(list.filters[name] ?? '');
    useEffect(() => setValue(list.filters[name] ?? ''), [list.filters[name]]);
    const commit = () => String(value) !== String(list.filters[name] ?? '') && list.apply({ [name]: value });
    return (
        <label className="block min-w-0">
            <Label>{label}</Label>
            <span className="relative block">
                {prefix && <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-zinc-400">{prefix}</span>}
                <input type="number" min="0" inputMode="decimal" value={value} placeholder={placeholder} onChange={(e) => setValue(e.target.value)} onBlur={commit} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), commit())} className={`input ${prefix ? 'pl-8' : ''}`} />
            </span>
        </label>
    );
}
