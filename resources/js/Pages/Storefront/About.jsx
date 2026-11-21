import { Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { ArrowUpRight, BadgeCheck, Clock, Mail, MapPin, Phone } from 'lucide-react';
import { WHY_ICONS } from '@/Components/Store/Home/WhyChooseUs';
import Img from '@/Components/Store/Img';
import WhatsAppButton from '@/Components/Store/WhatsAppButton';
import StoreLayout from '@/Layouts/StoreLayout';
import './about.css';

function splitAboutContent(html) {
    const story = document.createElement('template');
    story.innerHTML = html || '';
    const sections = [];

    // Move the named Markdown sections intact, including any lists and subheadings.
    for (const heading of story.content.querySelectorAll('h2')) {
        if (heading.parentNode !== story.content || !/^(what we offer|our promise)$/i.test(heading.textContent.trim())) continue;
        const section = document.createElement('template');
        let node = heading;
        do {
            const next = node.nextSibling;
            section.content.append(node);
            node = next;
        } while (node && !node.matches?.('h1, h2'));
        sections.push(section.innerHTML);
    }

    return { storyHtml: story.innerHTML.trim(), sections };
}

export default function About({ page, values, contact, categories = [], stats = [], quote }) {
    const { brand } = usePage().props;
    const { storyHtml, sections } = useMemo(() => splitAboutContent(page.content_html), [page.content_html]);
    const collection = categories.find(category => category.image?.src && category.url);
    const details = [
        ['Email', Mail, contact.email, contact.email && `mailto:${contact.email}`],
        ['Phone', Phone, contact.phone, contact.phone && `tel:${contact.phone.replace(/[^\d+]/g, '')}`],
        ['Address', MapPin, contact.address],
        ['Opening hours', Clock, contact.opening_hours],
    ].filter(([, , value]) => value);

    const founderNote = quote && (
        <blockquote className="profile-note mt-5 rounded-r-lg border-l-2 border-brand px-5 py-4">
            <p className="leading-relaxed">&ldquo;{quote.text}&rdquo;</p>
            {(quote.name || quote.role) && <footer className="mt-2 text-sm text-stone-600">{[quote.name, quote.role].filter(Boolean).join(', ')}</footer>}
        </blockquote>
    );

    return (
        <StoreLayout>
            <article className="about-profile">
                <header className={'profile-cover' + (page.image?.src ? ' has-image' : '')}>
                    {page.image?.src && <Img image={page.image} alt="" eager sizes="(min-width: 768px) 70vw, 100vw" className="profile-cover-image" />}
                    <div className="profile-width profile-cover-copy">
                        {brand.name && page.title !== brand.name && <p className="mb-3 text-sm font-medium">{page.title}</p>}
                        <h1 className="max-w-[18ch] text-[clamp(2.7rem,5vw,5rem)] leading-[1.04] font-semibold tracking-[-0.045em]">{brand.name || page.title}</h1>
                        {page.intro && <p className="mt-4 max-w-[41ch] text-base leading-relaxed sm:text-lg">{page.intro}</p>}
                    </div>
                </header>

                <div className="profile-width profile-body">
                    {stats.length > 0 && (
                        <dl aria-label="Business figures" className="profile-facts">
                            {stats.map((stat, index) => (
                                <div key={index} className="flex flex-col rounded-xl border border-stone-200 px-4 py-3 sm:px-5">
                                    <dt className="mt-1 text-sm text-stone-600">{stat.label}</dt>
                                    <dd className="order-first text-2xl font-semibold tracking-tight">{stat.value}</dd>
                                </div>
                            ))}
                        </dl>
                    )}

                    {(storyHtml || (!sections.length && quote)) && (
                        <section id="our-story" aria-label="Our story" className="profile-story-layout">
                            <div className="min-w-0 max-w-[66ch]">
                                {page.headline && <h2 className="profile-heading mb-4">{page.headline}</h2>}
                                {storyHtml && <div className="profile-story rich-text" dangerouslySetInnerHTML={{ __html: storyHtml }} />}
                                {sections.length === 0 && founderNote}
                            </div>
                            {collection && (
                                <Link href={collection.url} className="profile-photo group block overflow-hidden rounded-xl border border-stone-200">
                                    <Img image={collection.image} alt="" sizes="(min-width: 960px) 28vw, 90vw" className="aspect-[2.5] w-full object-cover md:aspect-square" />
                                    <span className="flex items-center justify-between gap-3 px-4 py-3 text-sm font-semibold sm:px-5">
                                        <span className="group-hover:underline">Explore {collection.name}</span>
                                        <ArrowUpRight size={17} aria-hidden />
                                    </span>
                                </Link>
                            )}
                        </section>
                    )}

                    {sections.length > 0 && (
                        <section id="offer-and-promise" aria-label="What we offer and our promise" className="border-y border-stone-200 py-6 sm:py-8">
                            <div className={`grid gap-5 md:gap-12 ${sections.length > 1 ? 'md:grid-cols-2' : ''}`}>
                                {sections.map((html, index) => (
                                    <div key={index} className="rich-text min-w-0 [&>h2:first-child]:mt-0 [&>h2:first-child]:mb-3 [&>:last-child]:mb-0"
                                        dangerouslySetInnerHTML={{ __html: html }} />
                                ))}
                            </div>
                            {founderNote}
                        </section>
                    )}

                    {values && (
                        <section id="values" aria-labelledby="values-heading">
                            <div className="flex flex-wrap items-end justify-between gap-x-8 gap-y-2">
                                <h2 id="values-heading" className="profile-heading">{values.heading}</h2>
                                {values.subheading && <p className="max-w-md text-sm leading-relaxed text-stone-600">{values.subheading}</p>}
                            </div>
                            <dl className="profile-values">
                                {values.items.map((item, index) => {
                                    const Icon = WHY_ICONS[item.icon] || BadgeCheck;
                                    return (
                                        <div key={index} className="rounded-xl border border-stone-200 p-4 sm:p-5">
                                            <dt className="flex items-center gap-3 font-semibold md:block">
                                                <Icon size={21} className="shrink-0 md:mb-4" strokeWidth={1.6} aria-hidden />{item.title}
                                            </dt>
                                            {item.text && <dd className="mt-2 text-sm leading-relaxed text-stone-600">{item.text}</dd>}
                                        </div>
                                    );
                                })}
                            </dl>
                        </section>
                    )}

                    <section id="contact" aria-labelledby="contact-heading" className="profile-contact">
                        <div>
                            <h2 id="contact-heading" className="profile-heading">Get in touch</h2>
                            <div className="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2">
                                <WhatsAppButton />
                                <Link href="/products" className="inline-flex min-h-11 items-center gap-1 text-sm font-semibold hover:underline">
                                    Browse products <ArrowUpRight size={15} aria-hidden />
                                </Link>
                            </div>
                        </div>
                        {details.length > 0 && (
                            <dl className="grid min-w-0 gap-x-6 gap-y-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-2">
                                {details.map(([label, Icon, value, href]) => (
                                    <div key={label} className="min-w-0">
                                        <dt className="flex items-center gap-2 text-sm font-semibold"><Icon size={16} aria-hidden />{label}</dt>
                                        <dd className="mt-1 whitespace-pre-line text-sm leading-relaxed text-stone-700">
                                            {href ? <a className="underline decoration-stone-300 underline-offset-4 hover:decoration-current" href={href}>{value}</a> : value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </section>
                </div>
            </article>
        </StoreLayout>
    );
}
