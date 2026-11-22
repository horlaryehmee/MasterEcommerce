import { Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, ExternalLink, FolderTree, Home, Pencil, Plus, Trash2 } from 'lucide-react';
import { BulkBar, BulkDialog, SelectAll, SelectRow, plural, useBulkAction, useSelection } from '@/Components/Admin/Bulk';
import { useState } from 'react';
import { FilterSelect, ListToolbar, activeFilters, useList } from '@/Components/Admin/ListToolbar';
import { ActionMenu, AdminEmpty, AdminPagination, Badge, Button, Modal, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

function DeleteCategoryModal({ category, categories, onClose }) {
    const others = categories.filter((c) => c.id !== category?.id);
    const form = useForm({ products_action: '', reassign_to: '' });
    const hasProducts = category?.products_count > 0;

    const submit = () =>
        form.delete(`/admin/categories/${category.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });

    return (
        <Modal
            open={!!category}
            onClose={onClose}
            title="Delete category?"
            footer={
                <>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="button" variant="danger" loading={form.processing} disabled={hasProducts && !form.data.products_action} onClick={submit}>
                        Delete category
                    </Button>
                </>
            }
        >
            {category && (
                <div className="space-y-4">
                    <p>
                        <strong className="text-zinc-900">{category.name}</strong> will be permanently deleted.
                    </p>
                    {hasProducts && (
                        <div className="space-y-3 rounded-xl bg-amber-50 p-4 text-amber-900">
                            <p className="font-medium">
                                This category contains {category.products_count} product{category.products_count === 1 ? '' : 's'}. What should happen to {category.products_count === 1 ? 'it' : 'them'}?
                            </p>
                            {others.length > 0 && (
                                <label className="flex items-start gap-2">
                                    <input type="radio" name="products_action" className="mt-1" checked={form.data.products_action === 'reassign'} onChange={() => form.setData('products_action', 'reassign')} />
                                    <span className="flex-1">
                                        Move them to another category
                                        {form.data.products_action === 'reassign' && (
                                            <select value={form.data.reassign_to} onChange={(e) => form.setData('reassign_to', e.target.value)} className="input mt-2" aria-label="Move products to">
                                                <option value="">Choose a category…</option>
                                                {others.map((c) => (
                                                    <option key={c.id} value={c.id}>
                                                        {c.name}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </span>
                                </label>
                            )}
                            <label className="flex items-start gap-2">
                                <input type="radio" name="products_action" className="mt-1" checked={form.data.products_action === 'uncategorize'} onChange={() => form.setData('products_action', 'uncategorize')} />
                                <span>Keep them without a category (they stay on the website)</span>
                            </label>
                            {(form.errors.products_action || form.errors.reassign_to) && <p className="text-xs font-medium text-red-600">{form.errors.products_action || form.errors.reassign_to}</p>}
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}

export default function CategoriesIndex({ categories, filters, visibilities, sorts, allCategories, total }) {
    const [deleting, setDeleting] = useState(null);
    const list = useList('/admin/categories', filters, { sort: 'order', per_page: 25 });
    const active = activeFilters(filters, { visibility: visibilities });
    const filtered = filters.search || active.length > 0;

    const sel = useSelection(categories.data);
    const bulk = useBulkAction('/admin/categories/bulk', sel);
    const [dialog, setDialog] = useState(null);
    const n = sel.count === 1 ? '1 category' : `${sel.count} categories`;
    const ask = (d) => {
        bulk.setErrors({});
        setDialog(d);
    };
    const askDelete = () => {
        const products = sel.rows.reduce((t, c) => t + c.products_count, 0);
        ask({
            action: 'delete',
            title: `Delete ${n}?`,
            danger: true,
            confirmLabel: `Delete ${n}`,
            message: products
                ? `${n} will be permanently deleted. They contain ${plural(products, 'product')}: choose what happens to them (they stay on the website).`
                : `${n} will be permanently deleted. This cannot be undone.`,
            choose: products
                ? {
                      label: 'Products in these categories',
                      initial: 'uncategorize',
                      options: [['uncategorize', 'Keep them without a category'], ...allCategories.filter((c) => !sel.has(c.id)).map((c) => [String(c.id), `Move them to “${c.name}”`])],
                  }
                : null,
        });
    };
    const bulkActions = [
        { label: 'Show', icon: Eye, onClick: () => bulk.run('show') },
        { label: 'Hide', icon: EyeOff, onClick: () => bulk.run('hide') },
        { label: 'Delete', icon: Trash2, danger: true, onClick: askDelete },
    ];
    const bulkMore = [
        { label: 'Show on homepage', icon: Home, onClick: () => bulk.run('feature') },
        { label: 'Remove from homepage', icon: Home, onClick: () => bulk.run('unfeature') },
    ];

    return (
        <AdminLayout title="Categories">
            <PageHeader
                title="Categories"
                description="Group your products so customers can browse easily. Lower display order numbers appear first."
                fab={{ href: '/admin/categories/create', label: 'Add category' }}
            />

            <div className="rounded-2xl border border-zinc-200 bg-white shadow-xs">
                {total > 0 && (
                    <ListToolbar list={list} placeholder="Search categories…" sorts={sorts} exportUrl="/admin/categories/export" active={active}>
                        <FilterSelect list={list} name="visibility" label="Show only" options={visibilities} placeholder="All categories" />
                    </ListToolbar>
                )}
                {categories.data.length === 0 ? (
                    <AdminEmpty
                        icon={FolderTree}
                        title={filtered ? 'No categories match' : 'No categories yet'}
                        message={filtered ? 'Try a different search or clear the filters.' : 'Create categories such as “Phones” or “Shoes” to organise your products.'}
                        action={
                            filtered ? (
                                <Button variant="secondary" type="button" onClick={list.clear}>
                                    Clear filters
                                </Button>
                            ) : (
                                <Button href="/admin/categories/create">
                                    <Plus size={16} /> Add category
                                </Button>
                            )
                        }
                    />
                ) : (
                    <>
                    <div className="flex items-center gap-3 border-b border-zinc-100 px-4 py-2.5">
                        <SelectAll selection={sel} />
                        <span className="text-xs font-medium text-zinc-500">{sel.count ? `${sel.count} selected` : 'Select all'}</span>
                    </div>
                    <ul className="divide-y divide-zinc-100">
                        {categories.data.map((c) => (
                            <li key={c.id} className={`relative flex items-center gap-3.5 py-3 pr-2 pl-4 transition active:bg-zinc-50 sm:gap-4 sm:p-4 sm:active:bg-transparent ${sel.has(c.id) ? 'bg-zinc-50' : ''}`}>
                                <SelectRow selection={sel} id={c.id} label={`Select ${c.name}`} />
                                <span className="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-zinc-100 sm:h-14 sm:w-14">{c.image && <img src={c.image} alt="" className="h-full w-full object-cover" loading="lazy" />}</span>
                                <div className="min-w-0 flex-1">
                                    {/* On phones the whole row opens the category (the link covers it). */}
                                    <Link href={`/admin/categories/${c.id}/edit`} className="block truncate text-[15px] font-medium text-zinc-900 after:absolute after:inset-0 hover:underline sm:text-base sm:after:hidden">
                                        {c.name}
                                    </Link>
                                    <div className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                                        <span>
                                            {c.products_count} product{c.products_count === 1 ? '' : 's'}
                                        </span>
                                        <span>·</span>
                                        <span>Order {c.sort_order}</span>
                                        {c.is_published ? <Badge color="green">Visible</Badge> : <Badge color="gray">Hidden</Badge>}
                                        {c.is_featured && <Badge color="amber">On homepage</Badge>}
                                    </div>
                                </div>
                                <div className="relative z-10 shrink-0 sm:hidden">
                                    <ActionMenu
                                        label={c.name}
                                        items={[
                                            { label: 'Edit category', icon: Pencil, href: `/admin/categories/${c.id}/edit` },
                                            { label: 'View on website', icon: ExternalLink, onClick: () => window.open(c.url, '_blank', 'noopener') },
                                            { label: 'Delete', icon: Trash2, danger: true, onClick: () => setDeleting(c) },
                                        ]}
                                    />
                                </div>
                                <div className="hidden shrink-0 items-center gap-0.5 sm:flex">
                                    <a href={c.url} target="_blank" rel="noopener noreferrer" className="hidden rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 sm:block" title="View on website" aria-label="View on website">
                                        <ExternalLink size={17} />
                                    </a>
                                    <Link href={`/admin/categories/${c.id}/edit`} className="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" title="Edit" aria-label="Edit">
                                        <Pencil size={17} />
                                    </Link>
                                    <button type="button" onClick={() => setDeleting(c)} className="rounded-lg p-2 text-zinc-500 hover:bg-red-50 hover:text-red-600" title="Delete" aria-label="Delete">
                                        <Trash2 size={17} />
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <BulkBar selection={sel} noun="category" actions={bulkActions} more={bulkMore} exportUrl="/admin/categories/export" processing={bulk.processing} />
                    <AdminPagination paginator={categories} list={list} />
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

            <DeleteCategoryModal key={deleting?.id} category={deleting} categories={allCategories} onClose={() => setDeleting(null)} />
        </AdminLayout>
    );
}
