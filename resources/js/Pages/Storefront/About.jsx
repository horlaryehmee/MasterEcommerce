import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import Img from '@/Components/Store/Img';
import StoreLayout from '@/Layouts/StoreLayout';
import './about.css';

const WIDTH = 'mx-auto max-w-7xl px-5 sm:px-6 md:px-12';
const PILL = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold transition-colors';

export default function About({ page, values, collection, stats = [] }) {
    const { brand } = usePage().props;
    const heading = page.headline || brand.name || page.title;
    const hasStory = Boolean(page.content_html?.trim());

    return (
        <StoreLayout>
            <article className="about-page text-stone-900">
                {/* Centred opening: label, statement, introduction, two buttons, then one wide photo. */}
                <header className={`${WIDTH} pt-12 pb-12 md:pt-24`}>
                    <div className="mx-auto mb-10 flex max-w-3xl flex-col items-center gap-5 text-center md:mb-16">
                        <span className="text-xs font-semibold tracking-[0.14em] text-stone-500 uppercase">{page.title}</span>
                        <h1 className="text-[2rem] leading-[1.18] font-semibold tracking-[-0.02em] text-balance md:text-[3.5rem] md:leading-[1.1] md:tracking-[-0.035em]">
                            {heading}
                        </h1>
                        {page.intro && <p className="max-w-prose text-lg leading-relaxed text-pretty text-stone-600">{page.intro}</p>}
                        <div className="flex flex-wrap items-center justify-center gap-3 pt-2">
                            <Link href="/products" className={`${PILL} bg-brand text-on-brand hover:opacity-90`}>Shop now</Link>
                            {hasStory && <a href="#our-story" className={`${PILL} border border-stone-300 hover:bg-stone-50`}>Read our story</a>}
                        </div>
                    </div>
                    {page.image?.src && (
                        <figure className="overflow-hidden rounded-[10px] bg-stone-100">
                            <Img image={page.image} alt={page.image.alt || ''} eager sizes="(min-width: 1280px) 1184px, 100vw" className="aspect-[4/3] w-full object-cover sm:aspect-[16/9]" />
                        </figure>
                    )}
                </header>

                {/* Story beside a photo, with the headline numbers underneath. */}
                {(hasStory || stats.length > 0) && (
                    <section id="our-story" aria-label="Our story" className={`${WIDTH} scroll-mt-24 py-12 md:py-24`}>
                        <div className={`grid grid-cols-1 items-center gap-10 md:gap-16 ${collection ? 'md:grid-cols-12' : ''}`}>
                            {collection && (
                                <Link href={collection.url} className="group block md:col-span-6" aria-label={`Explore ${collection.name}`}>
                                    <figure className="overflow-hidden rounded-[10px] bg-stone-100">
                                        <Img image={collection.image} alt="" sizes="(min-width: 768px) 45vw, 100vw"
                                            className="aspect-[4/3] w-full object-cover transition-transform duration-500 group-hover:scale-[1.02]" />
                                    </figure>
                                </Link>
                            )}
                            <div className={`flex min-w-0 flex-col gap-6 ${collection ? 'md:col-span-6' : 'mx-auto max-w-3xl'}`}>
                                {hasStory && <div className="about-story max-w-prose" dangerouslySetInnerHTML={{ __html: page.content_html }} />}
                                {stats.length > 0 && (
                                    <dl className={`grid gap-6 border-t border-stone-200 pt-6 ${stats.length === 1 ? 'grid-cols-1' : stats.length === 2 ? 'grid-cols-2' : 'grid-cols-3'}`}>
                                        {stats.map((stat, index) => (
                                            <div key={index} className="flex min-w-0 flex-col gap-1">
                                                <dt className="text-xs font-semibold tracking-wide text-stone-500">{stat.label}</dt>
                                                <dd className="font-heading order-first text-2xl font-semibold tracking-tight tabular-nums">{stat.value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {/* Value pillars on a soft band. */}
                {values && (
                    <section aria-labelledby="values-heading" className="border-y border-stone-200 bg-stone-50">
                        <div className={`${WIDTH} flex flex-col gap-10 py-16 md:py-24`}>
                            <div className="flex max-w-xl flex-col gap-2">
                                <h2 id="values-heading" className="text-[1.65rem] leading-tight font-semibold tracking-[-0.01em] text-balance md:text-[2.25rem]">{values.heading}</h2>
                                {values.subheading && <p className="leading-relaxed text-stone-600">{values.subheading}</p>}
                            </div>
                            <div className={`grid grid-cols-1 items-stretch gap-6 sm:grid-cols-2 ${values.items.length % 4 === 0 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}`}>
                                {values.items.map((item, index) => (
                                    <div key={index} className="flex flex-col gap-3 rounded-[10px] border border-stone-200 bg-white p-6 md:p-8">
                                        <h3 className="text-lg font-semibold">{item.title}</h3>
                                        {item.text && <p className="leading-relaxed text-pretty text-stone-600">{item.text}</p>}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                {/* Closing call to action in the brand colour. */}
                <section aria-labelledby="explore-heading" className={`${WIDTH} py-16 md:py-20`}>
                    <div className="flex flex-col items-start justify-between gap-8 rounded-[10px] bg-brand px-6 py-12 text-on-brand sm:px-8 md:flex-row md:items-center md:px-12 md:py-16">
                        <div className="flex max-w-xl flex-col gap-2">
                            <h2 id="explore-heading" className="text-[1.65rem] leading-tight font-semibold tracking-[-0.01em] text-balance md:text-[2.25rem]">Ready to explore the store?</h2>
                            <p className="text-lg leading-relaxed text-pretty opacity-75">See everything we have, with clear photos and honest prices.</p>
                        </div>
                        <Link href="/products" className={`${PILL} shrink-0 bg-white px-7 text-stone-900 hover:bg-stone-100`}>
                            Browse products <ArrowRight size={17} aria-hidden />
                        </Link>
                    </div>
                </section>
            </article>
        </StoreLayout>
    );
}
