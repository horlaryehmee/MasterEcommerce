import { ArrowRight } from 'lucide-react';
import { WhatsAppIcon } from '@/Components/Icons';
import Img from '../Img';
import SmartLink from '../SmartLink';

// Literal class names so Tailwind includes them.
export const DESKTOP_ASPECT = { slim: 'aspect-[3/1]', standard: 'aspect-[12/5]', tall: 'aspect-[2/1]' };
export const MOBILE_ASPECT = { portrait: 'aspect-[4/5]', square: 'aspect-square', landscape: 'aspect-video' };

const SHADE = {
    desktop: {
        light: { left: 'bg-gradient-to-r from-black/65 via-black/30 to-transparent', right: 'bg-gradient-to-l from-black/65 via-black/30 to-transparent', center: 'bg-black/40' },
        dark: { left: 'bg-gradient-to-r from-white/85 via-white/45 to-transparent', right: 'bg-gradient-to-l from-white/85 via-white/45 to-transparent', center: 'bg-white/55' },
    },
    // Phones: a strong fade only over the lower part, so the product at the top stays clear.
    mobile: {
        light: 'bg-gradient-to-t from-black/95 via-black/75 via-45% to-transparent to-80%',
        dark: 'bg-gradient-to-t from-white via-white/85 via-45% to-transparent to-80%',
    },
};

function SlideButton({ button, light, primary, compact }) {
    if (!button) return null;
    const whatsapp = button.link === 'whatsapp';
    let cls;
    if (whatsapp) cls = 'btn-whatsapp';
    else if (primary) cls = light ? 'btn bg-white text-stone-900 hover:bg-stone-100' : 'btn-primary';
    else cls = light ? 'btn border border-white/60 text-white hover:bg-white/10' : 'btn-outline';

    return (
        <SmartLink href={button.link} className={`${cls} ${compact ? 'h-12 min-w-0 flex-1 basis-0 gap-1.5 px-3 text-[13px] [&>svg]:shrink-0' : 'h-11 px-5 sm:h-12 sm:px-6'}`}>
            {whatsapp && <WhatsAppIcon size={compact ? 16 : 18} />}
            <span className={compact ? 'truncate' : undefined}>{button.text}</span>
            {primary && !whatsapp && <ArrowRight size={compact ? 15 : 17} />}
        </SmartLink>
    );
}

/** Eyebrow, heading, description and buttons. */
function SlideText({ slide, light, mobile, centered, alignRight, Heading }) {
    return (
        <div className={light ? 'text-white' : 'text-stone-950'}>
            {slide.eyebrow && <p className={`text-xs font-semibold tracking-[0.18em] uppercase ${light ? 'text-white/75' : 'text-accent'}`}>{slide.eyebrow}</p>}
            {slide.heading && (
                <Heading className={`mt-2 font-extrabold tracking-tight text-balance ${mobile ? 'text-[1.75rem] leading-tight' : 'text-4xl leading-[1.08] lg:text-5xl xl:text-6xl'}`}>{slide.heading}</Heading>
            )}
            {slide.description && <p className={`mt-3 leading-relaxed ${mobile ? 'line-clamp-2 text-[15px]' : 'text-lg'} ${light ? 'text-white/85' : 'text-stone-700'}`}>{slide.description}</p>}
            {(slide.primary || slide.secondary) && (
                <div className={`flex gap-2.5 sm:gap-3 ${mobile ? 'mt-5' : 'mt-7 flex-wrap'} ${centered ? 'justify-center' : alignRight ? 'justify-end' : ''}`}>
                    <SlideButton button={slide.primary} light={light} primary compact={mobile} />
                    <SlideButton button={slide.secondary} light={light} compact={mobile} />
                </div>
            )}
        </div>
    );
}

/**
 * One banner: a full-bleed image, optionally with text and buttons.
 * Used by the banner slider and by the single "Full banner" design.
 *
 * On phones, text can sit below the image (the image stays fully visible)
 * or on top of it with a soft shade.
 */
export default function HeroSlideView({ slide, device, heights, eager = false, roomForText = false, headingLevel = 'h2', mobileText = 'below' }) {
    const mobile = device === 'mobile';
    const image = mobile ? slide.mobile_image || slide.image : slide.image;
    const aspect = mobile ? MOBILE_ASPECT[heights.mobile] : DESKTOP_ASPECT[heights.desktop];
    const Heading = headingLevel;
    const light = slide.theme !== 'dark';
    const position = slide.position || 'left';
    const centered = position === 'center';
    const hasText = !slide.image_only;

    // Phones with text on the image: a tall, screen-capped banner with the image anchored to the top
    // and the text at the bottom (instead of the image's exact shape, which is too short for both).
    const mobileOverlay = mobile && roomForText && mobileText === 'overlay';

    const picture = <Img image={image} eager={eager} sizes="100vw" className={`absolute inset-0 h-full w-full object-cover ${mobileOverlay ? 'object-top' : ''}`} />;

    // Phones, text below: exact image shape, then a matching text panel (fills the slide height in a slider).
    if (mobile && hasText && mobileText === 'below') {
        return (
            <div className={`flex h-full flex-col ${light ? 'bg-stone-950' : 'bg-surface'}`}>
                <div className={`relative w-full overflow-hidden bg-stone-200 ${aspect}`}>{picture}</div>
                <div className={`flex-1 px-5 pt-6 pb-14 ${centered ? 'text-center' : ''}`}>
                    <SlideText slide={slide} light={light} mobile centered={centered} Heading={Heading} />
                </div>
            </div>
        );
    }

    // Text over the image needs a minimum height so it never overflows short banners;
    // image-only slides keep the exact shape so designed banners aren't cropped.
    const size = mobileOverlay ? 'h-[min(80svh,620px)] min-h-[540px]' : `h-full ${aspect} ${roomForText ? (mobile ? 'min-h-[460px]' : 'min-h-[400px]') : ''}`;

    return (
        <div className={`relative w-full overflow-hidden bg-stone-200 ${size}`}>
            {picture}

            {slide.image_only ? (
                slide.link && <SmartLink href={slide.link} className="absolute inset-0" aria-label={slide.image?.alt || 'View offer'} />
            ) : (
                <>
                    <div className={`absolute inset-0 ${mobile ? SHADE.mobile[light ? 'light' : 'dark'] : SHADE.desktop[light ? 'light' : 'dark'][position]}`} aria-hidden />
                    <div className={`absolute inset-0 flex ${mobile ? 'items-end' : 'items-center'}`}>
                        <div className={`w-full ${mobile ? `px-5 pb-16 ${centered ? 'text-center' : ''}` :`container-page flex ${centered ? 'justify-center text-center' : position === 'right' ? 'justify-end text-right' : 'justify-start'}`}`}>
                            <div className={mobile ? '' : 'max-w-xl px-2 lg:px-6'}>
                                <SlideText slide={slide} light={light} mobile={mobile} centered={centered} alignRight={!mobile && position === 'right'} Heading={Heading} />
                            </div>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
