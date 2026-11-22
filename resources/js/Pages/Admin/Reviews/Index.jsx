import { router, useForm } from '@inertiajs/react';
import { BadgeCheck, Ban, Check, ExternalLink, MessageSquareText, ShieldAlert, Star, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { BulkBar, BulkDialog, SelectAll, SelectRow, plural, useBulkAction, useSelection } from '@/Components/Admin/Bulk';
import { FilterSelect, ListToolbar, activeFilters, useList } from '@/Components/Admin/ListToolbar';
import { AdminEmpty, AdminPagination, Badge, Button, Card, FilterChips, PageHeader, Select, Toggle } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

const STATUS_COLOR = { pending: 'amber', approved: 'green', rejected: 'gray', spam: 'red' };

function StarsRow({ value }) {
    return (
        <span className="inline-flex" aria-label={`${value} out of 5 stars`}>
            {[1, 2, 3, 4, 5].map((n) => (
                <Star key={n} size={14} className={n <= value ? 'text-amber-400' : 'text-zinc-200'} fill="currentColor" strokeWidth={0} />
            ))}
        </span>
    );
}

/** Show/hide reviews, the homepage section, and auto-publishing. */
function ReviewSettings({ settings, options, home }) {
    const form = useForm(settings);
    const d = form.data;
    const save = (changes) => {
        form.setData({ ...d, ...changes });
        router.put('/admin/reviews/settings', { ...d, ...changes }, { preserveScroll: true });
    };
    const needed = Math.max(0, home.minimum - home.eligible);

    return (
        <Card title="Review settings" className="mb-6">
            <div className="grid gap-5 lg:grid-cols-3">
                <Toggle label="Show reviews on products" description="Customers can read and write reviews on product pages. Turning this off hides all reviews; nothing is deleted." checked={d.reviews_enabled} onChange={(v) => save({ reviews_enabled: v })} />
                <Toggle
                    label="Show reviews on the homepage"
                    description={
                        needed > 0
                            ? `A “What our customers say” section appears once you have ${home.minimum} published 4–5 star reviews (${needed} more to go).`
                            : `A “What our customers say” section with your best reviews (${home.eligible} published 4–5 star reviews).`
                    }
                    checked={d.reviews_home_enabled && d.reviews_enabled}
                    onChange={(v) => save({ reviews_home_enabled: v })}
                />
                <Select label="New reviews" value={d.reviews_auto_publish} onChange={(e) => save({ reviews_auto_publish: e.target.value })} options={Object.entries(options).map(([value, label]) => ({ value, label }))} help="Reviews caught by the spam filters always wait for you." />
            </div>
        </Card>
    );
}

export default function ReviewsIndex({ reviews, filters, statuses, sorts, counts, settings, autoPublishOptions, home }) {
    const list = useList('/admin/reviews', filters, { sort: 'newest', per_page: 25, status: 'pending' });
    const active = activeFilters(filters, { rating: (v) => `${v} star${v === '1' ? '' : 's'}`, verified: () => 'Verified buyers' });
    const total = Object.values(counts).reduce((a, b) => a + Number(b), 0);
    const tabs = [...Object.entries(statuses).map(([k, l]) => [k, k === 'approved' ? 'Published' : k === 'pending' ? 'Waiting' : l, counts[k] ?? 0]), ['all', 'All', total]];

    const sel = useSelection(reviews.data);
    const bulk = useBulkAction('/admin/reviews/bulk', sel);
    const [dialog, setDialog] = useState(null);
    const n = plural(sel.count, 'review');
    const setStatus = (r, status) => router.patch(`/admin/reviews/${r.id}`, { status }, { preserveScroll: true });

    const bulkActions = [
        { label: 'Publish', icon: Check, onClick: () => bulk.run('approve') },
        { label: 'Reject', icon: Ban, onClick: () => bulk.run('reject') },
        { label: 'Spam', icon: ShieldAlert, onClick: () => bulk.run('spam') },
        { label: 'Delete', icon: Trash2, danger: true, onClick: () => setDialog({ action: 'delete', title: `Delete ${n}?`, danger: true, confirmLabel: `Delete ${n}`, message: `${n} will be permanently deleted.` }) },
    ];

    return (
        <AdminLayout title="Reviews">
            <PageHeader title="Reviews" description="Customer reviews of your products. Nothing appears on your website until you publish it." />

            <ReviewSettings settings={settings} options={autoPublishOptions} home={home} />

            <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                <div className="border-b border-zinc-100 px-4 pt-4 pb-3">
                    <FilterChips label="Filter by status" value={filters.status} onChange={(key) => list.apply({ status: key })} options={tabs} />
                </div>
                <ListToolbar list={list} placeholder="Search reviews, names or products…" sorts={sorts} active={active}>
                    <FilterSelect list={list} name="rating" label="Rating" options={[5, 4, 3, 2, 1].map((r) => ({ value: String(r), label: `${r} star${r === 1 ? '' : 's'}` }))} placeholder="Any rating" />
                    <FilterSelect list={list} name="verified" label="Reviewer" options={{ 1: 'Verified buyers only' }} placeholder="Anyone" />
                </ListToolbar>

                {reviews.data.length === 0 ? (
                    <AdminEmpty
                        icon={MessageSquareText}
                        title={filters.status === 'pending' && !filters.search ? 'No reviews waiting' : 'No reviews here'}
                        message={filters.status === 'pending' ? 'New reviews from customers appear here for you to publish.' : 'Try another tab or search.'}
                    />
                ) : (
                    <>
                        <div className="flex items-center gap-3 border-b border-zinc-100 px-4 py-2.5">
                            <SelectAll selection={sel} />
                            <span className="text-xs font-medium text-zinc-500">{sel.count ? `${sel.count} selected` : 'Select all'}</span>
                        </div>
                        <ul className="divide-y divide-zinc-100">
                            {reviews.data.map((r) => (
                                <li key={r.id} className={`flex gap-3 p-4 ${sel.has(r.id) ? 'bg-zinc-50' : ''}`}>
                                    <SelectRow selection={sel} id={r.id} label={`Select review by ${r.name}`} className="mt-0.5" />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StarsRow value={r.rating} />
                                            <Badge color={STATUS_COLOR[r.status]}>{r.status_label}</Badge>
                                            {r.verified && (
                                                <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                                    <BadgeCheck size={13} /> Verified purchase
                                                </span>
                                            )}
                                            <span className="text-xs text-zinc-400" title={r.date}>
                                                {r.ago}
                                            </span>
                                        </div>
                                        {r.title && <p className="mt-2 text-sm font-semibold text-zinc-900">{r.title}</p>}
                                        <p className="mt-1 text-sm whitespace-pre-line text-zinc-700">{r.body}</p>
                                        {r.flags && (
                                            <p className="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-2 py-1 text-xs text-amber-900">
                                                <ShieldAlert size={13} /> Held by spam filter: {r.flags}
                                            </p>
                                        )}
                                        <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                                            <span className="font-medium text-zinc-800">{r.name}</span>
                                            {r.email && <span>{r.email}</span>}
                                            {r.product && (
                                                <a href={r.product.url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1.5 hover:text-zinc-900">
                                                    {r.product.image && <img src={r.product.image} alt="" className="h-5 w-5 rounded object-cover" />}
                                                    {r.product.name} <ExternalLink size={11} />
                                                </a>
                                            )}
                                            {r.order_url && (
                                                <a href={r.order_url} className="underline hover:text-zinc-900">
                                                    View order
                                                </a>
                                            )}
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {r.status !== 'approved' && (
                                                <Button type="button" onClick={() => setStatus(r, 'approved')} className="h-9 px-3 text-sm">
                                                    <Check size={15} /> Publish
                                                </Button>
                                            )}
                                            {r.status === 'approved' && (
                                                <Button type="button" variant="secondary" onClick={() => setStatus(r, 'rejected')} className="h-9 px-3 text-sm">
                                                    <X size={15} /> Unpublish
                                                </Button>
                                            )}
                                            {r.status === 'pending' && (
                                                <Button type="button" variant="secondary" onClick={() => setStatus(r, 'rejected')} className="h-9 px-3 text-sm">
                                                    <Ban size={15} /> Reject
                                                </Button>
                                            )}
                                            {r.status !== 'spam' && (
                                                <Button type="button" variant="secondary" onClick={() => setStatus(r, 'spam')} className="h-9 px-3 text-sm">
                                                    <ShieldAlert size={15} /> Spam
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <BulkBar selection={sel} noun="review" actions={bulkActions} processing={bulk.processing} />
                        <AdminPagination paginator={reviews} list={list} />
                    </>
                )}
            </div>

            <BulkDialog open={!!dialog} onClose={() => setDialog(null)} onConfirm={(value) => bulk.run(dialog.action, value, { onSuccess: () => setDialog(null) })} processing={bulk.processing} error={bulk.errors.ids} {...dialog} />
        </AdminLayout>
    );
}
