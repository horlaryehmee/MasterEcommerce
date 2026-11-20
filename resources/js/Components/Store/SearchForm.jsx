import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/** Product search box. Submits to the catalog (/products?search=...). */
export default function SearchForm({ initial = '', autoFocus = false, onDone, className = '', action = '/products', extraParams = {} }) {
    const [query, setQuery] = useState(initial);
    const input = useRef(null);

    useEffect(() => setQuery(initial), [initial]);
    useEffect(() => {
        if (autoFocus) input.current?.focus();
    }, [autoFocus]);

    const submit = (e) => {
        e.preventDefault();
        const search = query.trim();
        router.get(action, { ...extraParams, ...(search ? { search } : {}) }, { preserveScroll: false });
        onDone?.();
    };

    const clear = () => {
        setQuery('');
        input.current?.focus();
        if (initial) {
            router.get(action, extraParams, { preserveScroll: true });
        }
    };

    return (
        <form onSubmit={submit} role="search" className={`relative ${className}`}>
            <label htmlFor="site-search" className="sr-only">
                Search products
            </label>
            <Search size={18} className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-stone-400" aria-hidden />
            <input
                ref={input}
                id="site-search"
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search products…"
                maxLength={100}
                autoComplete="off"
                enterKeyHint="search"
                className="h-12 w-full rounded-full border border-stone-200 bg-stone-50 pr-24 pl-11 text-[15px] text-stone-900 placeholder:text-stone-400 focus:border-stone-400 focus:bg-white focus:outline-none [&::-webkit-search-cancel-button]:hidden"
            />
            {query && (
                <button type="button" onClick={clear} aria-label="Clear search" className="absolute top-1/2 right-20 -translate-y-1/2 rounded-full p-1.5 text-stone-400 hover:text-stone-700">
                    <X size={16} />
                </button>
            )}
            <button type="submit" className="absolute top-1.5 right-1.5 h-9 rounded-full bg-brand px-4 text-sm font-semibold text-on-brand">
                Search
            </button>
        </form>
    );
}
