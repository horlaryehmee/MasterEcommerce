import { Link, router } from '@inertiajs/react';
import { Boxes, Eye, EyeOff, FolderInput, Package, Pencil, Plus, Sparkles, Star, StarOff, Trash2 } from 'lucide-react';
import { BulkBar, BulkDialog, SelectAll, SelectRow, plural, useBulkAction, useSelection } from '@/Components/Admin/Bulk';
import { useState } from 'react';
import { FilterSelect, ListToolbar, activeFilters, useList } from '@/Components/Admin/ListToolbar';
import { ActionMenu, AdminEmpty, AdminPagination, Badge, Button, ConfirmModal, FilterChips, PageHeader, StatusBadge } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

function Thumb({ src, size = 'h-12 w-12 rounded-lg' }) {
    return (
        <span className={`flex shrink-0 items-center justify-center overflow-hidden bg-zinc-100 text-zinc-300 ${size}`}>
            {src ? <img src={src} alt="" loading="lazy" className="h-full w-full object-cover" /> : <Package size={20} />}
        </span>
    );
}

/** Phones: the same actions as the desktop icon row, in a "⋯" sheet. */
function MobileActions({ product, onDelete }) {
    const toggle = (field) => router.patch(`/admin/products/${product.id}/toggle`, { field }, { preserveScroll: true });
    const published = product.status === 'published';
    return (
        <ActionMenu
            label={product.name}
            items={[
                { label: 'Edit product', icon: Pencil, href: `/admin/products/${product.id}/edit` },
                { label: published ? 'Unpublish (move to drafts)' : 'Publish', icon: published ? EyeOff : Eye, onClick: () => toggle('status') },
                { label: product.is_featured ? 'Remove from featured' : 'Mark as featured', icon: Star, onClick: () => toggle('is_featured') },
                { label: 'Delete', icon: Trash2, danger: true, onClick: () => onDelete(product) },
            ]}
        />
    );
}

