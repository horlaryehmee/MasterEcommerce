import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ImageIcon, Monitor, Pencil, Plus, Smartphone, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge, Button, Card, ConfirmModal, Field, FormActions, ImageField, Select, TextInput, Textarea, Toggle } from './ui';
import { putWithFiles } from '@/lib/forms';

const LINK_HELP = 'A page path like /products, a full https:// link, or type whatsapp to open a WhatsApp chat.';

/* ------------------------------------------------------------------ */
/* Design picker                                                       */
/* ------------------------------------------------------------------ */

function Thumb({ type, phone }) {
    // Tiny schematic of each design.
    const box = phone ? 'h-20 w-12' : 'h-14 w-24';
    const line = (w, extra = '') => <span className={`block h-1 rounded-full ${w} ${extra}`} />;
    if (type === 'split') {
        return (
            <span className={`flex ${phone ? 'flex-col' : ''} ${box} gap-1 rounded-md bg-stone-100 p-1.5`}>
                <span className="flex flex-1 flex-col justify-center gap-1">
                    {line('w-3/4', 'bg-stone-400')}
                    {line('w-1/2', 'bg-stone-300')}
                    {line('w-1/3', 'bg-stone-800')}
                </span>
                <span className="flex-1 rounded bg-stone-300" />
            </span>
        );
    }
    return (
        <span className={`relative flex ${box} flex-col justify-end gap-1 overflow-hidden rounded-md bg-gradient-to-br from-stone-400 to-stone-600 p-1.5`}>
            {line('w-3/4', 'bg-white/90')}
            {line('w-1/2', 'bg-white/60')}
            {type === 'slider' && (
                <span className="absolute inset-x-0 bottom-1 flex justify-center gap-0.5">
                    <span className="h-1 w-2 rounded-full bg-white" />
                    <span className="h-1 w-1 rounded-full bg-white/60" />
                    <span className="h-1 w-1 rounded-full bg-white/60" />
                </span>
            )}
        </span>
    );
}

function DesignPicker({ label, icon: Icon, value, onChange, types, phone, error }) {
    return (
        <fieldset>
            <legend className="mb-2 flex items-center gap-2 text-sm font-medium text-zinc-800">
                <Icon size={16} className="text-zinc-500" /> {label}
            </legend>
            <div className="grid grid-cols-3 gap-2">
                {Object.entries(types).map(([key, text]) => {
                    const [name, desc] = text.split(' — ');
                    const on = value === key;
                    return (
                        <label key={key} className={`flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 p-3 text-center transition-colors ${on ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-200 hover:border-zinc-300'}`}>
                            <input type="radio" className="sr-only" checked={on} onChange={() => onChange(key)} name={`${label}-design`} />
                            <Thumb type={key} phone={phone} />
                            <span className="text-xs font-semibold text-zinc-900">{name}</span>
                            <span className="hidden text-[11px] leading-snug text-zinc-500 sm:block">{desc}</span>
                        </label>
                    );
                })}
            </div>
            {error && <p className="mt-1.5 text-xs font-medium text-red-600">{error}</p>}
        </fieldset>
    );
}

function Segmented({ label, value, options, onChange }) {
    return (
        <Field label={label}>
            <div className="inline-flex rounded-lg border border-zinc-300 p-0.5">
                {Object.entries(options).map(([key, text]) => (
                    <button key={key} type="button" onClick={() => onChange(key)} className={`rounded-md px-3 py-1.5 text-sm font-medium ${value === key ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:text-zinc-900'}`}>
                        {text}
                    </button>
                ))}
            </div>
        </Field>
    );
}

/* ------------------------------------------------------------------ */
/* Slides                                                              */
/* ------------------------------------------------------------------ */

