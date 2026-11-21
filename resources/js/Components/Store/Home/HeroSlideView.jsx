import { ArrowRight } from 'lucide-react';
import { WhatsAppIcon } from '@/Components/Icons';
import Img from '../Img';
import SmartLink from '../SmartLink';

// Literal class names so Tailwind includes them.
export const DESKTOP_ASPECT = { slim: 'aspect-[3/1]', standard: 'aspect-[12/5]', tall: 'aspect-[2/1]' };

// Shade behind the text, by strength (Website Content → Hero → Image shade). "none" draws nothing.
const SHADE = {
    strong: {
        desktop: {
            light: { left: 'bg-gradient-to-r from-black/65 via-black/30 to-transparent', right: 'bg-gradient-to-l from-black/65 via-black/30 to-transparent', center: 'bg-black/40' },
            dark: { left: 'bg-gradient-to-r from-white/85 via-white/45 to-transparent', right: 'bg-gradient-to-l from-white/85 via-white/45 to-transparent', center: 'bg-white/55' },
        },
        // Phones: a strong fade only over the lower part, so the product at the top stays clear.
        // Phones: text at the bottom gets a fade over the lower part (the top of the photo stays clear);
        // text in the middle gets an even shade.
        mobile: {
            bottom: { light: 'bg-gradient-to-t from-black/95 via-black/70 via-45% to-transparent to-80%', dark: 'bg-gradient-to-t from-white via-white/85 via-45% to-transparent to-80%' },
            middle: { light: 'bg-gradient-to-t from-black/80 via-black/50 to-black/30', dark: 'bg-gradient-to-t from-white/90 via-white/65 to-white/40' },
        },
    },
    soft: {
        desktop: {
            light: { left: 'bg-gradient-to-r from-black/40 via-black/15 to-transparent', right: 'bg-gradient-to-l from-black/40 via-black/15 to-transparent', center: 'bg-black/25' },
            dark: { left: 'bg-gradient-to-r from-white/60 via-white/25 to-transparent', right: 'bg-gradient-to-l from-white/60 via-white/25 to-transparent', center: 'bg-white/35' },
        },
        mobile: {
            bottom: { light: 'bg-gradient-to-t from-black/70 via-black/35 via-40% to-transparent to-75%', dark: 'bg-gradient-to-t from-white/85 via-white/45 via-40% to-transparent to-75%' },
            middle: { light: 'bg-gradient-to-t from-black/60 via-black/35 to-black/15', dark: 'bg-gradient-to-t from-white/75 via-white/45 to-white/20' },
        },
    },
};

/** The slide's single call-to-action button. */
function SlideButton({ button, light, mobile }) {
    if (!button) return null;
    const whatsapp = button.link === 'whatsapp';
    const cls = whatsapp ? 'btn-whatsapp' : light ? 'btn bg-white text-stone-900 hover:bg-stone-100' : 'btn-primary';

    return (
        <SmartLink href={button.link} className={`${cls} ${mobile ? 'h-12 max-w-full px-7 text-[15px]' : 'h-11 px-5 sm:h-12 sm:px-6'}`}>
            {whatsapp && <WhatsAppIcon size={18} />}
            <span className="truncate">{button.text}</span>
            {!whatsapp && <ArrowRight size={17} className="shrink-0" />}
        </SmartLink>
    );
}

/** Eyebrow, heading, description and the button. Always centred on phones. */
function SlideText({ slide, light, mobile, centered, alignRight, Heading }) {
    const center = mobile || centered;
    return (
        <div className={`${light ? 'text-white' : 'text-stone-950'} ${center ? 'text-center' : alignRight ? 'text-right' : ''}`}>
            {slide.eyebrow && <p className={`text-xs font-semibold tracking-[0.18em] uppercase ${light ? 'text-white/75' : 'text-accent'}`}>{slide.eyebrow}</p>}
            {slide.heading && (
                <Heading className={`mt-2 font-extrabold tracking-tight text-balance ${mobile ? 'text-[1.9rem] leading-[1.1]' : 'text-4xl leading-[1.08] lg:text-5xl xl:text-6xl'}`}>{slide.heading}</Heading>
            )}
            {slide.description && <p className={`mt-3 leading-relaxed ${mobile ? 'mx-auto line-clamp-3 max-w-sm text-[15px]' : 'text-lg'} ${centered && !mobile ? 'mx-auto max-w-xl' : ''} ${light ? 'text-white/85' : 'text-stone-700'}`}>{slide.description}</p>}
            {slide.primary && (
                <div className={`flex ${mobile ? 'mt-6' : 'mt-7'} ${center ? 'justify-center' : alignRight ? 'justify-end' : ''}`}>
                    <SlideButton button={slide.primary} light={light} mobile={mobile} />
                </div>
            )}
        </div>
    );
}

