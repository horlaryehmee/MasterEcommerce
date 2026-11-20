import { ArrowUpDown, Check, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import BottomSheet from './BottomSheet';

/** Phones: a compact "Sort" pill that opens a bottom sheet. Larger screens: a dropdown. */
export default function SortSelect({ value, options, onChange, className = '' }) {
    const [open, setOpen] = useState(false);
    const current = options.find((o) => o.value === value) || options[0];
    const choose = (v) => {
        setOpen(false);
        if (v !== value) onChange(v);
    };

    return (
        <div className={className}>
            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-haspopup="dialog"
                className="inline-flex h-10 shrink-0 items-center gap-2 rounded-full border border-stone-200 bg-white pr-3 pl-3.5 text-sm font-medium text-stone-800 transition active:scale-95 active:bg-stone-50 sm:hidden"
            >
                <ArrowUpDown size={15} className="text-stone-500" />
                <span className="max-w-[7.5rem] truncate">{current?.label || 'Sort'}</span>
            </button>
            <BottomSheet open={open} onClose={() => setOpen(false)} title="Sort by">
                <ul className="pb-2">
                    {options.map((o) => {
                        const active = o.value === current?.value;
                        return (
                            <li key={o.value}>
                                <button
                                    type="button"
                                    onClick={() => choose(o.value)}
                                    aria-pressed={active}
                                    className={`flex w-full items-center justify-between rounded-2xl px-4 py-3.5 text-left text-[15px] transition active:scale-[0.98] ${active ? 'bg-stone-100 font-semibold text-stone-900' : 'text-stone-700 active:bg-stone-50'}`}
                                >
                                    {o.label}
                                    {active && <Check size={18} className="text-brand" />}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </BottomSheet>

            <div className="relative hidden sm:block">
                <label htmlFor="sort" className="sr-only">
                    Sort products
                </label>
                <select
                    id="sort"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="h-11 appearance-none rounded-full border border-stone-200 bg-white pr-10 pl-4 text-sm font-medium text-stone-800 hover:border-stone-400 focus:border-stone-900 focus:outline-none"
                >
                    {options.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
                <ChevronDown size={16} className="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-stone-500" />
            </div>
        </div>
    );
}
