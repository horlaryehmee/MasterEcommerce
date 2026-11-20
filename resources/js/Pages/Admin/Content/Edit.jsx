import { Link, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, BadgeCheck, ChevronRight, ExternalLink, FileText, Images, LayoutGrid, Megaphone, Info, PanelBottom, Pencil, Plus, Search, Sparkles, Star, Tag, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge, Button, Card, ColorInput, FormActions, ImageField, MarkdownField, PageHeader, Select, TextInput, Textarea, Toggle } from '@/Components/Admin/ui';
import HeroEditor from '@/Components/Admin/HeroEditor';
import AdminLayout from '@/Layouts/AdminLayout';
import { putWithFiles } from '@/lib/forms';

const TABS = [
    ['announcement', 'Announcement bar'],
    ['hero', 'Hero banner & slider'],
    ['categories', 'Featured categories'],
    ['featured', 'Featured products'],
    ['new_arrivals', 'New arrivals'],
    ['promo', 'Promotional banner'],
    ['about', 'About section'],
    ['why', 'Why choose us'],
    ['footer', 'Footer'],
    ['pages', 'Pages'],
];

/** Phones & tablets: content sections as a menu, in homepage order. */
const MENU = [
    ['Homepage', [
        ['announcement', Megaphone, 'bg-amber-500', 'Thin bar at the very top of every page'],
        ['hero', Images, 'bg-sky-500', 'Big banner or slider at the top'],
        ['categories', LayoutGrid, 'bg-violet-500', '“Shop by Category” section'],
        ['featured', Star, 'bg-yellow-500', 'Hand-picked products'],
        ['new_arrivals', Sparkles, 'bg-pink-500', 'Latest products row'],
        ['promo', Tag, 'bg-rose-500', 'Promotional banner'],
        ['about', Info, 'bg-emerald-500', 'Short story about your business'],
        ['why', BadgeCheck, 'bg-teal-500', 'Reasons to shop with you'],
    ]],
    ['Everywhere', [
        ['footer', PanelBottom, 'bg-zinc-700', 'Footer text and links'],
        ['pages', FileText, 'bg-indigo-500', 'About, Contact, FAQ and policies'],
    ]],
];

const LINK_HELP = 'A page path like /products, a full https:// link, or type whatsapp to open a WhatsApp chat.';

/** Shared form wrapper: one form per section, saved independently. */
function SectionForm({ section, initial, hasFiles = false, title, description, children }) {
    const form = useForm(initial);
    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => hasFiles && form.setData((d) => resetFiles(d)) };
        if (hasFiles) {
            putWithFiles(form, `/admin/content/${section}`, opts);
        } else {
            form.put(`/admin/content/${section}`, opts);
        }
    };
    return (
        <form onSubmit={submit}>
            <Card title={title} description={description}>
                <div className="space-y-5">{children(form)}</div>
                <FormActions dirty={form.isDirty}>
                    <Button loading={form.processing}>Save changes</Button>
                </FormActions>
            </Card>
        </form>
    );
}

function resetFiles(d) {
    const next = { ...d };
    Object.keys(next).forEach((k) => {
        if (next[k] instanceof File) next[k] = null;
        if (k.startsWith('remove_')) next[k] = false;
    });
    return next;
}

const text = (form, key) => ({ value: form.data[key], onChange: (e) => form.setData(key, e.target.value), error: form.errors[key] });
const enabled = (form, key, label = 'Show this section') => <Toggle label={label} checked={form.data[key]} onChange={(v) => form.setData(key, v)} error={form.errors[key]} />;

function Announcement({ home }) {
    return (
        <SectionForm
            section="announcement"
            title="Announcement bar"
            description="A slim message across the top of every page."
            initial={{ announcement_enabled: home.announcement_enabled, announcement_text: home.announcement_text ?? '', announcement_link: home.announcement_link ?? '', announcement_bg_color: home.announcement_bg_color, announcement_text_color: home.announcement_text_color }}
        >
            {(form) => (
                <>
                    {enabled(form, 'announcement_enabled', 'Show announcement bar')}
                    <TextInput label="Message" maxLength={255} {...text(form, 'announcement_text')} />
                    <TextInput label="Link" help={LINK_HELP} optional {...text(form, 'announcement_link')} />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <ColorInput label="Background colour" value={form.data.announcement_bg_color} onChange={(v) => form.setData('announcement_bg_color', v)} error={form.errors.announcement_bg_color} />
                        <ColorInput label="Text colour" value={form.data.announcement_text_color} onChange={(v) => form.setData('announcement_text_color', v)} error={form.errors.announcement_text_color} />
                    </div>
                    <div>
                        <p className="mb-1.5 text-sm font-medium text-zinc-800">Preview</p>
                        <div className="rounded-lg px-4 py-2.5 text-center text-sm font-medium" style={{ backgroundColor: form.data.announcement_bg_color, color: form.data.announcement_text_color }}>
                            {form.data.announcement_text || 'Your announcement text'}
                        </div>
                    </div>
                </>
            )}
        </SectionForm>
    );
}

