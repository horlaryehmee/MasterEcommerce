import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import HeroSlideView from './HeroSlideView';

const DOTS_JUSTIFY = { left: 'justify-start', center: 'justify-center', right: 'justify-end' };

/**
 * Responsive banner slider: swipe on phones, arrows and keyboard on desktop,
 * dots everywhere. Autoplay pauses on hover, focus, or when the visitor prefers
 * reduced motion, and there is a visible pause button (accessibility).
 */
export default function HeroSlider({ slides, device, settings }) {
    const count = slides.length;
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const [hovering, setHovering] = useState(false);
    const [seen, setSeen] = useState(1); // slides up to here (plus the next one) load eagerly
    const [reducedMotion, setReducedMotion] = useState(false);
    const touch = useRef(null);
    const mobile = device === 'mobile';

    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        setReducedMotion(mq.matches);
    }, []);

    const go = useCallback(
        (i) => {
            const next = (i + count) % count;
            setIndex(next);
            setSeen((s) => Math.max(s, next + 1));
        },
        [count],
    );

    const autoplay = settings.autoplay && count > 1 && !reducedMotion;
    useEffect(() => {
        if (!autoplay || paused || hovering) return;
        const timer = setTimeout(() => go(index + 1), settings.interval * 1000);
        return () => clearTimeout(timer);
    }, [autoplay, paused, hovering, index, go, settings.interval]);

    if (count === 0) return null;
    const roomForText = slides.some((s) => !s.image_only);
    // Dots follow the current slide's text colour (dark text = light image). Text below the
    // image on phones sits on a dark or light panel matching the same theme.
    const dark = slides[index]?.theme === 'dark' && !slides[index]?.image_only;

    return (
        <section
            className="group/slider relative"
            aria-roledescription="carousel"
            aria-label="Featured offers"
            onMouseEnter={() => setHovering(true)}
            onMouseLeave={() => setHovering(false)}
            onFocus={() => setHovering(true)}
            onBlur={(e) => !e.currentTarget.contains(e.relatedTarget) && setHovering(false)}
            onKeyDown={(e) => {
                if (e.key === 'ArrowRight') go(index + 1);
                if (e.key === 'ArrowLeft') go(index - 1);
            }}
            onTouchStart={(e) => (touch.current = e.touches[0].clientX)}
            onTouchEnd={(e) => {
                if (touch.current === null) return;
                const dx = e.changedTouches[0].clientX - touch.current;
                if (Math.abs(dx) > 40) go(index + (dx < 0 ? 1 : -1));
                touch.current = null;
            }}
        >
            <div className="overflow-hidden">
                <div className={`flex ${reducedMotion ? '' : 'transition-transform duration-700 ease-[cubic-bezier(.22,.61,.36,1)]'}`} style={{ transform: `translateX(-${index * 100}%)` }}>
                    {slides.map((slide, i) => (
                        <div
                            key={slide.id}
                            className="w-full shrink-0 self-stretch"
                            role="group"
                            aria-roledescription="slide"
                            aria-label={`${i + 1} of ${count}`}
                            aria-hidden={i !== index}
                            inert={i !== index ? true : undefined}
                        >
                            <HeroSlideView slide={slide} device={device} heights={{ desktop: settings.desktop_height, mobile: settings.mobile_vh }} eager={i <= seen} roomForText={roomForText} headingLevel={i === 0 ? 'h1' : 'h2'} mobileText={settings.mobile_text} mobileAlign={settings.mobile_valign} shade={settings.shade} />
                        </div>
                    ))}
                </div>
            </div>

            {count > 1 && (
                <>
                    {!mobile && (
                        <>
                            <button
                                type="button"
                                onClick={() => go(index - 1)}
                                aria-label="Previous slide"
                                className="absolute top-1/2 left-4 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-stone-900 opacity-0 shadow-md backdrop-blur transition-opacity group-hover/slider:opacity-100 focus-visible:opacity-100 lg:left-6"
                            >
                                <ChevronLeft size={22} />
                            </button>
                            <button
                                type="button"
                                onClick={() => go(index + 1)}
                                aria-label="Next slide"
                                className="absolute top-1/2 right-4 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-stone-900 opacity-0 shadow-md backdrop-blur transition-opacity group-hover/slider:opacity-100 focus-visible:opacity-100 lg:right-6"
                            >
                                <ChevronRight size={22} />
                            </button>
                        </>
                    )}

                    {/* Dots sit left, centre or right (lined up with the slide text); dark dots on light slides. */}
                    <div className={`pointer-events-none absolute inset-x-0 bottom-4 sm:bottom-6 ${mobile ? 'px-5' : 'container-page'}`}>
                        <div className={`flex items-center gap-3 ${mobile ? 'justify-center' : DOTS_JUSTIFY[settings.dots] || DOTS_JUSTIFY.center} ${!mobile && settings.dots !== 'center' ? 'px-2 lg:px-6' : ''}`}>
                            <div className="pointer-events-auto flex items-center gap-1.5">
                                {slides.map((s, i) => (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onClick={() => go(i)}
                                        aria-label={`Go to slide ${i + 1}`}
                                        aria-current={i === index ? 'true' : undefined}
                                        className="group/dot flex h-6 items-center px-0.5"
                                    >
                                        <span className={`block h-2 rounded-full shadow-sm transition-all ${i === index ? `w-6 ${dark ? 'bg-stone-900' : 'bg-white'}` : `w-2 ${dark ? 'bg-stone-900/30 group-hover/dot:bg-stone-900/60' : 'bg-white/55 group-hover/dot:bg-white/80'}`}`} />
                                    </button>
                                ))}
                            </div>
                            {autoplay && (
                                <button
                                    type="button"
                                    onClick={() => setPaused((p) => !p)}
                                    aria-label={paused ? 'Play slideshow' : 'Pause slideshow'}
                                    className={`pointer-events-auto flex h-7 w-7 items-center justify-center rounded-full transition-colors ${dark ? 'text-stone-900 hover:bg-stone-900/10' : 'text-white hover:bg-white/15'}`}
                                >
                                    {paused ? <Play size={13} /> : <Pause size={13} />}
                                </button>
                            )}
                        </div>
                    </div>
                </>
            )}
        </section>
    );
}
