import { useForm } from '@inertiajs/react';
import { Check, Copy, ExternalLink, LayoutTemplate } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useGoogleFont } from '@/Components/Admin/FontPicker';
import { Badge, Button, Modal, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

const FRAME_WIDTH = 1280;

/**
 * Live, scaled-down homepage in the template (a one-request preview: it doesn't
 * change anything, or the admin's own browsing). Loads when scrolled into view.
 */
function Thumbnail({ url, name }) {
    const box = useRef(null);
    const [scale, setScale] = useState(0.25);
    const [visible, setVisible] = useState(false);
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        const el = box.current;
        if (!el) return;
        const ro = new ResizeObserver(() => setScale(el.clientWidth / FRAME_WIDTH));
        ro.observe(el);
        const io = new IntersectionObserver(([e]) => e.isIntersecting && setVisible(true), { rootMargin: '200px' });
        io.observe(el);
        return () => {
            ro.disconnect();
            io.disconnect();
        };
    }, []);

    return (
        <div ref={box} className="relative aspect-[4/3] overflow-hidden bg-zinc-100">
            {!loaded && <div className="absolute inset-0 animate-shimmer bg-zinc-200" aria-hidden />}
            {visible && (
                <iframe
                    src={url}
                    title={`${name} template preview`}
                    tabIndex={-1}
                    loading="lazy"
                    onLoad={() => setLoaded(true)}
                    className={`pointer-events-none absolute top-0 left-0 origin-top-left border-0 transition-opacity duration-500 ${loaded ? 'opacity-100' : 'opacity-0'}`}
                    style={{ width: FRAME_WIDTH, height: (FRAME_WIDTH * 3) / 4, transform: `scale(${scale})` }}
                />
            )}
        </div>
    );
}

function CopyLink({ url }) {
    const [copied, setCopied] = useState(false);
    return (
        <button
            type="button"
            onClick={() =>
                navigator.clipboard?.writeText(url).then(() => {
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1800);
                })
            }
            className="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"
            title="Copy a preview link you can send to anyone"
        >
            {copied ? <Check size={15} className="text-emerald-600" /> : <Copy size={15} />}
            {copied ? 'Copied' : 'Copy link'}
        </button>
    );
}

function TemplateCard({ template, current, onUse }) {
    useGoogleFont(template.fonts.url);
    const isCurrent = template.key === current;

    return (
        <article className={`flex flex-col overflow-hidden rounded-2xl border bg-white shadow-xs transition ${isCurrent ? 'border-zinc-900 ring-1 ring-zinc-900' : 'border-zinc-200 hover:border-zinc-300 hover:shadow-md'}`}>
            <a href={template.preview_url} target="_blank" rel="noopener noreferrer" className="group relative block" aria-label={`Preview ${template.name}`}>
                <Thumbnail url={template.thumbnail_url} name={template.name} />
                <span className="absolute inset-0 flex items-center justify-center bg-zinc-950/0 opacity-0 transition group-hover:bg-zinc-950/35 group-hover:opacity-100">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-sm font-semibold text-zinc-900 shadow-lg">
                        Preview <ExternalLink size={14} />
                    </span>
                </span>
                {isCurrent && (
                    <span className="absolute top-3 left-3">
                        <Badge color="green">Current template</Badge>
                    </span>
                )}
            </a>
            <div className="flex flex-1 flex-col p-4 sm:p-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h2 className="text-xl text-zinc-900" style={{ fontFamily: `'${template.fonts.heading}', serif`, fontWeight: 600 }}>
                            {template.name}
                        </h2>
                        <p className="mt-1 text-sm text-zinc-600">{template.tagline}</p>
                    </div>
                    <span className="flex shrink-0 -space-x-1.5 pt-1" aria-label="Template colours">
                        {template.colors.map((c) => (
                            <span key={c} className="h-5 w-5 rounded-full border-2 border-white shadow-sm ring-1 ring-zinc-200" style={{ backgroundColor: c }} />
                        ))}
                    </span>
                </div>
                <div className="mt-3 flex flex-wrap gap-1.5">
                    {template.best_for.map((b) => (
                        <span key={b} className="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700">
                            {b}
                        </span>
                    ))}
                </div>
                <p className="mt-3 text-xs text-zinc-500">
                    Fonts: {template.fonts.heading}
                    {template.fonts.body !== template.fonts.heading && ` & ${template.fonts.body}`}
                </p>
                <div className="mt-auto flex flex-wrap items-center gap-1 pt-4">
                    <a href={template.preview_url} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-zinc-300 px-3.5 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                        Preview <ExternalLink size={14} />
                    </a>
                    <CopyLink url={template.preview_url} />
                    <span className="ml-auto">
                        {isCurrent ? (
                            <span className="inline-flex h-10 items-center gap-1.5 px-2 text-sm font-semibold text-emerald-700">
                                <Check size={16} /> In use
                            </span>
                        ) : (
                            <Button type="button" onClick={() => onUse(template)}>
                                Use template
                            </Button>
                        )}
                    </span>
                </div>
            </div>
        </article>
    );
}