function FeaturedCategories({ home, categories }) {
    return (
        <SectionForm
            section="categories"
            title="Featured categories"
            description="Choose which categories appear on the homepage. If none are selected, the first eight are shown."
            initial={{ categories_enabled: home.categories_enabled, categories_heading: home.categories_heading ?? '', categories_subheading: home.categories_subheading ?? '', featured_ids: categories.filter((c) => c.is_featured).map((c) => c.id) }}
        >
            {(form) => (
                <>
                    {enabled(form, 'categories_enabled')}
                    <TextInput label="Heading" maxLength={120} {...text(form, 'categories_heading')} />
                    <TextInput label="Subheading" maxLength={255} optional {...text(form, 'categories_subheading')} />
                    <div>
                        <p className="mb-2 text-sm font-medium text-zinc-800">Categories to show</p>
                        {categories.length === 0 ? (
                            <p className="text-sm text-zinc-500">
                                No categories yet.{' '}
                                <Link href="/admin/categories/create" className="font-medium underline">
                                    Create one
                                </Link>
                            </p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {categories.map((c) => (
                                    <label key={c.id} className="flex items-center gap-3 rounded-lg border border-zinc-200 px-3 py-2.5 text-sm hover:bg-zinc-50">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4 rounded border-zinc-300"
                                            checked={form.data.featured_ids.includes(c.id)}
                                            onChange={(e) => form.setData('featured_ids', e.target.checked ? [...form.data.featured_ids, c.id] : form.data.featured_ids.filter((id) => id !== c.id))}
                                        />
                                        <span className="flex-1">{c.name}</span>
                                        {!c.is_published && <Badge>Hidden</Badge>}
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>
                </>
            )}
        </SectionForm>
    );
}

function FeaturedProducts({ home, products }) {
    const [query, setQuery] = useState('');
    return (
        <SectionForm
            section="featured"
            title="Featured products"
            description="Select the products to highlight on the homepage (up to 8 are shown, newest first)."
            initial={{ featured_enabled: home.featured_enabled, featured_heading: home.featured_heading ?? '', featured_subheading: home.featured_subheading ?? '', featured_ids: products.filter((p) => p.is_featured).map((p) => p.id) }}
        >
            {(form) => {
                const list = products.filter((p) => p.name.toLowerCase().includes(query.toLowerCase()));
                return (
                    <>
                        {enabled(form, 'featured_enabled')}
                        <TextInput label="Heading" maxLength={120} {...text(form, 'featured_heading')} />
                        <TextInput label="Subheading" maxLength={255} optional {...text(form, 'featured_subheading')} />
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <p className="text-sm font-medium text-zinc-800">Products</p>
                                <span className="text-xs text-zinc-500">{form.data.featured_ids.length} selected</span>
                            </div>
                            <div className="relative mb-2">
                                <Search size={15} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-zinc-400" />
                                <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Find a product…" className="input pl-9" aria-label="Find a product" />
                            </div>
                            <div className="max-h-96 divide-y divide-zinc-100 overflow-y-auto rounded-lg border border-zinc-200">
                                {list.map((p) => (
                                    <label key={p.id} className="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-zinc-50">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4 rounded border-zinc-300"
                                            checked={form.data.featured_ids.includes(p.id)}
                                            onChange={(e) => form.setData('featured_ids', e.target.checked ? [...form.data.featured_ids, p.id] : form.data.featured_ids.filter((id) => id !== p.id))}
                                        />
                                        <span className="h-9 w-9 shrink-0 overflow-hidden rounded-md bg-zinc-100">{p.thumb && <img src={p.thumb} alt="" className="h-full w-full object-cover" loading="lazy" />}</span>
                                        <span className="flex-1 truncate">{p.name}</span>
                                        {p.status !== 'published' && <Badge>Draft</Badge>}
                                    </label>
                                ))}
                                {list.length === 0 && <p className="px-3 py-6 text-center text-sm text-zinc-500">No products found.</p>}
                            </div>
                            {form.errors.featured_ids && <p className="mt-1.5 text-xs font-medium text-red-600">{form.errors.featured_ids}</p>}
                        </div>
                    </>
                );
            }}
        </SectionForm>
    );
}

function NewArrivals({ home }) {
    return (
        <SectionForm
            section="new_arrivals"
            title="New arrivals"
            description="Shows products marked as “New arrival”. If none are marked, the most recently added products are shown."
            initial={{ new_arrivals_enabled: home.new_arrivals_enabled, new_arrivals_heading: home.new_arrivals_heading ?? '', new_arrivals_subheading: home.new_arrivals_subheading ?? '', new_arrivals_count: home.new_arrivals_count ?? 8 }}
        >
            {(form) => (
                <>
                    {enabled(form, 'new_arrivals_enabled')}
                    <TextInput label="Heading" maxLength={120} {...text(form, 'new_arrivals_heading')} />
                    <TextInput label="Subheading" maxLength={255} optional {...text(form, 'new_arrivals_subheading')} />
                    <Select label="Number of products" options={[4, 8, 12].map((n) => ({ value: n, label: `${n} products` }))} {...text(form, 'new_arrivals_count')} />
                </>
            )}
        </SectionForm>
    );
}

function Promo({ home }) {
    return (
        <SectionForm
            section="promo"
            hasFiles
            title="Promotional banner"
            description="An optional banner to highlight an offer or collection."
            initial={{
                promo_enabled: home.promo_enabled,
                promo_eyebrow: home.promo_eyebrow ?? '',
                promo_heading: home.promo_heading ?? '',
                promo_description: home.promo_description ?? '',
                promo_button_text: home.promo_button_text ?? '',
                promo_button_link: home.promo_button_link ?? '',
                promo_image: null,
                remove_promo_image: false,
            }}
        >
            {(form) => (
                <>
                    {enabled(form, 'promo_enabled')}
                    <TextInput label="Small label" maxLength={80} optional {...text(form, 'promo_eyebrow')} />
                    <TextInput label="Heading" maxLength={160} {...text(form, 'promo_heading')} />
                    <Textarea label="Description" rows={3} maxLength={500} optional {...text(form, 'promo_description')} />
                    <ImageField label="Banner image" currentUrl={home.promo_image_url} file={form.data.promo_image} onFile={(f) => form.setData('promo_image', f)} removed={form.data.remove_promo_image} onRemove={(v) => form.setData('remove_promo_image', v)} error={form.errors.promo_image} />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextInput label="Button text" maxLength={40} optional {...text(form, 'promo_button_text')} />
                        <TextInput label="Button link" help={LINK_HELP} optional {...text(form, 'promo_button_link')} />
                    </div>
                </>
            )}
        </SectionForm>
    );
}

function About({ home }) {
    return (
        <SectionForm
            section="about"
            hasFiles
            title="About section"
            description="A short introduction to your business on the homepage."
            initial={{ about_enabled: home.about_enabled, about_heading: home.about_heading ?? '', about_body: home.about_body ?? '', about_image: null, remove_about_image: false }}
        >
            {(form) => (
                <>
                    {enabled(form, 'about_enabled')}
                    <TextInput label="Heading" maxLength={160} {...text(form, 'about_heading')} />
                    <MarkdownField label="Text" rows={7} value={form.data.about_body} onChange={(v) => form.setData('about_body', v)} error={form.errors.about_body} />
                    <ImageField label="Image" currentUrl={home.about_image_url} file={form.data.about_image} onFile={(f) => form.setData('about_image', f)} removed={form.data.remove_about_image} onRemove={(v) => form.setData('remove_about_image', v)} error={form.errors.about_image} aspect="aspect-[6/5]" />
                </>
            )}
        </SectionForm>
    );
}

function Why({ home, icons }) {
    const iconOptions = Object.entries(icons).map(([value, label]) => ({ value, label }));
    return (
        <SectionForm
            section="why"
            title="Why choose us"
            description="Up to 8 short selling points."
            initial={{ why_enabled: home.why_enabled, why_heading: home.why_heading ?? '', why_subheading: home.why_subheading ?? '', why_items: home.why_items?.length ? home.why_items : [] }}
        >
            {(form) => {
                const items = form.data.why_items;
                const update = (i, key, value) => form.setData('why_items', items.map((it, idx) => (idx === i ? { ...it, [key]: value } : it)));
                const move = (i, dir) => {
                    const next = [...items];
                    [next[i], next[i + dir]] = [next[i + dir], next[i]];
                    form.setData('why_items', next);
                };
                return (
                    <>
                        {enabled(form, 'why_enabled')}
                        <TextInput label="Heading" maxLength={120} {...text(form, 'why_heading')} />
                        <TextInput label="Subheading" maxLength={255} optional {...text(form, 'why_subheading')} />
                        <div className="space-y-3">
                            {items.map((item, i) => (
                                <div key={i} className="rounded-xl border border-zinc-200 p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-xs font-semibold tracking-wide text-zinc-500 uppercase">Point {i + 1}</span>
                                        <div className="flex gap-0.5">
                                            <button type="button" disabled={i === 0} onClick={() => move(i, -1)} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 disabled:opacity-30" aria-label="Move up">
                                                <ArrowUp size={15} />
                                            </button>
                                            <button type="button" disabled={i === items.length - 1} onClick={() => move(i, 1)} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 disabled:opacity-30" aria-label="Move down">
                                                <ArrowDown size={15} />
                                            </button>
                                            <button type="button" onClick={() => form.setData('why_items', items.filter((_, idx) => idx !== i))} className="rounded-md p-1.5 text-red-600 hover:bg-red-50" aria-label="Remove point">
                                                <Trash2 size={15} />
                                            </button>
                                        </div>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-[180px_1fr]">
                                        <Select label="Icon" options={iconOptions} value={item.icon} onChange={(e) => update(i, 'icon', e.target.value)} />
                                        <TextInput label="Title" maxLength={80} value={item.title} onChange={(e) => update(i, 'title', e.target.value)} error={form.errors[`why_items.${i}.title`]} />
                                    </div>
                                    <TextInput className="mt-4" label="Short text" maxLength={255} value={item.text} onChange={(e) => update(i, 'text', e.target.value)} error={form.errors[`why_items.${i}.text`]} />
                                </div>
                            ))}
                            {items.length < 8 && (
                                <Button type="button" variant="secondary" onClick={() => form.setData('why_items', [...items, { icon: 'badge-check', title: '', text: '' }])}>
                                    <Plus size={16} /> Add selling point
                                </Button>
                            )}
                        </div>
                    </>
                );
            }}
        </SectionForm>
    );
}

function Footer({ footer }) {
    return (
        <SectionForm section="footer" title="Footer" description="Contact details in the footer come from Website Settings → Business information." initial={{ footer_description: footer.footer_description ?? '', copyright_text: footer.copyright_text ?? '' }}>
            {(form) => (
                <>
                    <Textarea label="Footer description" rows={3} maxLength={500} help="Leave blank to use your business description." optional {...text(form, 'footer_description')} />
                    <TextInput label="Copyright text" maxLength={200} help="Use {year} for the current year, e.g. © {year} My Business." optional {...text(form, 'copyright_text')} />
                </>
            )}
        </SectionForm>
    );
}

function Pages({ pages }) {
    return (
        <Card title="Pages" description="Informational pages linked in your footer." padded={false}>
            <ul className="divide-y divide-zinc-100">
                {pages.map((p) => (
                    <li key={p.id} className="flex items-center gap-2 px-4 py-3.5 sm:gap-3 sm:px-5">
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-zinc-900">{p.title}</p>
                            <p className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500">
                                <span className="sm:hidden">{p.is_published ? <Badge color="green">Published</Badge> : <Badge>Hidden</Badge>}</span>
                                Updated {p.updated_at}
                            </p>
                        </div>
                        <span className="hidden shrink-0 sm:inline-flex">{p.is_published ? <Badge color="green">Published</Badge> : <Badge>Hidden</Badge>}</span>
                        <a href={p.url} target="_blank" rel="noopener noreferrer" className="shrink-0 rounded-lg p-2 text-zinc-500 hover:bg-zinc-100" aria-label="View page">
                            <ExternalLink size={16} />
                        </a>
                        <Link href={`/admin/pages/${p.id}/edit`} className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-800 hover:bg-zinc-50">
                            <Pencil size={14} /> Edit
                        </Link>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

export default function ContentEdit({ home, footer, categories, products, pages, icons, slides, heroOptions }) {
    const initial = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('tab');
    const [tab, setTab] = useState(() => (TABS.some(([k]) => k === initial) ? initial : 'hero'));
    // Phones show the section menu until a section is opened.
    const [chosen, setChosen] = useState(() => TABS.some(([k]) => k === initial));

    const choose = (key) => {
        setTab(key);
        setChosen(true);
        window.history.replaceState(window.history.state, '', `?tab=${key}`);
        window.scrollTo({ top: 0 });
    };
    const backToMenu = () => {
        setChosen(false);
        window.history.replaceState(window.history.state, '', window.location.pathname);
        window.scrollTo({ top: 0 });
    };
    const label = TABS.find(([k]) => k === tab)?.[1];

    const panels = {
        announcement: <Announcement home={home} />,
        hero: <HeroEditor home={home} slides={slides} options={heroOptions} />,
        categories: <FeaturedCategories home={home} categories={categories} />,
        featured: <FeaturedProducts home={home} products={products} />,
        new_arrivals: <NewArrivals home={home} />,
        promo: <Promo home={home} />,
        about: <About home={home} />,
        why: <Why home={home} icons={icons} />,
        footer: <Footer footer={footer} />,
        pages: <Pages pages={pages} />,
    };

    return (
        <AdminLayout title={chosen ? label : 'Website Content'}>
            <div className="lg:hidden">
                {chosen ? (
                    <div className="mb-5">
                        <button type="button" onClick={backToMenu} className="mb-3 -ml-1 inline-flex items-center gap-0.5 rounded-full bg-white py-1.5 pr-3.5 pl-1.5 text-sm font-medium text-zinc-700 shadow-xs ring-1 ring-zinc-200 transition active:scale-95">
                            <ChevronRight size={18} className="rotate-180" /> Content
                        </button>
                        <h1 className="text-[1.75rem] leading-tight font-bold tracking-tight text-zinc-900">{label}</h1>
                    </div>
                ) : (
                    <>
                        <PageHeader title="Website Content" description="Your homepage sections, footer and pages." />
                        <div className="space-y-6">
                            {MENU.map(([group, rows]) => (
                                <section key={group}>
                                    <h2 className="mb-2 px-1 text-xs font-semibold tracking-wider text-zinc-500 uppercase">{group}</h2>
                                    <ul className="divide-y divide-zinc-100 overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-xs">
                                        {rows.map(([key, Icon, color, hint]) => (
                                            <li key={key}>
                                                <button type="button" onClick={() => choose(key)} className="flex w-full items-center gap-3.5 px-4 py-3.5 text-left transition active:bg-zinc-50">
                                                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-white ${color}`}>
                                                        <Icon size={18} />
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block text-[15px] font-medium text-zinc-900">{TABS.find(([k]) => k === key)?.[1]}</span>
                                                        <span className="block truncate text-[13px] text-zinc-500">{hint}</span>
                                                    </span>
                                                    <ChevronRight size={18} className="shrink-0 text-zinc-300" />
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ))}
                        </div>
                    </>
                )}
            </div>
            <div className="hidden lg:block">
                <PageHeader title="Website Content" description="Update your homepage sections, footer text and information pages." />
            </div>
            <div className={`grid gap-6 lg:grid-cols-[220px_1fr] ${chosen ? '' : 'max-lg:hidden'}`}>
                <div className="hidden lg:block">
                    <nav className="flex flex-col gap-0.5" aria-label="Content sections">
                        {TABS.map(([k, l]) => (
                            <button key={k} type="button" onClick={() => choose(k)} className={`rounded-lg px-3 py-2 text-left text-sm font-medium ${tab === k ? 'bg-white text-zinc-900 shadow-xs ring-1 ring-zinc-200' : 'text-zinc-600 hover:text-zinc-900'}`}>
                                {l}
                            </button>
                        ))}
                    </nav>
                </div>
                <div key={tab}>{panels[tab]}</div>
            </div>
        </AdminLayout>
    );
}