function SlideEditor({ slide, options, sizes, onDone }) {
    const editing = !!slide?.id;
    const form = useForm({
        content_mode: slide?.content_mode ?? 'text',
        eyebrow: slide?.eyebrow ?? '',
        heading: slide?.heading ?? '',
        description: slide?.description ?? '',
        button_text: slide?.button_text ?? '',
        button_link: slide?.button_link ?? '',
        secondary_text: slide?.secondary_text ?? '',
        secondary_link: slide?.secondary_link ?? '',
        link: slide?.link ?? '',
        text_position: slide?.text_position ?? 'left',
        text_theme: slide?.text_theme ?? 'light',
        is_active: slide?.is_active ?? true,
        desktop_image: null,
        mobile_image: null,
        remove_mobile_image: false,
    });
    const text = (key) => ({ value: form.data[key], onChange: (e) => form.setData(key, e.target.value), error: form.errors[key] });

    const submit = () => {
        const opts = { forceFormData: true, preserveScroll: true, onSuccess: onDone };
        if (editing) putWithFiles(form, `/admin/hero-slides/${slide.id}`, opts);
        else form.post('/admin/hero-slides', opts);
    };

    return (
        <div className="rounded-xl border-2 border-zinc-900 bg-white p-4 sm:p-5">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-zinc-900">{editing ? 'Edit slide' : 'New slide'}</h3>
                <Toggle label="Active" checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />
            </div>

            <div className="grid gap-5 sm:grid-cols-[1.6fr_1fr]">
                <ImageField
                    label="Desktop image"
                    help={`Wide image, ${sizes.desktop} recommended.`}
                    aspect="aspect-[12/5]"
                    currentUrl={slide?.desktop_image_url}
                    file={form.data.desktop_image}
                    onFile={(f) => form.setData('desktop_image', f)}
                    removable={false}
                    error={form.errors.desktop_image}
                />
                <ImageField
                    label="Phone image (optional)"
                    help={`Portrait works best, ${sizes.mobile}. If empty, the desktop image is cropped to fit.`}
                    aspect="aspect-[4/5]"
                    currentUrl={slide?.mobile_image_url}
                    file={form.data.mobile_image}
                    onFile={(f) => form.setData('mobile_image', f)}
                    removed={form.data.remove_mobile_image}
                    onRemove={(v) => form.setData('remove_mobile_image', v)}
                    error={form.errors.mobile_image}
                />
            </div>

            <fieldset className="mt-5">
                <legend className="mb-2 text-sm font-medium text-zinc-800">Slide content</legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {Object.entries(options.modes).map(([key, label]) => (
                        <label key={key} className={`flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 text-sm ${form.data.content_mode === key ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-200'}`}>
                            <input type="radio" checked={form.data.content_mode === key} onChange={() => form.setData('content_mode', key)} />
                            {label}
                        </label>
                    ))}
                </div>
            </fieldset>

            {form.data.content_mode === 'text' ? (
                <div className="mt-5 space-y-5">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextInput label="Small label" maxLength={80} optional {...text('eyebrow')} />
                        <TextInput label="Heading" maxLength={160} {...text('heading')} />
                    </div>
                    <Textarea label="Description" rows={2} maxLength={300} optional {...text('description')} />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextInput label="Button text" maxLength={40} optional {...text('button_text')} />
                        <TextInput label="Button link" help={LINK_HELP} optional {...text('button_link')} />
                        <TextInput label="Second button text" maxLength={40} optional {...text('secondary_text')} />
                        <TextInput label="Second button link" optional {...text('secondary_link')} />
                    </div>
                    <div className="flex flex-wrap gap-6">
                        <Segmented label="Text position (desktop)" value={form.data.text_position} options={options.positions} onChange={(v) => form.setData('text_position', v)} />
                        <Select label="Text colour" value={form.data.text_theme} onChange={(e) => form.setData('text_theme', e.target.value)} options={Object.entries(options.themes).map(([value, label]) => ({ value, label }))} />
                    </div>
                    <p className="text-xs text-zinc-500">On phones the text sits at the bottom of the image. A soft shade is added behind the text so it's always readable.</p>
                </div>
            ) : (
                <TextInput className="mt-5" label="When tapped, go to" help={LINK_HELP} optional {...text('link')} />
            )}

            <div className="mt-6 flex justify-end gap-2 border-t border-zinc-100 pt-4">
                <Button type="button" variant="secondary" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="button" onClick={submit} loading={form.processing}>
                    {editing ? 'Save slide' : 'Add slide'}
                </Button>
            </div>
        </div>
    );
}

