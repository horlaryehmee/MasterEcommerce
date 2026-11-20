import { Mail, MessageCircleQuestion, Phone, Plus, Search, X } from 'lucide-react';
import { useEffect, useId, useMemo, useState } from 'react';
import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import WhatsAppButton from '@/Components/Store/WhatsAppButton';
import StoreLayout from '@/Layouts/StoreLayout';

function FaqItem({ item, open, onToggle }) {
    const id = useId();
    return (
        <div className="border-b border-stone-200 last:border-b-0">
            <h3>
                <button type="button" onClick={onToggle} aria-expanded={open} aria-controls={id} className="group flex w-full items-start gap-4 py-5 text-left sm:py-6">
                    <span className="flex-1 text-base font-semibold text-stone-900 group-hover:text-stone-700 sm:text-lg">{item.question}</span>
                    <span className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border transition-all ${open ? 'rotate-45 border-brand bg-brand text-on-brand' : 'border-stone-300 text-stone-600 group-hover:border-stone-500'}`}>
                        <Plus size={16} />
                    </span>
                </button>
            </h3>
            <div id={id} role="region" hidden={!open} className="pr-12 pb-6">
                <div className="rich-text text-[15px] prose-p:my-2" dangerouslySetInnerHTML={{ __html: item.answer_html }} />
            </div>
        </div>
    );
}

function HelpCard({ contact }) {
    return (
        <div className="rounded-3xl bg-surface p-6 text-on-surface sm:p-8">
            <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-stone-800">
                <MessageCircleQuestion size={22} />
            </span>
            <h2 className="mt-5 text-xl font-bold tracking-tight text-stone-900">Still have questions?</h2>
            <p className="mt-2 text-sm leading-relaxed text-stone-600">Can't find the answer you're looking for? Chat with us — we'll reply as soon as possible.</p>
            <WhatsAppButton className="mt-6 w-full">Ask us on WhatsApp</WhatsAppButton>
            {(contact.phone || contact.email) && (
                <div className="mt-5 space-y-2 border-t border-black/5 pt-5 text-sm">
                    {contact.phone && (
                        <a href={`tel:${contact.phone.replace(/[^\d+]/g, '')}`} className="flex items-center gap-2 text-stone-700 hover:text-stone-950">
                            <Phone size={15} /> {contact.phone}
                        </a>
                    )}
                    {contact.email && (
                        <a href={`mailto:${contact.email}`} className="flex items-center gap-2 break-all text-stone-700 hover:text-stone-950">
                            <Mail size={15} /> {contact.email}
                        </a>
                    )}
                </div>
            )}
        </div>
    );
}

export default function Faq({ page, contact }) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(() => new Set(page.items.length ? [0] : []));

    const plain = (html) => html.replace(/<[^>]+>/g, ' ');
    const results = useMemo(() => {
        const q = query.trim().toLowerCase();
        return page.items.map((item, index) => ({ ...item, index })).filter((item) => !q || item.question.toLowerCase().includes(q) || plain(item.answer_html).toLowerCase().includes(q));
    }, [query, page.items]);

    const searching = query.trim() !== '';

    // Open matching answers when the search changes (they can still be collapsed).
    useEffect(() => {
        if (searching) setOpen(new Set(results.map((r) => r.index)));
    }, [query]); // eslint-disable-line react-hooks/exhaustive-deps

    const allOpen = results.length > 0 && results.every((r) => open.has(r.index));

    const toggle = (index) =>
        setOpen((prev) => {
            const next = new Set(prev);
            next.has(index) ? next.delete(index) : next.add(index);
            return next;
        });

    return (
        <StoreLayout>
            <section className="bg-stone-50/70">
                <div className="container-page py-10 sm:py-14">
                    <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: page.title }]} />
                    <div className="mx-auto mt-6 max-w-2xl text-center">
                        <h1 className="text-3xl font-extrabold tracking-tight text-balance text-stone-950 sm:text-5xl">{page.title}</h1>
                        {page.content_html && <div className="rich-text mx-auto mt-4 text-base text-stone-600 sm:text-lg [&_p]:my-2" dangerouslySetInnerHTML={{ __html: page.content_html }} />}
                        {page.items.length > 3 && (
                            <div className="relative mx-auto mt-8 max-w-lg">
                                <label htmlFor="faq-search" className="sr-only">
                                    Search questions
                                </label>
                                <Search size={18} className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-stone-400" />
                                <input
                                    id="faq-search"
                                    type="search"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Search questions…"
                                    className="h-13 w-full rounded-full border border-stone-200 bg-white pr-12 pl-11 text-[15px] shadow-sm placeholder:text-stone-400 focus:border-stone-400 focus:outline-none [&::-webkit-search-cancel-button]:hidden"
                                />
                                {searching && (
                                    <button type="button" onClick={() => setQuery('')} aria-label="Clear search" className="absolute top-1/2 right-4 -translate-y-1/2 rounded-full p-1 text-stone-400 hover:text-stone-700">
                                        <X size={16} />
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </section>

            <section className="py-10 sm:py-16">
                <div className="container-page grid gap-10 lg:grid-cols-[1fr_340px] lg:gap-14">
                    <div>
                        {page.items.length > 0 && (
                            <div className="mb-2 flex items-center justify-between gap-4">
                                <p className="text-sm text-stone-500" aria-live="polite">
                                    {searching ? `${results.length} ${results.length === 1 ? 'result' : 'results'}` : `${page.items.length} questions`}
                                </p>
                                {results.length > 1 && (
                                    <button type="button" onClick={() => setOpen(allOpen ? new Set() : new Set(results.map((r) => r.index)))} className="text-sm font-semibold text-stone-900 underline-offset-4 hover:underline">
                                        {allOpen ? 'Collapse all' : 'Expand all'}
                                    </button>
                                )}
                            </div>
                        )}

                        {results.length > 0 ? (
                            <div className="border-t border-stone-200">
                                {results.map((item) => (
                                    <FaqItem key={item.index} item={item} open={open.has(item.index)} onToggle={() => toggle(item.index)} />
                                ))}
                            </div>
                        ) : (
                            page.items.length > 0 && (
                                <div className="rounded-3xl border border-dashed border-stone-300 px-6 py-14 text-center">
                                    <p className="font-semibold text-stone-900">No questions match “{query}”</p>
                                    <p className="mt-1 text-sm text-stone-600">Try another word, or ask us directly.</p>
                                    <button type="button" onClick={() => setQuery('')} className="btn-outline mt-5">
                                        Show all questions
                                    </button>
                                </div>
                            )
                        )}
                    </div>

                    <aside className="lg:sticky lg:top-28 lg:self-start">
                        <HelpCard contact={contact} />
                    </aside>
                </div>
            </section>
        </StoreLayout>
    );
}