function RowActions({ product, onDelete }) {
    const toggle = (field) => router.patch(`/admin/products/${product.id}/toggle`, { field }, { preserveScroll: true });
    const icon = 'rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900';
    const published = product.status === 'published';
    return (
        <div className="flex items-center justify-end gap-0.5">
            <button type="button" onClick={() => toggle('status')} className={icon} title={published ? 'Unpublish (move to drafts)' : 'Publish'} aria-label={published ? 'Unpublish' : 'Publish'}>
                {published ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
            <button type="button" onClick={() => toggle('is_featured')} className={icon} title={product.is_featured ? 'Remove from featured' : 'Mark as featured'} aria-label="Toggle featured">
                <Star size={17} className={product.is_featured ? 'fill-amber-400 text-amber-500' : ''} />
            </button>
            <Link href={`/admin/products/${product.id}/edit`} className={icon} title="Edit" aria-label="Edit">
                <Pencil size={17} />
            </Link>
            <button type="button" onClick={() => onDelete(product)} className="rounded-lg p-2 text-zinc-500 hover:bg-red-50 hover:text-red-600" title="Delete" aria-label="Delete">
                <Trash2 size={17} />
            </button>
        </div>
    );
}

export default function ProductsIndex({ products, filters, categories, statuses, availabilities, tags, sorts }) {
    const [deleting, setDeleting] = useState(null);
    const [processing, setProcessing] = useState(false);
    const list = useList('/admin/products', filters, { sort: 'updated', per_page: 25 });
    const apply = list.apply;
    const active = activeFilters(filters, {
        category: [...categories.map((c) => ({ value: String(c.id), label: c.name })), { value: 'none', label: 'Uncategorised' }],
        availability: availabilities,
        tag: tags,
    });

    const destroy = () => {
        router.delete(`/admin/products/${deleting.id}`, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDeleting(null);
            },
        });
    };

    const filtered = filters.search || filters.status || active.length > 0;

    const sel = useSelection(products.data);
    const bulk = useBulkAction('/admin/products/bulk', sel);
    const [dialog, setDialog] = useState(null);
    const n = plural(sel.count, 'product');
    const ask = (d) => {
        bulk.setErrors({});
        setDialog(d);
    };
    const categoryOptions = [...categories.map((c) => [String(c.id), c.name]), ['none', 'No category (uncategorised)']];
    const bulkActions = [
        { label: 'Publish', icon: Eye, onClick: () => bulk.run('publish') },
        { label: 'Unpublish', icon: EyeOff, onClick: () => bulk.run('draft') },
        { label: 'Delete', icon: Trash2, danger: true, onClick: () => ask({ action: 'delete', title: `Delete ${n}?`, danger: true, confirmLabel: `Delete ${n}`, message: `${n} and their images will be permanently deleted. This cannot be undone. To hide them temporarily, unpublish them instead.` }) },
    ];
    const bulkMore = [
        { label: 'Mark as featured', icon: Star, onClick: () => bulk.run('feature') },
        { label: 'Remove from featured', icon: StarOff, onClick: () => bulk.run('unfeature') },
        { label: 'Mark as new arrival', icon: Sparkles, onClick: () => bulk.run('new_arrival') },
        { label: 'Remove “new arrival”', icon: Sparkles, onClick: () => bulk.run('not_new_arrival') },
        { label: 'Set availability…', icon: Boxes, onClick: () => ask({ action: 'availability', title: `Set availability of ${n}`, confirmLabel: 'Update', choose: { label: 'Availability', options: Object.entries(availabilities) } }) },
        { label: 'Move to category…', icon: FolderInput, onClick: () => ask({ action: 'category', title: `Move ${n} to a category`, confirmLabel: 'Move', choose: { label: 'Category', options: categoryOptions } }) },
    ];

    return (
        <AdminLayout title="Products">
            <PageHeader title="Products" description="Add, edit and organise the products shown on your website." fab={{ href: '/admin/products/create', label: 'Add product' }} />

            <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                <div className="border-b border-zinc-100 px-4 pt-4 pb-3">
                    <FilterChips label="Filter by status" value={filters.status} onChange={(v) => apply({ status: v })} options={[['', 'All'], ...Object.entries(statuses)]} />
                </div>
                <ListToolbar list={list} placeholder="Search products…" sorts={sorts} exportUrl="/admin/products/export" active={active}>
                    <FilterSelect list={list} name="category" label="Category" options={[...categories.map((c) => ({ value: String(c.id), label: c.name })), { value: 'none', label: 'Uncategorised' }]} placeholder="All categories" />
                    <FilterSelect list={list} name="availability" label="Availability" options={availabilities} />
                    <FilterSelect list={list} name="tag" label="Show only" options={tags} placeholder="All products" />
                </ListToolbar>

                {products.data.length === 0 ? (
                    <AdminEmpty
                        icon={Package}
                        title={filtered ? 'No products match your filters' : 'No products yet'}
                        message={filtered ? 'Try a different search or clear the filters.' : 'Add your first product and it will appear on your website.'}
                        action={
                            filtered ? (
                                <Button variant="secondary" type="button" onClick={list.clear}>
                                    Clear filters
                                </Button>
                            ) : (
                                <Button href="/admin/products/create">
                                    <Plus size={16} /> Add product
                                </Button>
                            )
                        }
                    />
                ) : (
                    <>
                        {/* Desktop table */}
                        <table className="hidden w-full text-left text-sm md:table">
                            <thead className="border-b border-zinc-100 text-xs font-semibold tracking-wide text-zinc-500 uppercase">
                                <tr>
                                    <th className="w-10 py-3 pl-4">
                                        <SelectAll selection={sel} />
                                    </th>
                                    <th className="px-4 py-3">Product</th>
                                    <th className="px-4 py-3">Price</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Availability</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100">
                                {products.data.map((p) => (
                                    <tr key={p.id} className={sel.has(p.id) ? 'bg-zinc-50' : 'hover:bg-zinc-50/60'}>
                                        <td className="py-3 pl-4">
                                            <SelectRow selection={sel} id={p.id} label={`Select ${p.name}`} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link href={`/admin/products/${p.id}/edit`} className="flex items-center gap-3">
                                                <Thumb src={p.thumb} />
                                                <span className="min-w-0">
                                                    <span className="block max-w-[15rem] truncate font-medium text-zinc-900 xl:max-w-xs">{p.name}</span>
                                                    <span className="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-500">
                                                        {p.category || 'Uncategorised'}
                                                        {p.is_new_arrival && <Badge color="blue">New</Badge>}
                                                    </span>
                                                </span>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className="font-medium text-zinc-900">{p.price}</span>
                                            {p.compare_at_price && <span className="ml-1.5 text-xs text-zinc-400 line-through">{p.compare_at_price}</span>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={p.status} />
                                        </td>
                                        <td className="px-4 py-3 text-zinc-600">{p.availability}</td>
                                        <td className="px-4 py-3">
                                            <RowActions product={p} onDelete={setDeleting} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {/* Mobile list */}
                        <div className="flex items-center gap-3 border-b border-zinc-100 px-4 py-2.5 md:hidden">
                            <SelectAll selection={sel} />
                            <span className="text-xs font-medium text-zinc-500">{sel.count ? `${sel.count} selected` : 'Select all'}</span>
                        </div>
                        <ul className="divide-y divide-zinc-100 md:hidden">
                            {products.data.map((p) => (
                                <li key={p.id} className={`flex items-center gap-1 py-3 pr-2 pl-4 ${sel.has(p.id) ? 'bg-zinc-50' : ''}`}>
                                    <SelectRow selection={sel} id={p.id} label={`Select ${p.name}`} className="mr-1.5" />
                                    <Link href={`/admin/products/${p.id}/edit`} className="flex min-w-0 flex-1 items-center gap-3.5 rounded-xl transition active:scale-[0.99] active:opacity-80">
                                        <Thumb src={p.thumb} size="h-16 w-16 rounded-xl" />
                                        <span className="min-w-0 flex-1">
                                            <span className="line-clamp-2 text-[15px] leading-snug font-medium text-zinc-900">{p.name}</span>
                                            <span className="mt-1 flex items-baseline gap-1.5">
                                                <span className="text-sm font-semibold text-zinc-900">{p.price}</span>
                                                {p.compare_at_price && <span className="text-xs text-zinc-400 line-through">{p.compare_at_price}</span>}
                                            </span>
                                            <span className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                                <StatusBadge status={p.status} />
                                                {p.is_featured && (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-600/20 ring-inset">
                                                        <Star size={11} className="fill-amber-400 text-amber-500" /> Featured
                                                    </span>
                                                )}
                                                {p.is_new_arrival && <Badge color="blue">New</Badge>}
                                            </span>
                                        </span>
                                    </Link>
                                    <MobileActions product={p} onDelete={setDeleting} />
                                </li>
                            ))}
                        </ul>
                        <BulkBar selection={sel} noun="product" actions={bulkActions} more={bulkMore} exportUrl="/admin/products/export" processing={bulk.processing} />
                        <AdminPagination paginator={products} list={list} />
                    </>
                )}
            </div>

            <BulkDialog
                open={!!dialog}
                onClose={() => setDialog(null)}
                onConfirm={(value) => bulk.run(dialog.action, value, { onSuccess: () => setDialog(null) })}
                processing={bulk.processing}
                error={bulk.errors.value || bulk.errors.ids}
                {...dialog}
            />

            <ConfirmModal
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={destroy}
                processing={processing}
                title="Delete product?"
                message={
                    <>
                        <strong className="text-zinc-900">{deleting?.name}</strong> and its images will be permanently deleted. This cannot be undone. To hide it temporarily, unpublish it instead.
                    </>
                }
            />
        </AdminLayout>
    );
}
