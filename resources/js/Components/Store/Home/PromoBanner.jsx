import { ArrowRight } from 'lucide-react';
import Img from '../Img';
import SmartLink from '../SmartLink';

export default function PromoBanner({ promo }) {
    if (!promo) return null;
    return (
        <section className="py-6 sm:py-10">
            <div className="container-page">
                <div className={`grid overflow-hidden rounded-[2rem] bg-brand text-on-brand ${promo.image ? 'lg:grid-cols-2' : ''}`}>
                    <div className="flex flex-col justify-center p-8 sm:p-12 lg:p-16">
                        {promo.eyebrow && <p className="text-xs font-semibold tracking-[0.18em] uppercase opacity-70">{promo.eyebrow}</p>}
                        <h2 className="mt-3 text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{promo.heading}</h2>
                        {promo.description && <p className="mt-4 max-w-md text-base leading-relaxed opacity-80">{promo.description}</p>}
                        {promo.button && (
                            <div className="mt-8">
                                <SmartLink href={promo.button.link} className="btn h-12 bg-on-brand px-6 text-brand hover:opacity-90">
                                    {promo.button.text} <ArrowRight size={17} />
                                </SmartLink>
                            </div>
                        )}
                    </div>
                    {promo.image && (
                        <div className="aspect-[16/10] lg:aspect-auto">
                            <Img image={promo.image} sizes="(min-width: 1024px) 50vw, 100vw" className="h-full w-full object-cover" />
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
