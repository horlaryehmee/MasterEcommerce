import { useForm } from '@inertiajs/react';
import { ExternalLink, ImagePlus, Star, Trash2, Undo2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button, Card, Field, MarkdownField, PageHeader, Select, slugify, Textarea, TextInput, Toggle } from '@/Components/Admin/ui';
import VariantsEditor from '@/Components/Admin/VariantsEditor';
import AdminLayout from '@/Layouts/AdminLayout';
import { putWithFiles } from '@/lib/forms';

function ImageManager({ existing, form, maxImages, maxUploadMb }) {
    const input = useRef(null);
    const [dragging, setDragging] = useState(false);
    const { images: files, remove_images: removed, primary_image: primary } = form.data;

    const previews = useMemo(() => files.map((f) => URL.createObjectURL(f)), [files]);
    useEffect(() => () => previews.forEach(URL.revokeObjectURL), [previews]);

    const keptExisting = existing.filter((img) => !removed.includes(img.id));
    const total = keptExisting.length + files.length;

    const currentPrimary = primary || (existing.find((i) => i.is_primary && !removed.includes(i.id)) ? `existing:${existing.find((i) => i.is_primary).id}` : null);
    const effectivePrimary = currentPrimary || (keptExisting[0] ? `existing:${keptExisting[0].id}` : files.length ? 'new:0' : null);

    const addFiles = (list) => {
        const accepted = Array.from(list).filter((f) => ['image/jpeg', 'image/png', 'image/webp'].includes(f.type));
        const room = Math.max(0, maxImages - total);
        form.setData('images', [...files, ...accepted.slice(0, room)]);
    };

    // "new:3" refers to the 4th new file, so removing one shifts the references after it.
    const shiftRef = (ref, index) => {
        if (ref === `new:${index}`) return '';
        if (ref?.startsWith('new:') && Number(ref.split(':')[1]) > index) return `new:${Number(ref.split(':')[1]) - 1}`;
        return ref;
    };

    const removeNew = (index) => {
        const next = files.filter((_, i) => i !== index);
        form.setData((d) => ({
            ...d,
            images: next,
            primary_image: shiftRef(primary, index),
            variants: d.variants.map((v) => ({
                ...v,
                image: shiftRef(v.image, index),
            })),
        }));
    };

    const toggleRemoveExisting = (id) => {
        const isRemoved = removed.includes(id);
        form.setData((d) => ({
            ...d,
            remove_images: isRemoved ? removed.filter((r) => r !== id) : [...removed, id],
            primary_image: !isRemoved && d.primary_image === `existing:${id}` ? '' : d.primary_image,
            // A variation using a removed photo falls back to the main image.
            variants: isRemoved ? d.variants : d.variants.map((v) => (v.image === `existing:${id}` ? { ...v, image: '' } : v)),
        }));
    };

    const imageErrors = Object.entries(form.errors)
        .filter(([k]) => k.startsWith('images'))
        .map(([, v]) => v);

    const tile = (key, src, isPrimary, onPrimary, onRemove, faded = false, removedState = false) => (
        <div key={key} className={`group relative aspect-square overflow-hidden rounded-xl border-2 bg-zinc-50 ${isPrimary ? 'border-zinc-900' : 'border-zinc-200'}`}>
            <img src={src} alt="" className={`h-full w-full object-cover ${faded ? 'opacity-30' : ''}`} />
            {isPrimary && <span className="absolute top-1.5 left-1.5 rounded-md bg-zinc-900 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">Main</span>}
            <div className="absolute inset-x-1.5 bottom-1.5 flex justify-between gap-1">
                {!removedState && !isPrimary ? (
                    <button
                        type="button"
                        onClick={onPrimary}
                        className="flex items-center gap-1 rounded-md bg-white/95 px-1.5 py-1 text-[11px] font-semibold text-zinc-800 shadow-sm hover:bg-white"
                        title="Use as main image"
                    >
                        <Star size={12} /> Main
                    </button>
                ) : (
                    <span />
                )}
                <button
                    type="button"
                    onClick={onRemove}
                    className={`rounded-md bg-white/95 p-1 shadow-sm hover:bg-white ${removedState ? 'text-zinc-800' : 'text-red-600'}`}
                    aria-label={removedState ? 'Undo remove' : 'Remove image'}
                    title={removedState ? 'Undo' : 'Remove'}
                >
                    {removedState ? <Undo2 size={14} /> : <Trash2 size={14} />}
                </button>
            </div>
        </div>
    );

    return (
        <Card
            title="Images"
            description={`The main image is shown on product cards. Up to ${maxImages} images, ${maxUploadMb} MB each — large phone photos are fine, they're optimised automatically without visible quality loss.`}
        >
            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
                {existing.map((img) => {
                    const isRemoved = removed.includes(img.id);
                    return tile(
                        `e${img.id}`,
                        img.url,
                        !isRemoved && effectivePrimary === `existing:${img.id}`,
                        () => form.setData('primary_image', `existing:${img.id}`),
                        () => toggleRemoveExisting(img.id),
                        isRemoved,
                        isRemoved,
                    );
                })}
                {previews.map((src, i) =>
                    tile(
                        `n${i}`,
                        src,
                        effectivePrimary === `new:${i}`,
                        () => form.setData('primary_image', `new:${i}`),
                        () => removeNew(i),
                    ),
                )}
                {total < maxImages && (
                    <button
                        type="button"
                        onClick={() => input.current?.click()}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={(e) => {
                            e.preventDefault();
                            setDragging(false);
                            addFiles(e.dataTransfer.files);
                        }}
                        className={`flex aspect-square flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed text-xs font-medium transition-colors ${dragging ? 'border-zinc-900 bg-zinc-100' : 'border-zinc-300 text-zinc-500 hover:border-zinc-400 hover:bg-zinc-50'}`}
                    >
                        <ImagePlus size={22} strokeWidth={1.6} />
                        Add images
                    </button>
                )}
            </div>
            <input
                ref={input}
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(e) => {
                    addFiles(e.target.files);
                    e.target.value = '';
                }}
            />
            {removed.length > 0 && <p className="mt-3 text-xs text-amber-700">{removed.length} image(s) will be removed when you save.</p>}
            {imageErrors.length > 0 && <p className="mt-3 text-xs font-medium text-red-600">{imageErrors[0]}</p>}
            {form.progress && (
                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-100">
                    <div className="h-full bg-zinc-900 transition-all" style={{ width: `${form.progress.percentage}%` }} />
                </div>
            )}
        </Card>
    );
}

