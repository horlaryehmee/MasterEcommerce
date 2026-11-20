import { useForm } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { Button, Card, FormActions, ImageField, PageHeader, TextInput, Textarea, Toggle, slugify } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { putWithFiles } from '@/lib/forms';

export default function CategoryForm({ category }) {
    const editing = !!category.id;
    const [slugTouched, setSlugTouched] = useState(editing);
    const form = useForm({
        name: category.name ?? '',
        slug: category.slug ?? '',
        description: category.description ?? '',
        sort_order: category.sort_order ?? 0,
        is_published: category.is_published ?? true,
        is_featured: category.is_featured ?? false,
        seo_title: category.seo_title ?? '',
        seo_description: category.seo_description ?? '',
        image: null,
        remove_image: false,
    });

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            putWithFiles(form, `/admin/categories/${category.id}`);
        } else {
            form.post('/admin/categories', { forceFormData: true });
        }
    };

    return (
        <AdminLayout title={editing ? 'Edit category' : 'Add category'}>
            <PageHeader
                back={{ href: '/admin/categories', label: 'Categories' }}
                title={editing ? category.name : 'Add category'}
                actions={
                    editing && (
                        <a href={category.url} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                            View on website <ExternalLink size={15} />
                        </a>
                    )
                }
            />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card title="Category details">
                        <div className="space-y-5">
                            <TextInput
                                label="Name"
                                value={form.data.name}
                                onChange={(e) => {
                                    const name = e.target.value;
                                    form.setData((d) => ({ ...d, name, slug: slugTouched ? d.slug : slugify(name) }));
                                }}
                                error={form.errors.name}
                                maxLength={120}
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
                                help={`Web address: /categories/${form.data.slug || 'category-name'}`}
                            />
                            <Textarea label="Description" rows={3} maxLength={2000} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} error={form.errors.description} optional />
                        </div>
                    </Card>
                    <Card title="Search engine listing" description="Optional. Leave blank to use the category name and description.">
                        <div className="space-y-5">
                            <TextInput label="SEO title" value={form.data.seo_title} onChange={(e) => form.setData('seo_title', e.target.value)} error={form.errors.seo_title} maxLength={160} optional />
                            <Textarea label="SEO description" rows={3} maxLength={320} value={form.data.seo_description} onChange={(e) => form.setData('seo_description', e.target.value)} error={form.errors.seo_description} optional />
                        </div>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Image">
                        <ImageField
                            aspect="aspect-square"
                            currentUrl={category.image}
                            file={form.data.image}
                            onFile={(f) => form.setData('image', f)}
                            removed={form.data.remove_image}
                            onRemove={(v) => form.setData('remove_image', v)}
                            error={form.errors.image}
                        />
                    </Card>
                    <Card title="Display">
                        <div className="space-y-5">
                            <Toggle label="Visible on website" description="Hidden categories (and their products) are not shown to customers." checked={form.data.is_published} onChange={(v) => form.setData('is_published', v)} />
                            <Toggle label="Show on homepage" description="Include in the homepage “Shop by Category” section." checked={form.data.is_featured} onChange={(v) => form.setData('is_featured', v)} />
                            <TextInput label="Display order" type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} error={form.errors.sort_order} help="Lower numbers appear first." />
                        </div>
                    </Card>
                    <FormActions dirty={form.isDirty} className="lg:border-0 lg:pt-0 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200/80 max-lg:shadow-xs">
                        <Button type="button" variant="secondary" href="/admin/categories">
                            Cancel
                        </Button>
                        <Button loading={form.processing}>{editing ? 'Save changes' : 'Create category'}</Button>
                    </FormActions>
                </div>
            </form>
        </AdminLayout>
    );
}
