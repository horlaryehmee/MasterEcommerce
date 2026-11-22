import { useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, FileText, ImageIcon, Loader2, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const STATUS_STYLE = {
    pending: 'bg-amber-50 text-amber-900',
    approved: 'bg-emerald-50 text-emerald-900',
    rejected: 'bg-red-50 text-red-900',
};
const STATUS_ICON = { pending: Clock, approved: CheckCircle2, rejected: AlertTriangle };

/**
 * Bank transfer: the customer uploads a photo, screenshot or PDF of their transfer.
 * The shop checks it and confirms the payment; a rejection shows the shop's reason.
 */
export default function ReceiptUpload({ upload, receipts = [], inStep = false }) {
    const latest = receipts[0];
    const waiting = latest?.status === 'pending';
    const [open, setOpen] = useState(!latest || latest.status === 'rejected');
    const [preview, setPreview] = useState(null);
    const input = useRef(null);
    const form = useForm({ receipt: null });

    useEffect(() => () => preview && URL.revokeObjectURL(preview), [preview]);
    useEffect(() => {
        if (upload?.just_uploaded) setOpen(false);
    }, [upload?.just_uploaded]);

    if (!upload && receipts.length === 0) return null;

    const choose = (file) => {
        form.clearErrors();
        form.setData('receipt', file || null);
        setPreview(file && file.type.startsWith('image/') ? URL.createObjectURL(file) : null);
    };
    const clear = () => {
        choose(null);
        if (input.current) input.current.value = '';
    };
    const submit = (e) => {
        e.preventDefault();
        form.post(upload.url, { forceFormData: true, preserveScroll: true, onSuccess: clear });
    };

    return (
        <section className="rounded-3xl border border-stone-200 p-5 sm:p-6" aria-labelledby="receipt-heading">
            <h2 id="receipt-heading" className="text-lg font-semibold text-stone-900">
                {inStep ? (
                    <>
                        Upload your receipt <span className="text-sm font-normal text-stone-500">(optional)</span>
                    </>
                ) : (
                    'Payment receipt'
                )}
            </h2>

            {receipts.length > 0 && (
                <ul className="mt-3 space-y-2">
                    {receipts.map((r) => {
                        const Icon = STATUS_ICON[r.status] ?? Clock;
                        return (
                            <li key={r.id} className={`flex items-start gap-3 rounded-2xl p-3.5 text-sm ${STATUS_STYLE[r.status] ?? 'bg-stone-50'}`}>
                                <Icon size={18} className="mt-0.5 shrink-0" />
                                <span className="min-w-0">
                                    <strong>{r.status_label}</strong>
                                    <span className="opacity-75">
                                        {' '}
                                        · {r.kind === 'pdf' ? 'PDF' : 'Image'} uploaded {r.date}
                                    </span>
                                    {r.status === 'pending' && <span className="mt-0.5 block">{inStep ? 'Got it! Now press “I’ve made the payment” below.' : 'We’ll confirm your order as soon as the money shows in our account. You don’t need to do anything else.'}</span>}
                                    {r.note && <span className="mt-0.5 block">Reason: {r.note}</span>}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}

            {upload && !open && (
                <button type="button" onClick={() => setOpen(true)} className="mt-3 text-sm font-medium text-stone-600 underline hover:text-stone-900">
                    {waiting ? 'Upload another receipt' : 'Upload a receipt'}
                </button>
            )}

            {upload && open && (
                <form onSubmit={submit} className="mt-3">
                    <p className="text-sm text-stone-600">
                        {latest?.status === 'rejected' ? 'Please upload a new receipt.' : inStep ? 'After your transfer, upload a screenshot, photo or PDF of the receipt. It helps us confirm your order faster.' : 'Paid already? Upload a screenshot, photo or PDF of your transfer receipt so we can confirm your order faster.'}
                    </p>

                    {form.data.receipt ? (
                        <div className="mt-3 flex items-center gap-3 rounded-2xl border border-stone-200 p-3">
                            {preview ? <img src={preview} alt="Your receipt" className="h-16 w-16 rounded-xl object-cover" /> : <FileText size={28} className="mx-4 text-stone-500" />}
                            <span className="min-w-0 flex-1 text-sm">
                                <span className="block truncate font-medium text-stone-900">{form.data.receipt.name}</span>
                                <span className="text-stone-500">{(form.data.receipt.size / 1048576).toFixed(1)} MB</span>
                            </span>
                            <button type="button" onClick={clear} className="rounded-full p-2 text-stone-500 hover:bg-stone-100" aria-label="Remove file">
                                <X size={18} />
                            </button>
                        </div>
                    ) : (
                        <label className="mt-3 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-stone-300 px-4 py-7 text-center transition hover:border-stone-500 focus-within:border-stone-900">
                            <span className="flex gap-2 text-stone-500">
                                <ImageIcon size={22} /> <FileText size={22} />
                            </span>
                            <span className="text-sm font-semibold text-stone-900">Choose your receipt</span>
                            <span className="text-xs text-stone-500">Photo, screenshot or PDF · up to {upload.max_mb} MB</span>
                            <input ref={input} type="file" accept="image/jpeg,image/png,image/webp,application/pdf" className="sr-only" onChange={(e) => choose(e.target.files?.[0])} />
                        </label>
                    )}

                    {form.errors.receipt && (
                        <p className="mt-2 text-sm font-medium text-red-600" role="alert">
                            {form.errors.receipt}
                        </p>
                    )}
                    {form.progress && (
                        <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuenow={form.progress.percentage} aria-valuemin={0} aria-valuemax={100}>
                            <div className="h-full bg-stone-900 transition-all" style={{ width: `${form.progress.percentage}%` }} />
                        </div>
                    )}

                    <button type="submit" disabled={!form.data.receipt || form.processing} className="btn-primary mt-4 h-12 w-full sm:w-auto sm:px-8">
                        {form.processing ? <Loader2 size={18} className="animate-spin" /> : <Upload size={18} />} Send receipt
                    </button>
                </form>
            )}
        </section>
    );
}