export default function ProductForm({ product, categories, availabilityOptions, statuses, maxImages, maxUploadMb, maxVariants }) {
    const editing = !!product.id;
    const [slugTouched, setSlugTouched] = useState(editing);

    const form = useForm({
        name: product.name ?? '',
        slug: product.slug ?? '',
        short_description: product.short_description ?? '',
        description: product.description ?? '',
        price: product.price ?? '',
        compare_at_price: product.compare_at_price ?? '',
        category_id: product.category_id ?? '',
        availability: product.availability ?? 'in_stock',
        status: product.status ?? 'published',
        is_featured: product.is_featured ?? false,
        is_new_arrival: product.is_new_arrival ?? false,
        seo_title: product.seo_title ?? '',
        seo_description: product.seo_description ?? '',
        images: [],
        remove_images: [],
        primary_image: '',
        options: product.options ?? [],
        variants: (product.variants ?? []).map((v) => ({
            ...v,
            compare_at_price: v.compare_at_price ?? '',
            sku: v.sku ?? '',
            image: v.image ?? '',
        })),
    });
    const hasVariants = form.data.options.length > 0;

    const set = (key) => (e) => form.setData(key, e.target.value);

    const submit = (e) => {
        e.preventDefault();
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () =>
                form.setData((d) => ({
                    ...d,
                    images: [],
                    remove_images: [],
                    primary_image: '',
                })),
        };
        if (editing) {
            putWithFiles(form, `/admin/products/${product.id}`, options);
        } else {
            form.post('/admin/products', options);
        }
    };

    return (
        <AdminLayout title={editing ? 'Edit product' : 'Add product'}>
            <PageHeader
                back={{ href: '/admin/products', label: 'Products' }}
                title={editing ? product.name : 'Add product'}
                actions={
                    editing && (
                        <a
                            href={product.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 hover:bg-zinc-50"
                        >
                            {form.data.status === 'published' ? 'View on website' : 'Preview'} <ExternalLink size={15} />
                        </a>
                    )
                }
            />

            <form onSubmit={submit} className="grid gap-5 pb-24 sm:gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card title="Product details">
                        <div className="space-y-5">
                            <TextInput
                                label="Product name"
                                value={form.data.name}
                                onChange={(e) => {
                                    const name = e.target.value;
                                    form.setData((d) => ({
                                        ...d,
                                        name,
                                        slug: slugTouched ? d.slug : slugify(name),
                                    }));
                                }}
                                error={form.errors.name}
                                maxLength={160}
                                required
                            />
                            <TextInput
                                label="URL slug"
                                prefix="/"
                                value={form.data.slug}
                                onChange={(e) => {
                                    setSlugTouched(true);
                                    form.setData('slug', slugify(e.target.value));
                                }}
                                error={form.errors.slug}
                                help={`Web address: /products/${form.data.slug || 'product-name'} — created from the name automatically.`}
                                maxLength={180}
                            />
                            <Textarea
                                label="Short description"
                                rows={2}
                                maxLength={500}
                                value={form.data.short_description}
                                onChange={set('short_description')}
                                error={form.errors.short_description}
                                help="One or two sentences shown near the price."
                                optional
                            />
                            <MarkdownField label="Full description" value={form.data.description} onChange={(v) => form.setData('description', v)} error={form.errors.description} rows={12} optional />
                        </div>
                    </Card>

                    <ImageManager existing={product.images ?? []} form={form} maxImages={maxImages} maxUploadMb={maxUploadMb} />

                    <VariantsEditor form={form} existingImages={product.images ?? []} availabilityOptions={availabilityOptions} maxVariants={maxVariants} />

                    <Card title="Search engine listing" description="Optional. Leave blank to use the product name and short description.">
                        <div className="space-y-5">
                            <TextInput label="SEO title" value={form.data.seo_title} onChange={set('seo_title')} error={form.errors.seo_title} maxLength={160} optional />
                            <Textarea
                                label="SEO description"
                                rows={3}
                                maxLength={320}
                                value={form.data.seo_description}
                                onChange={set('seo_description')}
                                error={form.errors.seo_description}
                                optional
                            />
                        </div>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Visibility">
                        <div className="space-y-5">
                            <Select
                                label="Status"
                                value={form.data.status}
                                onChange={set('status')}
                                options={Object.entries(statuses).map(([value, label]) => ({ value, label }))}
                                error={form.errors.status}
                                help={form.data.status === 'draft' ? 'Drafts are hidden from customers.' : 'Visible on your website.'}
                            />
                            <Toggle label="Featured product" description="Show in the homepage Featured section." checked={form.data.is_featured} onChange={(v) => form.setData('is_featured', v)} />
                            <Toggle
                                label="New arrival"
                                description="Show in the homepage New Arrivals section."
                                checked={form.data.is_new_arrival}
                                onChange={(v) => form.setData('is_new_arrival', v)}
                            />
                        </div>
                    </Card>

                    <Card title="Pricing">
                        {hasVariants ? (
                            <p className="text-sm text-zinc-600">This product has variations, so each one has its own price (see Variations). Customers see the lowest as “From …”.</p>
                        ) : (
                            <div className="space-y-5">
                                <TextInput
                                    label="Current price"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={form.data.price}
                                    onChange={set('price')}
                                    error={form.errors.price}
                                    required
                                />
                                <TextInput
                                    label="Previous price"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={form.data.compare_at_price}
                                    onChange={set('compare_at_price')}
                                    error={form.errors.compare_at_price}
                                    help="Shown crossed out to indicate a discount."
                                    optional
                                />
                            </div>
                        )}
                    </Card>

                    <Card title="Organisation">
                        <div className="space-y-5">
                            <Select
                                label="Category"
                                value={form.data.category_id}
                                onChange={set('category_id')}
                                placeholder="No category"
                                options={categories.map((c) => ({
                                    value: c.id,
                                    label: c.name,
                                }))}
                                error={form.errors.category_id}
                            />
                            {hasVariants ? (
                                <p className="text-sm text-zinc-600">Availability is set for each variation.</p>
                            ) : (
                                <Select
                                    label="Availability"
                                    value={form.data.availability}
                                    onChange={set('availability')}
                                    options={Object.entries(availabilityOptions).map(([value, label]) => ({
                                        value,
                                        label,
                                    }))}
                                    error={form.errors.availability}
                                />
                            )}
                        </div>
                    </Card>
                </div>

                {/* Above the phone tab bar; pinned to the bottom of the screen on desktop. */}
                <div className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t border-zinc-200 bg-white/95 shadow-[0_-10px_24px_-20px_rgba(24,24,27,0.45)] backdrop-blur-md lg:bottom-0 lg:left-64 lg:shadow-none">
                    <div className="mx-auto flex max-w-6xl items-center justify-end gap-3 px-4 py-3 sm:px-6 lg:px-8 max-sm:[&>a]:flex-1 max-sm:[&>button]:flex-1">
                        {form.isDirty && (
                            <span className="mr-auto inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-amber-700 sm:text-sm sm:font-normal sm:text-zinc-500">
                                <span className="h-2 w-2 animate-pulse rounded-full bg-amber-500 sm:hidden" aria-hidden />
                                <span className="sm:hidden">Unsaved</span>
                                <span className="hidden sm:inline">You have unsaved changes</span>
                            </span>
                        )}
                        <Button type="button" variant="secondary" href="/admin/products">
                            Cancel
                        </Button>
                        <Button loading={form.processing}>{editing ? 'Save changes' : 'Create product'}</Button>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
