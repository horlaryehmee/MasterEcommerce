import { Link, router } from '@inertiajs/react';
import { Copy, Pencil, Plus, Power, TicketPercent, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FilterSelect, ListToolbar, activeFilters, useList } from '@/Components/Admin/ListToolbar';
import { CouponStatusBadge } from '@/Components/Admin/OrderBadges';
import { ActionMenu, AdminEmpty, AdminPagination, Button, ConfirmModal, FilterChips, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

function usage(c) {
    return c.usage_limit ? `${c.used} / ${c.usage_limit} used` : `${c.used} used`;
}

function dates(c) {
    if (c.starts_on && c.expires_on) return `${c.starts_on} – ${c.expires_on}`;
    if (c.expires_on) return `Until ${c.expires_on}`;
    if (c.starts_on) return `From ${c.starts_on}`;
    return 'No expiry';
}

export default function CouponsIndex({ coupons, counts, statuses, types, sorts, filters, total: allCoupons }) {
    const [deleting, setDeleting] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [copied, setCopied] = useState(null);

    const list = useList('/admin/coupons', filters, { sort: 'newest', per_page: 25 });
    const apply = list.apply;
    const active = activeFilters(filters, { type: types });
    const total = Object.values(counts).reduce((a, b) => a + Number(b), 0);
    const filtered = filters.search || filters.status || active.length > 0;
    const tabs = [['', 'All', total], ...Object.entries(statuses).map(([k, l]) => [k, l, counts[k] ?? 0])];

    const toggle = (c) => router.patch(`/admin/coupons/${c.id}/toggle`, {}, { preserveScroll: true });
    const copy = (c) => {
        navigator.clipboard?.writeText(c.code).then(() => {
            setCopied(c.id);
            setTimeout(() => setCopied(null), 1500);
        });
    };
    const destroy = () =>
        router.delete(`/admin/coupons/${deleting.id}`, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDeleting(null);
            },
        });

    return (
        <AdminLayout title="Coupons">
            <PageHeader title="Coupons" description="Discount codes customers enter in the cart or at checkout. One coupon per order." fab={{ href: '/admin/coupons/create', label: 'Add coupon' }} />

            <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                {allCoupons > 0 && (
                    <>
                        <div className="border-b border-zinc-100 px-4 pt-4 pb-3">
                            <FilterChips label="Filter by status" value={filters.status} onChange={(key) => apply({ status: key })} options={tabs} />
                        </div>
                        <ListToolbar list={list} placeholder="Search codes or descriptions…" sorts={sorts} exportUrl="/admin/coupons/export" active={active}>
                            <FilterSelect list={list} name="type" label="Discount type" options={types} placeholder="All types" />
                        </ListToolbar>
                    </>
                )}

                {coupons.data.length === 0 ? (
                    <AdminEmpty
                        icon={TicketPercent}
                        title={filtered ? 'No coupons match' : 'No coupons yet'}
                        message={filtered ? 'Try a different search or status.' : 'Create a code such as “WELCOME10” for 10% off, a fixed amount off, or free shipping.'}
                        action={
                            filtered ? (
                                <Button variant="secondary" type="button" onClick={list.clear}>
                                    Clear filters
                                </Button>
                            ) : (
                                <Button href="/admin/coupons/create">
                                    <Plus size={16} /> Add coupon
                                </Button>
                            )
                        }
                    />
                ) : (
                    <>
                    <ul className="divide-y divide-zinc-100">
                        {coupons.data.map((c) => (
                            <li key={c.id} className="relative flex items-center gap-3.5 py-3.5 pr-2 pl-4 transition active:bg-zinc-50 sm:gap-4 sm:p-4 sm:active:bg-transparent">
                                <span className="hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 sm:flex">
                                    <TicketPercent size={20} />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link href={`/admin/coupons/${c.id}/edit`} className="font-mono text-[15px] font-semibold tracking-wide text-zinc-900 after:absolute after:inset-0 hover:underline sm:after:hidden">
                                            {c.code}
                                        </Link>
                                        <CouponStatusBadge status={c.status} label={c.status_label} />
                                    </div>
                                    <p className="mt-0.5 truncate text-sm text-zinc-700">
                                        {c.offer}
                                        {c.minimum_spend && <span className="text-zinc-500"> · min. {c.minimum_spend}</span>}
                                    </p>
                                    <p className="mt-0.5 flex flex-wrap gap-x-2 text-xs text-zinc-500">
                                        <span>{usage(c)}</span>
                                        <span>·</span>
                                        <span>{dates(c)}</span>
                                        {c.used > 0 && (
                                            <>
                                                <span>·</span>
                                                <span>{c.discount_given} given</span>
                                            </>
                                        )}
                                    </p>
                                </div>
                                <div className="relative z-10 shrink-0 sm:hidden">
                                    <ActionMenu
                                        label={c.code}
                                        items={[
                                            { label: 'Edit coupon', icon: Pencil, href: `/admin/coupons/${c.id}/edit` },
                                            { label: copied === c.id ? 'Copied!' : 'Copy code', icon: Copy, onClick: () => copy(c) },
                                            { label: c.is_active ? 'Switch off' : 'Switch on', icon: Power, onClick: () => toggle(c) },
                                            { label: 'Delete', icon: Trash2, danger: true, onClick: () => setDeleting(c) },
                                        ]}
                                    />
                                </div>
                                <div className="hidden shrink-0 items-center gap-0.5 sm:flex">
                                    <button type="button" onClick={() => copy(c)} className="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" title={copied === c.id ? 'Copied!' : 'Copy code'} aria-label="Copy code">
                                        <Copy size={17} />
                                    </button>
                                    <button type="button" onClick={() => toggle(c)} className={`rounded-lg p-2 hover:bg-zinc-100 ${c.is_active ? 'text-emerald-600' : 'text-zinc-400'}`} title={c.is_active ? 'Switch off' : 'Switch on'} aria-label={c.is_active ? 'Switch off' : 'Switch on'}>
                                        <Power size={17} />
                                    </button>
                                    <Link href={`/admin/coupons/${c.id}/edit`} className="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" title="Edit" aria-label="Edit">
                                        <Pencil size={17} />
                                    </Link>
                                    <button type="button" onClick={() => setDeleting(c)} className="rounded-lg p-2 text-zinc-500 hover:bg-red-50 hover:text-red-600" title="Delete" aria-label="Delete">
                                        <Trash2 size={17} />
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <AdminPagination paginator={coupons} list={list} />
                    </>
                )}
            </div>

            <ConfirmModal
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={destroy}
                processing={processing}
                title="Delete coupon?"
                confirmLabel="Delete coupon"
                message={deleting && (deleting.used > 0 ? `${deleting.code} will stop working. The ${deleting.used} order${deleting.used === 1 ? '' : 's'} that used it keep their discount. To pause it instead, switch it off.` : `${deleting.code} will be permanently deleted.`)}
            />
        </AdminLayout>
    );
}
