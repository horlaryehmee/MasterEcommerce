import { router, useForm } from '@inertiajs/react';
import { Info, Loader2, TicketPercent, X } from 'lucide-react';
import { useId, useState } from 'react';

/**
 * "Have a coupon?" field for the cart and checkout summaries. The server checks
 * the code and the page reloads its totals; one coupon per order.
 * `error` is a coupon problem found when the order was placed.
 * The field is shown open, so shoppers see straight away that codes work here.
 */
export default function CouponBox({ coupon, notice, error, compact = false }) {
    const id = useId();
    const form = useForm({ coupon: '' });
    const [removing, setRemoving] = useState(false);
    const message = form.errors.coupon || error;

    const apply = (e) => {
        e.preventDefault();
        e.stopPropagation();
        form.post('/cart/coupon', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = () =>
        router.delete('/cart/coupon', {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setRemoving(true),
            onFinish: () => setRemoving(false),
        });

    return (
        <div className="space-y-2">
            {notice && (
                <p className="flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2 text-[13px] text-amber-900">
                    <Info size={15} className="mt-0.5 shrink-0" /> {notice}
                </p>
            )}

            {coupon ? (
                <div className={`flex items-center gap-3 rounded-2xl border border-dashed border-emerald-300 bg-emerald-50/70 px-3.5 py-2.5 transition-opacity ${removing ? 'opacity-60' : ''}`}>
                    <TicketPercent size={18} className="shrink-0 text-emerald-700" />
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-semibold tracking-wide text-emerald-900">{coupon.code}</span>
                        <span className="block truncate text-xs text-emerald-800">{coupon.label}</span>
                    </span>
                    <button type="button" onClick={remove} disabled={removing} className="-mr-1 shrink-0 rounded-full p-1.5 text-emerald-800 hover:bg-emerald-100" aria-label={`Remove coupon ${coupon.code}`}>
                        {removing ? <Loader2 size={16} className="animate-spin" /> : <X size={16} />}
                    </button>
                </div>
            ) : (
                // Not a nested <form>: on the checkout page this sits inside the order form.
                <div role="group" aria-labelledby={`${id}-label`}>
                    <label id={`${id}-label`} htmlFor={id} className={`mb-1.5 flex items-center gap-1.5 font-medium text-stone-800 ${compact ? 'text-[13px]' : 'text-sm'}`}>
                        <TicketPercent size={compact ? 14 : 16} className="text-stone-500" /> Have a coupon code?
                    </label>
                    <div className="flex gap-2">
                        <input
                            id={id}
                            autoComplete="off"
                            autoCapitalize="characters"
                            spellCheck={false}
                            maxLength={40}
                            value={form.data.coupon}
                            onChange={(e) => {
                                form.setData('coupon', e.target.value);
                                if (form.errors.coupon) form.clearErrors('coupon');
                            }}
                            onKeyDown={(e) => e.key === 'Enter' && apply(e)}
                            placeholder="Enter code"
                            aria-invalid={message ? true : undefined}
                            className={`${compact ? 'h-10' : 'h-11'} min-w-0 flex-1 rounded-xl border bg-white px-3.5 text-[15px] tracking-wide text-stone-900 uppercase placeholder:tracking-normal placeholder:text-stone-400 placeholder:normal-case focus:border-stone-900 focus:ring-0 focus:outline-none ${message ? 'border-red-500' : 'border-stone-300'}`}
                        />
                        <button type="button" onClick={apply} disabled={form.processing || !form.data.coupon.trim()} className={`inline-flex ${compact ? 'h-10' : 'h-11'} shrink-0 items-center justify-center gap-1.5 rounded-xl bg-stone-900 px-4 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:opacity-50`}>
                            {form.processing && <Loader2 size={15} className="animate-spin" />} Apply
                        </button>
                    </div>
                    {message && <p className="mt-1.5 text-xs font-medium text-red-600" data-error>{message}</p>}
                </div>
            )}
        </div>
    );
}
