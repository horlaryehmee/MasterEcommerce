import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ChevronDown, ChevronRight, Lightbulb, Monitor, Search, Smartphone, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Card, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { SECTIONS, guideText } from './guides';

/** "Tap **Save**" → Tap <strong>Save</strong>. */
function Rich({ text }) {
    return text.split(/(\*\*[^*]+\*\*)/g).map((part, i) =>
        part.startsWith('**') ? (
            <strong key={i} className="font-semibold text-zinc-900">
                {part.slice(2, -2)}
            </strong>
        ) : (
            part
        ),
    );
}

/** A screenshot: phone shots in a phone-shaped frame, computer shots full width. Tap to open full size. */
function Shot({ name, alt, caption }) {
    const desktop = name.startsWith('desktop-');
    const src = `/help/${name}.webp`;
    return (
        <figure className={desktop ? 'mt-4' : 'mt-4 flex flex-col items-center'}>
            <a href={src} target="_blank" rel="noopener noreferrer" className={`block overflow-hidden bg-zinc-100 ring-1 ring-zinc-200 transition hover:ring-zinc-400 ${desktop ? 'rounded-xl shadow-sm' : 'w-full max-w-[290px] rounded-[2rem] border-[6px] border-zinc-900 shadow-lg shadow-zinc-900/15'}`} aria-label={`Open screenshot full size: ${alt}`}>
                <img src={src} alt={alt} loading="lazy" decoding="async" className="block h-auto w-full" width={desktop ? 1366 : 390} height={desktop ? 860 : 844} />
            </a>
            <figcaption className="mt-2 flex items-center gap-1.5 text-xs text-zinc-500">
                {desktop ? <Monitor size={13} /> : <Smartphone size={13} />}
                {caption || (desktop ? 'On a computer' : 'On a phone')} · tap to enlarge
            </figcaption>
        </figure>
    );
}

function useGuides() {
    const { admin } = usePage().props;
    const shop = admin?.shop_enabled !== false;
    return useMemo(
        () =>
            SECTIONS.filter((s) => shop || !s.shop)
                .map((s) => ({ ...s, guides: s.guides.filter((g) => shop || !g.shop) }))
                .filter((s) => s.guides.length),
        [shop],
    );
}

const guideHref = (id) => `/admin/help?guide=${id}`;

function GuideLink({ guide, section, showSection = false }) {
    return (
        <Link href={guideHref(guide.id)} className="flex items-center gap-3 px-4 py-3.5 transition hover:bg-zinc-50 active:bg-zinc-100 sm:px-5">
            <span className="min-w-0 flex-1">
                {showSection && <span className="block text-[11px] font-semibold tracking-wide text-zinc-400 uppercase">{section.title}</span>}
                <span className="block text-[15px] font-medium text-zinc-900">{guide.title}</span>
                <span className="mt-0.5 block text-sm text-zinc-500">{guide.summary}</span>
            </span>
            <ChevronRight size={18} className="shrink-0 text-zinc-300" />
        </Link>
    );
}

