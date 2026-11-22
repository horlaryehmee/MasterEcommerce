import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ExternalLink, Plus, Trash2 } from 'lucide-react';
import { Button, Card, FormActions, ImageField, MarkdownField, PageHeader, TextInput, Textarea, Toggle } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { putWithFiles } from '@/lib/forms';

function FaqEditor({ form }) {
    const items = form.data.faq_items;
    const update = (i, key, value) => form.setData('faq_items', items.map((it, idx) => (idx === i ? { ...it, [key]: value } : it)));
    const move = (i, dir) => {
        const next = [...items];
        [next[i], next[i + dir]] = [next[i + dir], next[i]];
        form.setData('faq_items', next);
    };

    return (
        <Card title="Questions and answers" description="Shown as an expandable list customers can search. Answers support the formatting toolbar, links and placeholders.">
            <div className="space-y-3">
                {items.map((item, i) => (
                    <div key={i} className="rounded-xl border border-zinc-200 p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <span className="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Question {i + 1}</span>
                            <div className="flex gap-0.5">
                                <button type="button" disabled={i === 0} onClick={() => move(i, -1)} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 disabled:opacity-30" aria-label="Move up">
                                    <ArrowUp size={15} />
                                </button>
                                <button type="button" disabled={i === items.length - 1} onClick={() => move(i, 1)} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 disabled:opacity-30" aria-label="Move down">
                                    <ArrowDown size={15} />
                                </button>
                                <button type="button" onClick={() => form.setData('faq_items', items.filter((_, idx) => idx !== i))} className="rounded-md p-1.5 text-red-600 hover:bg-red-50" aria-label="Remove question">
                                    <Trash2 size={15} />
                                </button>
                            </div>
                        </div>
                        <div className="space-y-4">
                            <TextInput label="Question" maxLength={255} value={item.question} onChange={(e) => update(i, 'question', e.target.value)} error={form.errors[`faq_items.${i}.question`]} />
                            <MarkdownField label="Answer" rows={4} value={item.answer} onChange={(v) => update(i, 'answer', v)} error={form.errors[`faq_items.${i}.answer`]} help="" />
                        </div>
                    </div>
                ))}
                {items.length === 0 && <p className="rounded-xl bg-zinc-50 px-4 py-6 text-center text-sm text-zinc-500">No questions yet.</p>}
                {items.length < 50 && (
                    <Button type="button" variant="secondary" onClick={() => form.setData('faq_items', [...items, { question: '', answer: '' }])}>
                        <Plus size={16} /> Add question
                    </Button>
                )}
            </div>
        </Card>
    );
}

/** About page extras: up to three headline numbers shown beside the story. */
function AboutExtras({ form }) {
    const stats = form.data.stats;
    const setStat = (i, key, value) => form.setData('stats', stats.map((s, j) => (j === i ? { ...s, [key]: value } : s)));

    return (
        <Card title="Headline numbers" description="Up to three short facts shown under your story, e.g. 12,000+ Happy customers, 4.9 / 5 Average rating. Leave empty to hide.">
            <div className="space-y-3">
                {stats.map((s, i) => (
                    <div key={i} className="flex items-start gap-2">
                        <div className="w-28 shrink-0 sm:w-32">
                            <input className={`input ${form.errors[`stats.${i}.value`] ? 'input-error' : ''}`} value={s.value} maxLength={20} placeholder="12,000+" onChange={(e) => setStat(i, 'value', e.target.value)} aria-label={`Number ${i + 1}`} />
                        </div>
                        <div className="min-w-0 flex-1">
                            <input className={`input ${form.errors[`stats.${i}.label`] ? 'input-error' : ''}`} value={s.label} maxLength={60} placeholder="Happy customers" onChange={(e) => setStat(i, 'label', e.target.value)} aria-label={`What number ${i + 1} counts`} />
                            {(form.errors[`stats.${i}.value`] || form.errors[`stats.${i}.label`]) && <p className="mt-1 text-xs font-medium text-red-600">{form.errors[`stats.${i}.value`] || form.errors[`stats.${i}.label`]}</p>}
                        </div>
                        <button type="button" onClick={() => form.setData('stats', stats.filter((_, j) => j !== i))} className="rounded-lg p-2.5 text-zinc-500 hover:bg-red-50 hover:text-red-600" aria-label="Remove">
                            <Trash2 size={16} />
                        </button>
                    </div>
                ))}
                {stats.length < 3 && (
                    <Button type="button" variant="secondary" onClick={() => form.setData('stats', [...stats, { value: '', label: '' }])}>
                        <Plus size={16} /> Add a number
                    </Button>
                )}
            </div>
        </Card>
    );
}