/**
 * One banner: a full-bleed image, optionally with text and a button.
 * Used by the banner slider and by the single "Full banner" design.
 *
 * On phones the text is centred left to right and sits at the bottom or in the
 * middle of the image (or below it), and the height is a share of the screen
 * (Website Content → Hero).
 */
export default function HeroSlideView({ slide, device, heights, eager = false, roomForText = false, headingLevel = 'h2', mobileText = 'below', mobileAlign = 'bottom', shade = 'strong' }) {
    const mobile = device === 'mobile';
    const image = mobile ? slide.mobile_image || slide.image : slide.image;
    const Heading = headingLevel;
    const light = slide.theme !== 'dark';
    const position = slide.position || 'left';
    const centered = position === 'center';
    const hasText = !slide.image_only;
    const vh = Math.max(40, Math.min(100, Number(heights.mobile) || 75));
    const valign = mobileAlign === 'middle' ? 'middle' : 'bottom';

    const picture = <Img image={image} eager={eager} sizes="100vw" className="absolute inset-0 h-full w-full object-cover" />;

    // Phones, text below: the image at the chosen height, then a matching text panel (fills the slide height in a slider).
    if (mobile && hasText && mobileText === 'below') {
        return (
            <div className={`flex h-full flex-col ${light ? 'bg-stone-950' : 'bg-surface'}`}>
                <div className="relative w-full overflow-hidden bg-stone-200" style={{ height: `${vh}svh`, minHeight: 220 }}>
                    {picture}
                </div>
                <div className="flex flex-1 flex-col items-center justify-center px-6 pt-7 pb-14">
                    <SlideText slide={slide} light={light} mobile Heading={Heading} />
                </div>
            </div>
        );
    }

    // Phones: the chosen share of the screen (text on the image needs at least room for itself).
    // Desktop: the chosen shape, with a minimum height when there is text.
    const sizeClass = mobile ? '' : `h-full ${DESKTOP_ASPECT[heights.desktop]} ${roomForText ? 'min-h-[400px]' : ''}`;
    const sizeStyle = mobile ? { height: `${vh}svh`, minHeight: hasText ? 380 : 200 } : undefined;

    return (
        <div className={`relative w-full overflow-hidden bg-stone-200 ${sizeClass}`} style={sizeStyle}>
            {picture}

            {slide.image_only ? (
                slide.link && <SmartLink href={slide.link} className="absolute inset-0" aria-label={slide.image?.alt || 'View offer'} />
            ) : (
                <>
                    {SHADE[shade] && <div className={`absolute inset-0 ${mobile ? SHADE[shade].mobile[valign][light ? 'light' : 'dark'] : SHADE[shade].desktop[light ? 'light' : 'dark'][position]}`} aria-hidden />}
                    {mobile ? (
                        <div className={`absolute inset-0 flex justify-center px-6 ${valign === 'middle' ? 'items-center pt-6 pb-14' : 'items-end pb-16'}`}>
                            <SlideText slide={slide} light={light} mobile Heading={Heading} />
                        </div>
                    ) : (
                        <div className="absolute inset-0 flex items-center">
                            <div className={`container-page flex w-full ${centered ? 'justify-center' : position === 'right' ? 'justify-end' : 'justify-start'}`}>
                                <div className="max-w-xl px-2 lg:px-6">
                                    <SlideText slide={slide} light={light} centered={centered} alignRight={position === 'right'} Heading={Heading} />
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
