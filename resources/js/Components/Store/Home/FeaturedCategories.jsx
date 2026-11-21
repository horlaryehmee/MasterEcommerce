import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import useTheme from '@/hooks/useTheme';
import CategoryCard from '../CategoryCard';
import Img from '../Img';
import Reveal from '../Reveal';
import SectionHeading from '../SectionHeading';

const sizes = '(min-width: 1024px) 25vw, (min-width: 768px) 33vw, 45vw';

/** Essential: square image cards (a swipeable row on phones). */
function Cards({ items }) {
    return (
        <>
            <div className="no-scrollbar flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto overscroll-x-contain px-4 pb-2 sm:hidden">
                {items.map((c, i) => (
                    <Reveal key={c.id} delay={Math.min(i, 3) * 60} className="w-[40%] shrink-0 snap-start">
                        <CategoryCard category={c} />
                    </Reveal>
                ))}
            </div>
            <div className="container-page hidden gap-6 sm:grid sm:grid-cols-3 lg:grid-cols-4">
                {items.map((c, i) => (
                    <Reveal key={c.id} delay={(i % 4) * 70}>
                        <CategoryCard category={c} />
                    </Reveal>
                ))}
            </div>
        </>
    );
}

/** Atelier: tall editorial tiles with the name over the photo; the first one is larger on desktop. */
function Editorial({ items }) {
    return (
        <div className="container-page grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-4">
            {items.slice(0, 7).map((c, i) => (
                <Reveal key={c.id} delay={(i % 4) * 70} className={i === 0 && items.length >= 3 ? 'col-span-2 row-span-2 lg:col-span-2' : ''}>
                    <Link href={c.url} prefetch="hover" className="group relative block h-full overflow-hidden bg-surface">
                        <div className={i === 0 && items.length >= 3 ? 'aspect-[4/5] h-full lg:aspect-auto' : 'aspect-[3/4]'}>
                            <Img image={c.image} sizes={sizes} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]" />
                        </div>
                        <div className="absolute inset-0 bg-gradient-to-t from-black/55 via-black/5 to-transparent" aria-hidden />
                        <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 p-3 text-white sm:p-5">
                            <span>
                                <span className="font-heading block text-lg leading-tight sm:text-2xl">{c.name}</span>
                                <span className="mt-1 block text-[11px] tracking-[0.18em] text-white/80 uppercase underline-offset-4 group-hover:underline">Shop now</span>
                            </span>
                        </div>
                    </Link>
                </Reveal>
            ))}
        </div>
    );
}

/** Glow: round "story" bubbles in a swipeable row. */
function Circles({ items }) {
    return (
        <div className="no-scrollbar flex snap-x gap-4 overflow-x-auto overscroll-x-contain px-4 pb-2 sm:justify-center sm:gap-8 sm:px-6">
            {items.map((c, i) => (
                <Reveal key={c.id} delay={Math.min(i, 5) * 50} className="shrink-0 snap-start">
                    <Link href={c.url} prefetch="hover" className="group flex w-24 flex-col items-center text-center sm:w-32">
                        <span className="rounded-full bg-gradient-to-tr from-accent via-brand/60 to-surface p-[3px] transition-transform duration-300 group-hover:scale-[1.04]">
                            <span className="block h-[5.5rem] w-[5.5rem] overflow-hidden rounded-full border-[3px] border-white bg-surface sm:h-[7.25rem] sm:w-[7.25rem]">
                                <Img image={c.image} sizes="120px" className="h-full w-full object-cover" />
                            </span>
                        </span>
                        <span className="mt-3 line-clamp-2 text-[13px] font-medium text-stone-800 sm:text-sm">{c.name}</span>
                    </Link>
                </Reveal>
            ))}
        </div>
    );
}

/** Volt: compact tiles, many per row, like a marketplace's category bar. */
function Tiles({ items }) {
    return (
        <div className="container-page grid grid-cols-3 gap-2.5 sm:grid-cols-4 sm:gap-4 lg:grid-cols-6">
            {items.map((c, i) => (
                <Reveal key={c.id} delay={(i % 6) * 40}>
                    <Link href={c.url} prefetch="hover" className="group flex h-full flex-col items-center rounded-xl border border-stone-200 bg-white p-3 text-center transition hover:-translate-y-0.5 hover:border-accent hover:shadow-md sm:p-4">
                        <span className="aspect-square w-full overflow-hidden rounded-lg bg-surface">
                            <Img image={c.image} sizes="160px" className="h-full w-full object-contain p-1 transition-transform duration-300 group-hover:scale-105" />
                        </span>
                        <span className="mt-2.5 line-clamp-2 text-xs font-semibold text-stone-900 sm:text-sm">{c.name}</span>
                        {c.products_count != null && <span className="mt-0.5 text-[11px] text-stone-500">{c.products_count} items</span>}
                    </Link>
                </Reveal>
            ))}
        </div>
    );
}

/** Bloom: arch-topped photos with centred names. */
function Arches({ items }) {
    return (
        <div className="no-scrollbar flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-2 sm:container-page sm:grid sm:grid-cols-3 sm:gap-6 sm:overflow-visible lg:grid-cols-4">
            {items.map((c, i) => (
                <Reveal key={c.id} delay={(i % 4) * 70} className="w-[44%] shrink-0 snap-start sm:w-auto">
                    <Link href={c.url} prefetch="hover" className="group block text-center">
                        <span className="block aspect-[4/5] overflow-hidden rounded-t-full bg-surface">
                            <Img image={c.image} sizes={sizes} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.05]" />
                        </span>
                        <span className="font-heading mt-3 block text-lg text-stone-900 sm:text-xl">{c.name}</span>
                        <span className="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-accent">
                            Shop now <ArrowUpRight size={13} />
                        </span>
                    </Link>
                </Reveal>
            ))}
        </div>
    );
}

const DESIGNS = { cards: Cards, editorial: Editorial, circles: Circles, tiles: Tiles, arches: Arches };

export default function FeaturedCategories({ section }) {
    const theme = useTheme();
    if (!section) return null;
    const Design = DESIGNS[theme.categories] || Cards;

    return (
        <section className={theme.categories === 'circles' ? 'py-10 sm:py-16' : 'py-12 sm:py-20'}>
            <div className="container-page">
                <SectionHeading title={section.heading} subtitle={section.subheading} link="/categories" linkLabel="All categories" />
            </div>
            <Design items={section.items} />
        </section>
    );
}
