import { useForm } from '@inertiajs/react';
import { Check, ExternalLink, FileText, X } from 'lucide-react';
import { useState } from 'react';
import { Badge, Button, Card, Modal, Textarea } from '@/Components/Admin/ui';

const COLOR = { pending: 'amber', approved: 'green', rejected: 'gray' };
const QUICK_REASONS = ['No payment received yet', 'Amount doesn’t match the order total', 'Receipt is unclear, please send a clearer one'];

/** Bank-transfer receipts for one order: view, confirm the payment, or reject with a reason. */
export default function Receipts({ receipts, total }) {
    const [rejecting, setRejecting] = useState(null);
    const approve = useForm({ action: 'approve' });
    const reject = useForm({ action: 'reject', note: '' });

    if (!receipts.length) return null;

    return (
        <Card title="Payment receipts" description={`Check that ${total} has arrived in your bank account before confirming.`}>
            <ul className="space-y-4">
                {receipts.map((r) => (
                    <li key={r.id} className="rounded-xl border border-zinc-200 p-3">
                        <a href={r.url} target="_blank" rel="noopener noreferrer" className="group block overflow-hidden rounded-lg bg-zinc-50">
                            {r.kind === 'pdf' ? (
                                <span className="flex h-28 flex-col items-center justify-center gap-1.5 text-sm font-medium text-zinc-700 group-hover:text-zinc-950">
                                    <FileText size={28} /> Download PDF receipt
                                </span>
                            ) : (
                                <img src={r.url} alt={`Receipt uploaded ${r.at}`} loading="lazy" className="max-h-80 w-full object-contain transition group-hover:opacity-90" />
                            )}
                        </a>
                        <div className="mt-2.5 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                            <Badge color={COLOR[r.status]}>{r.status_label}</Badge>
                            <span>
                                {r.at} · {r.size}
                            </span>
                            <a href={r.url} target="_blank" rel="noopener noreferrer" className="ml-auto inline-flex items-center gap-1 font-medium text-zinc-600 hover:text-zinc-900">
                                Full size <ExternalLink size={12} />
                            </a>
                        </div>
                        {r.note && <p className="mt-2 text-xs text-zinc-600">Reason given: {r.note}</p>}
                        {r.status === 'pending' && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Button type="button" className="h-10 flex-1 px-3 text-sm" loading={approve.processing} onClick={() => approve.patch(`/admin/receipts/${r.id}`, { preserveScroll: true })}>
                                    <Check size={16} /> Confirm payment
                                </Button>
                                <Button type="button" variant="secondary" className="h-10 px-3 text-sm" onClick={() => (reject.reset(), reject.clearErrors(), setRejecting(r))}>
                                    <X size={16} /> Reject
                                </Button>
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            <Modal
                open={!!rejecting}
                onClose={() => setRejecting(null)}
                title="Reject this receipt?"
                footer={
                    <>
                        <Button type="button" variant="secondary" onClick={() => setRejecting(null)}>
                            Cancel
                        </Button>
                        <Button type="button" variant="danger" loading={reject.processing} onClick={() => reject.patch(`/admin/receipts/${rejecting.id}`, { preserveScroll: true, onSuccess: () => setRejecting(null) })}>
                            Reject receipt
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-zinc-600">The customer sees your reason on their order page and can upload a new receipt. The order stays unpaid.</p>
                <div className="mt-3 flex flex-wrap gap-2">
                    {QUICK_REASONS.map((q) => (
                        <button key={q} type="button" onClick={() => reject.setData('note', q)} className="rounded-full border border-zinc-200 px-3 py-1 text-xs text-zinc-700 hover:border-zinc-400">
                            {q}
                        </button>
                    ))}
                </div>
                <Textarea label="Reason" className="mt-3" rows={3} maxLength={200} value={reject.data.note} onChange={(e) => reject.setData('note', e.target.value)} error={reject.errors.note} />
            </Modal>
        </Card>
    );
}
