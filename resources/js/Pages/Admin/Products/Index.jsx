import { Link, router } from '@inertiajs/react';
import { Eye, EyeOff, Package, Pencil, Plus, Search, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

export default function ProductsIndex({ products, filters, categories, statuses }) {
    const [search, setSearch] = useState(filters.search);
    const [deleting, setDeleting] = useState(null);
    const [processing, setProcessing] = useState(false);

    const apply = (changes) => {
        const params = { ...filters, ...changes };
        Object.keys(params).forEach((k) => (params[k] === '' || params[k] === false) && delete params[k]);
        router.get('/admin/products', params, { preserveState: true, preserveScroll: true, replace: true });
    };

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

    const filtered = filters.search || filters.status || filters.category || filters.featured;

    return (
        <AdminLayout title="Products">
            <PageHeader title="Products" description="Add, edit and organise the products shown on your website." fab={{ href: '/admin/products/create', label: 'Add product' }} />

            {/* Phones: one-tap status filters. */}
            <div className="mb-3 md:hidden">
                <FilterChips
                    label="Filter products"
                    value={filters.featured ? 'featured' : filters.status}
                    onChange={(v) => apply(v === 'featured' ? { featured: '1', status: '' } : { status: v, featured: false })}
                    options={[['', 'All'], ...Object.entries(statuses), ['featured', 'Featured']]}
                />
            </div>

            <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                <div className="flex flex-col gap-3 border-b border-zinc-100 p-4 md:flex-row">
                    <form
                        className="relative flex-1"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ search });
                        }}
                    >
                        <Search size={16} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-400" />
                        <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search products and press Enter…" className="input pl-9" aria-label="Search products" />
                    </form>
                    <div className="flex gap-3">
                        <select value={filters.status} onChange={(e) => apply({ status: e.target.value })} className="input hidden md:block md:w-40" aria-label="Filter by status">
                            <option value="">All statuses</option>
                            {Object.entries(statuses).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                        <select value={filters.category} onChange={(e) => apply({ category: e.target.value })} className="input md:w-48" aria-label="Filter by category">
                            <option value="">All categories</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
                {filters.featured && (
                    <div className="flex items-center gap-2 border-b border-zinc-100 px-4 py-2 text-sm text-zinc-600">
                        Showing featured products only.
                        <button type="button" onClick={() => apply({ featured: false })} className="font-medium text-zinc-900 underline">
                            Show all
                        </button>
                    </div>
                )}

                {products.data.length === 0 ? (
                    <AdminEmpty
                        icon={Package}
                        title={filtered ? 'No products match your filters' : 'No products yet'}
                        message={filtered ? 'Try a different search or clear the filters.' : 'Add your first product and it will appear on your website.'}
                        action={
                            filtered ? (
                                <Button variant="secondary" href="/admin/products">
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
                                    <th className="px-4 py-3">Product</th>
                                    <th className="px-4 py-3">Price</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Availability</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100">
                                {products.data.map((p) => (
                                    <tr key={p.id} className="hover:bg-zinc-50/60">
                                        <td className="px-4 py-3">
                                            <Link href={`/admin/products/${p.id}/edit`} className="flex items-center gap-3">
                                                <Thumb src={p.thumb} />
                                                <span className="min-w-0">
                                                    <span className="block max-w-xs truncate font-medium text-zinc-900">{p.name}</span>
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
                        <ul className="divide-y divide-zinc-100 md:hidden">
                            {products.data.map((p) => (
                                <li key={p.id} className="flex items-center gap-1 py-3 pr-2 pl-3.5">
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
                        <AdminPagination paginator={products} />
                    </>
                )}
            </div>

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
