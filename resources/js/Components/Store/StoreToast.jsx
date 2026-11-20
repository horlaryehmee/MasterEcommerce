import { usePage } from '@inertiajs/react';
import { AlertCircle, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Storefront error messages (e.g. a product that can't be ordered). Sits above the phone bottom navigation. */
export default function StoreToast() {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);

    useEffect(() => {
        if (!flash?.error) return;
        setToast({ key: Date.now(), text: flash.error });
        const t = setTimeout(() => setToast(null), 6000);
        return () => clearTimeout(t);
    }, [flash]);

    if (!toast) return null;

    return (
        <div className="fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+4.75rem)] z-50 mx-auto max-w-md lg:inset-x-auto lg:top-24 lg:right-6 lg:bottom-auto lg:w-96" role="alert">
            <div key={toast.key} className="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-2xl shadow-stone-900/15 transition duration-200 starting:translate-y-2 starting:opacity-0">
                <p className="flex flex-1 items-start gap-2 text-sm text-stone-800">
                    <AlertCircle size={18} className="mt-0.5 shrink-0 text-red-600" />
                    {toast.text}
                </p>
                <button type="button" onClick={() => setToast(null)} className="-mt-1 -mr-1 rounded-full p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-700" aria-label="Dismiss">
                    <X size={16} />
                </button>
            </div>
        </div>
    );
}