function SlidesManager({ slides, options, sizes, inUse }) {
    const [editing, setEditing] = useState(null); // slide id, 'new', or null
    const [deleting, setDeleting] = useState(null);

    const move = (i, dir) => {
        const ids = slides.map((s) => s.id);
        [ids[i], ids[i + dir]] = [ids[i + dir], ids[i]];
        router.post('/admin/hero-slides/reorder', { ids }, { preserveScroll: true });
    };

    return (
        <Card
            title="Slides"
            description={inUse ? 'Shown in the banner slider. Change the order with the arrows; hidden slides are skipped.' : 'Choose “Banner slider” for desktop or phones above to show these slides.'}
            actions={!inUse && <Badge>Not in use</Badge>}
        >
            <div className="space-y-3">
                {slides.map((s, i) =>
                    editing === s.id ? (
                        <SlideEditor key={s.id} slide={s} options={options} sizes={sizes} onDone={() => setEditing(null)} />
                    ) : (
                        <div key={s.id} className="flex items-center gap-3 rounded-xl border border-zinc-200 p-3">
                            <span className="relative h-14 w-28 shrink-0 overflow-hidden rounded-lg bg-zinc-100">
                                {s.desktop_image_url && <img src={s.desktop_image_url} alt="" className="h-full w-full object-cover" />}
                                {s.mobile_image_url && <img src={s.mobile_image_url} alt="" className="absolute right-1 bottom-1 h-10 w-8 rounded border-2 border-white object-cover shadow" title="Phone image" />}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-zinc-900">{s.content_mode === 'image' ? 'Image-only slide' : s.heading}</p>
                                <div className="mt-1 flex flex-wrap gap-1.5">
                                    {s.is_active ? <Badge color="green">Active</Badge> : <Badge>Hidden</Badge>}
                                    {!s.mobile_image_url && <Badge color="amber">No phone image</Badge>}
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center">
                                <button type="button" disabled={i === 0} onClick={() => move(i, -1)} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 disabled:opacity-30" aria-label="Move up">
                                    <ArrowUp size={16} />
                                </button>
                                <button type="button" disabled={i === slides.length - 1} onClick={() => move(i, 1)} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 disabled:opacity-30" aria-label="Move down">
                                    <ArrowDown size={16} />
                                </button>
                                <button type="button" onClick={() => setEditing(s.id)} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" aria-label="Edit slide">
                                    <Pencil size={16} />
                                </button>
                                <button type="button" onClick={() => setDeleting(s)} className="rounded-md p-1.5 text-zinc-500 hover:bg-red-50 hover:text-red-600" aria-label="Delete slide">
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        </div>
                    ),
                )}

                {slides.length === 0 && editing !== 'new' && (
                    <div className="flex flex-col items-center rounded-xl border border-dashed border-zinc-300 px-6 py-10 text-center">
                        <ImageIcon size={26} className="text-zinc-400" />
                        <p className="mt-3 text-sm font-medium text-zinc-800">No slides yet</p>
                        <p className="mt-1 text-xs text-zinc-500">Add banner images — with a separate design for phones if you like.</p>
                    </div>
                )}

                {editing === 'new' ? (
                    <SlideEditor options={options} sizes={sizes} onDone={() => setEditing(null)} />
                ) : (
                    slides.length < 8 && (
                        <Button type="button" variant="secondary" onClick={() => setEditing('new')}>
                            <Plus size={16} /> Add slide
                        </Button>
                    )
                )}
            </div>

            <ConfirmModal
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => router.delete(`/admin/hero-slides/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
                title="Delete slide?"
                message="This slide and its images will be permanently deleted."
            />
        </Card>
    );
}

/* ------------------------------------------------------------------ */
/* Hero section                                                        */
/* ------------------------------------------------------------------ */

export default function HeroEditor({ home, slides, options }) {
    const form = useForm({
        hero_enabled: home.hero_enabled,
        hero_type_desktop: home.hero_type_desktop ?? 'split',
        hero_type_mobile: home.hero_type_mobile ?? 'split',
        hero_width: home.hero_width ?? 'full',
        hero_text_align: home.hero_text_align ?? 'left',
        hero_autoplay: home.hero_autoplay ?? true,
        hero_interval: home.hero_interval ?? 6,
        hero_desktop_height: home.hero_desktop_height ?? 'standard',
        hero_mobile_height: home.hero_mobile_height ?? 'portrait',
        hero_mobile_text: home.hero_mobile_text ?? 'overlay',
        hero_eyebrow: home.hero_eyebrow ?? '',
        hero_heading: home.hero_heading ?? '',
        hero_description: home.hero_description ?? '',
        hero_primary_text: home.hero_primary_text ?? '',
        hero_primary_link: home.hero_primary_link ?? '',
        hero_secondary_text: home.hero_secondary_text ?? '',
        hero_secondary_link: home.hero_secondary_link ?? '',
        hero_image: null,
        remove_hero_image: false,
        hero_mobile_image: null,
        remove_hero_mobile_image: false,
    });
    const d = form.data;
    const text = (key) => ({ value: d[key], onChange: (e) => form.setData(key, e.target.value), error: form.errors[key] });
    const types = [d.hero_type_desktop, d.hero_type_mobile];
    const usesSlider = types.includes('slider');
    const usesSingle = types.includes('split') || types.includes('banner');
    const usesFullImage = types.includes('slider') || types.includes('banner');
    const sizes = { desktop: options.desktopHeights[d.hero_desktop_height]?.[1], mobile: options.mobileHeights[d.hero_mobile_height]?.[1] };
    const devicesFor = (...t) => [d.hero_type_desktop, d.hero_type_mobile].map((x, i) => (t.includes(x) ? ['Desktop', 'Phones'][i] : null)).filter(Boolean).join(' & ');

    const submit = (e) => {
        e.preventDefault();
        putWithFiles(form, '/admin/content/hero', {
            preserveScroll: true,
            onSuccess: () => form.setData((x) => ({ ...x, hero_image: null, hero_mobile_image: null, remove_hero_image: false, remove_hero_mobile_image: false })),
        });
    };

    return (
        <div className="space-y-6">
            <form onSubmit={submit} className="space-y-6">
                <Card title="Hero design" description="The large banner at the top of your homepage. Choose a design for computers and tablets, and one for phones.">
                    <div className="space-y-6">
                        <Toggle label="Show the hero section" checked={d.hero_enabled} onChange={(v) => form.setData('hero_enabled', v)} />
                        <div className="grid gap-6 lg:grid-cols-2">
                            <DesignPicker label="Desktop & tablet" icon={Monitor} value={d.hero_type_desktop} onChange={(v) => form.setData('hero_type_desktop', v)} types={options.types} error={form.errors.hero_type_desktop} />
                            <DesignPicker label="Phones" icon={Smartphone} phone value={d.hero_type_mobile} onChange={(v) => form.setData('hero_type_mobile', v)} types={options.types} error={form.errors.hero_type_mobile} />
                        </div>

                        {usesFullImage && (
                            <div className="grid gap-5 border-t border-zinc-100 pt-5 sm:grid-cols-3">
                                <Select label="Banner width" options={Object.entries(options.widths).map(([value, label]) => ({ value, label }))} {...text('hero_width')} />
                                <Select label="Height on desktop" options={Object.entries(options.desktopHeights).map(([value, [label, size]]) => ({ value, label: `${label} · ${size}` }))} {...text('hero_desktop_height')} />
                                <Select label="Height on phones" options={Object.entries(options.mobileHeights).map(([value, [label, size]]) => ({ value, label: `${label} · ${size}` }))} {...text('hero_mobile_height')} />
                                {['slider', 'banner'].includes(d.hero_type_mobile) && (
                                    <Select
                                        className="sm:col-span-3"
                                        label="Text on phones"
                                        help="“Below the image” keeps your banner fully visible — best for photos with products. “On the image” looks bolder but can cover part of the picture."
                                        options={Object.entries(options.mobileText).map(([value, label]) => ({ value, label }))}
                                        {...text('hero_mobile_text')}
                                    />
                                )}
                            </div>
                        )}

                        {usesSlider && (
                            <div className="grid items-end gap-5 sm:grid-cols-2">
                                <Toggle label="Play slides automatically" description="Pauses when a visitor hovers or taps the pause button." checked={d.hero_autoplay} onChange={(v) => form.setData('hero_autoplay', v)} />
                                <Select label="Time per slide" disabled={!d.hero_autoplay} options={[4, 5, 6, 8, 10].map((n) => ({ value: n, label: `${n} seconds` }))} {...text('hero_interval')} />
                            </div>
                        )}
                    </div>
                </Card>

                <Card
                    title="Hero text and image"
                    description={usesSingle ? `Used by the ${types.includes('split') && types.includes('banner') ? 'split and banner designs' : types.includes('banner') ? 'full banner design' : 'split design'} (${devicesFor('split', 'banner')}).` : 'Not used right now — both devices show the slider. Kept as a fallback if all slides are hidden.'}
                    actions={!usesSingle && <Badge>Fallback</Badge>}
                >
                    <div className="space-y-5">
                        <TextInput label="Small label above the heading" maxLength={80} optional {...text('hero_eyebrow')} />
                        <TextInput label="Heading" maxLength={160} {...text('hero_heading')} />
                        <Textarea label="Description" rows={3} maxLength={500} optional {...text('hero_description')} />
                        <div className="grid gap-5 sm:grid-cols-[1.4fr_1fr]">
                            <ImageField
                                label="Image"
                                help={types.includes('banner') ? `For the full banner, ${sizes.desktop} works best.` : 'Landscape, about 1600 × 1200 px.'}
                                currentUrl={home.hero_image_url}
                                file={d.hero_image}
                                onFile={(f) => form.setData('hero_image', f)}
                                removed={d.remove_hero_image}
                                onRemove={(v) => form.setData('remove_hero_image', v)}
                                error={form.errors.hero_image}
                                aspect="aspect-[4/3]"
                            />
                            <ImageField
                                label="Phone image (optional)"
                                help="A different crop or design for phones."
                                currentUrl={home.hero_mobile_image_url}
                                file={d.hero_mobile_image}
                                onFile={(f) => form.setData('hero_mobile_image', f)}
                                removed={d.remove_hero_mobile_image}
                                onRemove={(v) => form.setData('remove_hero_mobile_image', v)}
                                error={form.errors.hero_mobile_image}
                                aspect="aspect-[4/5]"
                            />
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextInput label="Main button text" maxLength={40} {...text('hero_primary_text')} />
                            <TextInput label="Main button link" help={LINK_HELP} {...text('hero_primary_link')} />
                            <TextInput label="Second button text" maxLength={40} optional {...text('hero_secondary_text')} />
                            <TextInput label="Second button link" optional {...text('hero_secondary_link')} />
                        </div>
                        {types.includes('banner') && <Segmented label="Banner text alignment" value={d.hero_text_align} options={{ left: 'Left', center: 'Centre' }} onChange={(v) => form.setData('hero_text_align', v)} />}
                    </div>
                </Card>

                <FormActions dirty={form.isDirty} className="lg:border-0 lg:pt-0 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200/80 max-lg:shadow-xs">
                    <Button loading={form.processing}>Save hero settings</Button>
                </FormActions>
            </form>

            <SlidesManager slides={slides} options={options} sizes={sizes} inUse={usesSlider} />
        </div>
    );
}