function HelpHome({ sections }) {
    const [q, setQ] = useState('');
    const query = q.trim().toLowerCase();
    const results = useMemo(() => {
        if (!query) return [];
        const words = query.split(/\s+/);
        return sections.flatMap((s) => s.guides.filter((g) => words.every((w) => guideText(g).includes(w))).map((g) => ({ guide: g, section: s })));
    }, [query, sections]);

    return (
        <>
            <PageHeader title="Help" description="Simple, step-by-step guides for everything in your admin, with pictures." />

            <div className="relative mb-6">
                <Search size={18} className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-zinc-400" />
                <input
                    type="search"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search, e.g. add product"
                    className="h-12 w-full rounded-2xl border border-zinc-200 bg-white pr-11 pl-11 text-[15px] text-zinc-900 shadow-xs placeholder:text-zinc-400 focus:border-zinc-900 focus:ring-0 focus:outline-none"
                    aria-label="Search the help guides"
                />
                {q && (
                    <button type="button" onClick={() => setQ('')} className="absolute top-1/2 right-3 -translate-y-1/2 rounded-full p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700" aria-label="Clear search">
                        <X size={16} />
                    </button>
                )}
            </div>

            {query ? (
                <Card padded={false}>
                    {results.length ? (
                        <div className="divide-y divide-zinc-100">
                            {results.map(({ guide, section }) => (
                                <GuideLink key={guide.id} guide={guide} section={section} showSection />
                            ))}
                        </div>
                    ) : (
                        <p className="p-6 text-center text-sm text-zinc-500">No guides match “{q}”. Try a simpler word, like “order”, “photo” or “payment”.</p>
                    )}
                </Card>
            ) : (
                <div className="grid gap-5 lg:grid-cols-2">
                    {sections.map((s) => (
                        <section key={s.id} className="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-xs">
                            <h2 className="flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5 text-base font-semibold text-zinc-900 sm:px-5">
                                <span className={`flex h-8 w-8 items-center justify-center rounded-lg text-white ${s.color}`}>
                                    <s.icon size={17} />
                                </span>
                                {s.title}
                            </h2>
                            <div className="divide-y divide-zinc-100">
                                {s.guides.map((g) => (
                                    <GuideLink key={g.id} guide={g} section={s} />
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            )}

            <p className="mt-8 text-center text-sm text-zinc-500">Can’t find what you need? Contact the person or company who set up your website.</p>
        </>
    );
}

function Faq({ items }) {
    return (
        <div className="divide-y divide-zinc-100 overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-xs">
            {items.map(([question, answer]) => (
                <details key={question} className="group">
                    <summary className="flex cursor-pointer list-none items-center gap-3 px-4 py-4 text-[15px] font-medium text-zinc-900 sm:px-5 [&::-webkit-details-marker]:hidden">
                        <span className="flex-1">{question}</span>
                        <ChevronDown size={18} className="shrink-0 text-zinc-400 transition group-open:rotate-180" />
                    </summary>
                    <p className="px-4 pb-4 text-[15px] leading-relaxed text-zinc-600 sm:px-5">
                        <Rich text={answer} />
                    </p>
                </details>
            ))}
        </div>
    );
}

function GuideView({ guide, section, prev, next }) {
    return (
        <article className="mx-auto max-w-2xl">
            <PageHeader back={{ href: '/admin/help', label: 'Help' }} title={guide.title} description={guide.summary} />
            <p className="-mt-2 mb-6 flex items-center gap-2 text-xs font-semibold tracking-wide text-zinc-400 uppercase">
                <span className={`flex h-5 w-5 items-center justify-center rounded text-white ${section.color}`}>
                    <section.icon size={12} />
                </span>
                {section.title}
            </p>

            {guide.steps && (
                <ol className="space-y-5">
                    {guide.steps.map((step, i) => (
                        <li key={i} className="rounded-2xl border border-zinc-200/80 bg-white p-4 shadow-xs sm:p-5">
                            <div className="flex gap-3.5">
                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-sm font-bold text-white" aria-hidden>
                                    {i + 1}
                                </span>
                                <p className="pt-1 text-[15px] leading-relaxed text-zinc-700">
                                    <span className="sr-only">Step {i + 1}: </span>
                                    <Rich text={step.t} />
                                </p>
                            </div>
                            {step.img && <Shot name={step.img} alt={step.t.replace(/\*\*/g, '')} />}
                        </li>
                    ))}
                </ol>
            )}

            {guide.faq && <Faq items={guide.faq} />}

            {guide.tips?.length > 0 && (
                <aside className="mt-6 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200/70 sm:p-5">
                    <p className="flex items-center gap-2 text-sm font-semibold text-amber-950">
                        <Lightbulb size={17} /> Good to know
                    </p>
                    <ul className="mt-2 space-y-2 text-[15px] leading-relaxed text-amber-950/90">
                        {guide.tips.map((tip) => (
                            <li key={tip} className="flex gap-2">
                                <span aria-hidden>•</span>
                                <span>
                                    <Rich text={tip} />
                                </span>
                            </li>
                        ))}
                    </ul>
                </aside>
            )}

            {guide.more?.map((m) => (
                <Shot key={m.img} name={m.img} alt={m.caption} caption={m.caption} />
            ))}

            <nav className="mt-8 grid gap-3 sm:grid-cols-2" aria-label="More guides">
                {prev ? (
                    <Link href={guideHref(prev.id)} className="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 text-left transition hover:border-zinc-400">
                        <ArrowLeft size={18} className="shrink-0 text-zinc-400" />
                        <span className="min-w-0">
                            <span className="block text-xs text-zinc-500">Previous</span>
                            <span className="block truncate text-sm font-medium text-zinc-900">{prev.title}</span>
                        </span>
                    </Link>
                ) : (
                    <span className="hidden sm:block" />
                )}
                {next && (
                    <Link href={guideHref(next.id)} className="flex items-center justify-end gap-3 rounded-2xl border border-zinc-200 bg-white p-4 text-right transition hover:border-zinc-400">
                        <span className="min-w-0">
                            <span className="block text-xs text-zinc-500">Next</span>
                            <span className="block truncate text-sm font-medium text-zinc-900">{next.title}</span>
                        </span>
                        <ArrowRight size={18} className="shrink-0 text-zinc-400" />
                    </Link>
                )}
            </nav>
            <div className="mt-4 text-center">
                <Link href="/admin/help" className="text-sm font-medium text-zinc-600 underline hover:text-zinc-900">
                    All guides
                </Link>
            </div>
        </article>
    );
}

export default function HelpIndex() {
    const { url } = usePage();
    const sections = useGuides();
    const id = new URLSearchParams(url.split('?')[1] || '').get('guide');

    const flat = sections.flatMap((s) => s.guides.map((g) => ({ guide: g, section: s })));
    const index = flat.findIndex((x) => x.guide.id === id);
    const current = index >= 0 ? flat[index] : null;

    return (
        <AdminLayout title={current ? current.guide.title : 'Help'}>
            {current ? <GuideView guide={current.guide} section={current.section} prev={flat[index - 1]?.guide} next={flat[index + 1]?.guide} /> : <HelpHome sections={sections} />}
        </AdminLayout>
    );
}