export default function TemplatesIndex({ templates, current, apply }) {
    const [choosing, setChoosing] = useState(() => templates.find((t) => t.key === apply && t.key !== current) || null);
    const form = useForm({ theme: '', with_style: true });

    const open = (template) => {
        form.setData({ theme: template.key, with_style: true });
        setChoosing(template);
    };
    useEffect(() => {
        if (choosing) form.setData({ theme: choosing.key, with_style: true });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const submit = () => form.put('/admin/templates', { preserveScroll: true, onSuccess: () => setChoosing(null) });

    return (
        <AdminLayout title="Templates">
            <PageHeader title="Templates" description="Choose the design of your website. Previews use your own products and content — nothing changes until you pick one." />

            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                {templates.map((t) => (
                    <TemplateCard key={t.key} template={t} current={current} onUse={open} />
                ))}
            </div>

            <p className="mt-6 flex items-start gap-2 text-sm text-zinc-500">
                <LayoutTemplate size={17} className="mt-0.5 shrink-0" />
                Preview links can be sent to anyone (a client or colleague). They show the template on this website without changing it.
            </p>

            <Modal
                open={!!choosing}
                onClose={() => setChoosing(null)}
                title={choosing ? `Use ${choosing.name}?` : ''}
                footer={
                    <>
                        <Button type="button" variant="secondary" onClick={() => setChoosing(null)}>
                            Cancel
                        </Button>
                        <Button type="button" loading={form.processing} onClick={submit}>
                            Use {choosing?.name}
                        </Button>
                    </>
                }
            >
                {choosing && (
                    <div className="space-y-4">
                        <p>Your website switches to the {choosing.name} layout right away. Your products, photos, pages, prices and settings stay exactly as they are.</p>
                        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 p-3.5">
                            <input type="checkbox" className="mt-0.5 h-4 w-4 rounded border-zinc-300" checked={form.data.with_style} onChange={(e) => form.setData('with_style', e.target.checked)} />
                            <span>
                                <span className="block font-medium text-zinc-900">Also use its colours, fonts and product cards</span>
                                <span className="mt-0.5 block text-sm text-zinc-500">Recommended — it’s how the preview looks. Untick to keep your current brand colours and fonts. You can fine-tune everything later in Website Settings → Branding.</span>
                                <span className="mt-2 flex gap-1.5">
                                    {choosing.colors.map((c) => (
                                        <span key={c} className="h-5 w-5 rounded-full ring-1 ring-zinc-200" style={{ backgroundColor: c }} />
                                    ))}
                                </span>
                            </span>
                        </label>
                    </div>
                )}
            </Modal>
        </AdminLayout>
    );
}
