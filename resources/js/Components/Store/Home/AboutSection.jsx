import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import Img from '../Img';

export default function AboutSection({ about }) {
    const { store } = usePage().props;
    if (!about) return null;
    const hasAboutPage = store?.pages?.some((p) => p.slug === 'about');

    return (
        <section className="py-14 sm:py-20">
            <div className={`container-page grid items-center gap-10 lg:gap-16 ${about.image ? 'lg:grid-cols-2' : 'max-w-3xl'}`}>
                {about.image && (
                    <div className="aspect-[6/5] overflow-hidden rounded-[2rem] bg-surface">
                        <Img image={about.image} sizes="(min-width: 1024px) 50vw, 100vw" className="h-full w-full object-cover" />
                    </div>
                )}
                <div>
                    <p className="text-xs font-semibold tracking-[0.18em] text-accent uppercase">About us</p>
                    <h2 className="mt-3 text-3xl font-bold tracking-tight text-balance text-stone-900 sm:text-4xl">{about.heading}</h2>
                    <div className="rich-text mt-5 text-[15px] sm:text-base" dangerouslySetInnerHTML={{ __html: about.body_html }} />
                    {hasAboutPage && (
                        <Link href="/about" className="group mt-7 inline-flex items-center gap-1.5 text-sm font-semibold text-stone-900">
                            Learn more about us <ArrowRight size={16} className="transition-transform group-hover:translate-x-0.5" />
                        </Link>
                    )}
                </div>
            </div>
        </section>
    );
}
