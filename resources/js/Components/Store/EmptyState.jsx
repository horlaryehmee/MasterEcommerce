import { Link } from '@inertiajs/react';
import { SearchX } from 'lucide-react';
import WhatsAppButton from './WhatsAppButton';

export default function EmptyState({ title = 'No products found', message, clearHref, clearLabel = 'Clear filters', icon: Icon = SearchX }) {
    return (
        <div className="flex flex-col items-center rounded-3xl border border-dashed border-stone-300 bg-stone-50/60 px-6 py-16 text-center sm:py-20">
            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-white text-stone-500 shadow-sm">
                <Icon size={26} strokeWidth={1.6} />
            </span>
            <h2 className="mt-5 text-lg font-semibold text-stone-900">{title}</h2>
            {message && <p className="mt-2 max-w-md text-sm text-stone-600">{message}</p>}
            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                {clearHref && (
                    <Link href={clearHref} className="btn-outline">
                        {clearLabel}
                    </Link>
                )}
                <WhatsAppButton>Ask us on WhatsApp</WhatsAppButton>
            </div>
        </div>
    );
}
