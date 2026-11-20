import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

/** items: [{ label, href? }] — the last item is the current page. */
export default function Breadcrumbs({ items, className = '' }) {
    return (
        <nav aria-label="Breadcrumb" className={className}>
            <ol className="flex flex-wrap items-center gap-1 text-sm text-stone-500">
                {items.map((item, i) => {
                    const last = i === items.length - 1;
                    return (
                        <li key={i} className="flex items-center gap-1">
                            {item.href && !last ? (
                                <Link href={item.href} className="transition-colors hover:text-stone-900">
                                    {item.label}
                                </Link>
                            ) : (
                                <span aria-current={last ? 'page' : undefined} className={last ? 'line-clamp-1 text-stone-900' : ''}>
                                    {item.label}
                                </span>
                            )}
                            {!last && <ChevronRight size={14} className="text-stone-400" aria-hidden />}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