export default function PageForm({ page, supportsImage, supportsFaq }) {
    const form = useForm({
        title: page.title ?? '',
        content: page.content ?? '',
        is_published: page.is_published,
        seo_title: page.seo_title ?? '',
        seo_description: page.seo_description ?? '',
        faq_items: page.faq_items ?? [],
        stats: page.stats ?? [],
        image: null,
        remove_image: false,
    });

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => form.setData((d) => ({ ...d, image: null, remove_image: false })) };
        if (supportsImage) {
            putWithFiles(form, `/admin/pages/${page.id}`, options);
        } else {
            form.put(`/admin/pages/${page.id}`, options);
        }
    };

    const contentHelp = 'Tip: {business_name}, {email}, {phone} and {address} are replaced with your business details automatically.';

    return (
        <AdminLayout title={`Edit ${page.title}`}>
            <PageHeader
                back={{ href: '/admin/content?tab=pages', label: 'Website Content' }}
                title={page.title}
                actions={
                    <a href={page.url} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                        View page <ExternalLink size={15} />
                    </a>
                }
            />
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <div className="space-y-5">
                            <TextInput label="Page title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} maxLength={160} required />
                            <MarkdownField
                                label={supportsFaq ? 'Introduction' : supportsImage ? 'Your story' : 'Content'}
                                rows={supportsFaq ? 4 : 18}
                                value={form.data.content}
                                onChange={(v) => form.setData('content', v)}
                                error={form.errors.content}
                                help={supportsFaq ? 'A short line shown under the page title. ' + contentHelp : contentHelp}
                                optional={supportsFaq}
                            />
                        </div>
                    </Card>
                    {supportsFaq && <FaqEditor form={form} />}
                    {supportsImage && <AboutExtras form={form} />}
                </div>
                <div className="space-y-6">
                    {supportsImage && (
                        <Card title="Page image" description="A wide photo shown under the title (about 2400 × 1350 px). If empty, the homepage About image is used.">
                            <ImageField
                                aspect="aspect-[16/9]"
                                currentUrl={page.image_url}
                                file={form.data.image}
                                onFile={(f) => form.setData('image', f)}
                                removed={form.data.remove_image}
                                onRemove={(v) => form.setData('remove_image', v)}
                                error={form.errors.image}
                            />
                            <p className="mt-4 text-xs text-zinc-500">
                                The introduction under the title is your <strong>business description</strong> (Website Settings), and the values section uses your homepage <strong>“Why choose us”</strong> points. The photo beside your story comes from your first category.
                            </p>
                        </Card>
                    )}
                    <Card title="Visibility">
                        <Toggle label="Published" description="Unpublished pages are hidden and removed from the footer." checked={form.data.is_published} onChange={(v) => form.setData('is_published', v)} />
                    </Card>
                    <Card title="Search engine listing">
                        <div className="space-y-5">
                            <TextInput label="SEO title" value={form.data.seo_title} onChange={(e) => form.setData('seo_title', e.target.value)} error={form.errors.seo_title} maxLength={160} optional />
                            <Textarea label="SEO description" rows={3} maxLength={320} value={form.data.seo_description} onChange={(e) => form.setData('seo_description', e.target.value)} error={form.errors.seo_description} optional />
                        </div>
                    </Card>
                    <FormActions dirty={form.isDirty} className="lg:border-0 lg:pt-0 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200/80 max-lg:shadow-xs">
                        <Button loading={form.processing}>Save page</Button>
                    </FormActions>
                </div>
            </form>
        </AdminLayout>
    );
}
