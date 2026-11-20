import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

/** Server-side pagination using Laravel's paginator links. */
export default function Pagination({ links, currentPage, lastPage }) {
    if (!links || lastPage <= 1) return null;

    const prev = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1);

    const arrow = (link, label, Icon) =>
        link.url ? (
            <Link href={link.url} preserveScroll={false} className="flex h-10 w-10 items-center justify-center rounded-full border border-stone-200 text-stone-700 hover:border-stone-900" aria-label={label}>
                <Icon size={18} />
            </Link>
        ) : (
            <span className="flex h-10 w-10 items-center justify-center rounded-full border border-stone-100 text-stone-300" aria-hidden>
                <Icon size={18} />
            </span>
        );

    return (
        <nav className="mt-14 flex items-center justify-center gap-2" aria-label="Pagination">
            {arrow(prev, 'Previous page', ChevronLeft)}
            <div className="hidden items-center gap-1 sm:flex">
                {pages.map((link, i) =>
                    link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            aria-current={link.active ? 'page' : undefined}
                            className={`flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-medium ${link.active ? 'bg-brand text-on-brand' : 'text-stone-700 hover:bg-stone-100'}`}
                        >
                            {link.label}
                        </Link>
                    ) : (
                        <span key={i} className="px-2 text-stone-400">
                            {link.label}
                        </span>
                    ),
                )}
            </div>
            <span className="px-3 text-sm text-stone-600 sm:hidden">
                Page {currentPage} of {lastPage}
            </span>
            {arrow(next, 'Next page', ChevronRight)}
        </nav>
    );
}
