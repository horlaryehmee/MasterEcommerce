import { ArrowRight } from 'lucide-react';
import { WhatsAppIcon } from '@/Components/Icons';
import useIsMobile from '@/hooks/useIsMobile';
import Img from '../Img';
import SmartLink from '../SmartLink';
import HeroSlider from './HeroSlider';
import HeroSlideView from './HeroSlideView';

export function CtaButton({ button }) {
    if (!button) return null;
    const isWhatsApp = button.link === 'whatsapp';
    return (
        <SmartLink href={button.link} className={isWhatsApp ? 'btn-whatsapp h-12 px-7' : 'btn-primary h-12 px-7'}>
            {isWhatsApp && <WhatsAppIcon size={18} />}
            {button.text}
            {!isWhatsApp && <ArrowRight size={17} />}
        </SmartLink>
    );
}

/** "Split" design: text beside an image (stacked and centred on phones). */
function SplitHero({ content, mobile, vh }) {
    const image = mobile ? content.mobile_image || content.image : content.image;
    const hasImage = !!image;
    const center = mobile || !hasImage;

    return (
        <section className="bg-surface text-on-surface">
            <div className={`container-page grid items-center gap-8 py-10 sm:py-16 lg:gap-14 lg:py-20 ${hasImage ? 'md:grid-cols-2' : ''}`}>
                <div className={center ? 'mx-auto max-w-3xl text-center' : ''}>
                    {content.eyebrow && (
                        <p className="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-semibold tracking-wider text-stone-700 uppercase">
                            <span className="h-1.5 w-1.5 rounded-full bg-accent" aria-hidden />
                            {content.eyebrow}
                        </p>
                    )}
                    <h1 className="mt-5 text-4xl leading-[1.08] font-extrabold tracking-tight text-balance text-stone-950 sm:text-5xl lg:text-6xl">{content.heading}</h1>
                    {content.description && <p className={`mt-5 max-w-xl text-base leading-relaxed text-stone-600 sm:text-lg ${center ? 'mx-auto' : ''}`}>{content.description}</p>}
                    {content.primary && (
                        <div className={`mt-8 flex ${center ? 'justify-center' : ''}`}>
                            <CtaButton button={content.primary} />
                        </div>
                    )}
                </div>
                {hasImage && (
                    <div className={`overflow-hidden rounded-[2rem] bg-white/50 ${mobile ? 'order-first' : 'aspect-[4/3]'}`} style={mobile ? { height: `${Math.round(vh * 0.6)}svh`, minHeight: 200 } : undefined}>
                        <Img image={image} eager sizes="(min-width: 768px) 50vw, 100vw" className="h-full w-full object-cover" />
                    </div>
                )}
            </div>
        </section>
    );
}

/** Banner and slider can sit inside the page margins (rounded) or run edge to edge. */
function Frame({ width, children }) {
    if (width === 'full') return <div>{children}</div>;
    return (
        <div className="container-page pt-4 sm:pt-6">
            <div className="overflow-hidden rounded-[1.5rem] sm:rounded-[2rem]">{children}</div>
        </div>
    );
}

/**
 * Homepage hero. The business chooses a design for desktop/tablet and,
 * separately, for phones: split, full banner or banner slider.
 */
export default function Hero({ hero }) {
    const isMobile = useIsMobile();
    if (!hero) return null;

    const device = isMobile ? 'mobile' : 'desktop';
    const type = hero[device];
    const heights = { desktop: hero.slider.desktop_height, mobile: hero.slider.mobile_vh };

    if (type === 'slider' && hero.slides.length) {
        return (
            <Frame width={hero.width}>
                <HeroSlider slides={hero.slides} device={device} settings={hero.slider} />
            </Frame>
        );
    }

    if (!hero.content) return null;

    if (type === 'banner') {
        const c = hero.content;
        return (
            <Frame width={hero.width}>
                <HeroSlideView
                    slide={{ ...c, image_only: false, position: c.align, theme: 'light' }}
                    device={device}
                    heights={heights}
                    eager
                    roomForText
                    headingLevel="h1"
                    mobileText={hero.slider.mobile_text}
                    mobileAlign={hero.slider.mobile_valign}
                    shade={hero.slider.shade}
                />
            </Frame>
        );
    }

    return <SplitHero content={hero.content} mobile={isMobile} vh={hero.slider.mobile_vh} />;
}
