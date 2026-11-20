import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import HeroSlideView from './HeroSlideView';

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
                            <HeroSlideView slide={slide} device={device} heights={{ desktop: settings.desktop_height, mobile: settings.mobile_height }} eager={i <= seen} roomForText={roomForText} headingLevel={i === 0 ? 'h1' : 'h2'} mobileText={settings.mobile_text} />
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

                    <div className="absolute inset-x-0 bottom-4 flex items-center justify-center gap-3 sm:bottom-5">
                        <div className="flex items-center gap-1.5 rounded-full bg-black/25 px-2.5 py-1.5 backdrop-blur-sm">
                            {slides.map((s, i) => (
                                <button
                                    key={s.id}
                                    type="button"
                                    onClick={() => go(i)}
                                    aria-label={`Go to slide ${i + 1}`}
                                    aria-current={i === index ? 'true' : undefined}
                                    className={`h-2 rounded-full transition-all ${i === index ? 'w-6 bg-white' : 'w-2 bg-white/55 hover:bg-white/80'}`}
                                />
                            ))}
                        </div>
                        {autoplay && (
                            <button
                                type="button"
                                onClick={() => setPaused((p) => !p)}
                                aria-label={paused ? 'Play slideshow' : 'Pause slideshow'}
                                className="flex h-7 w-7 items-center justify-center rounded-full bg-black/25 text-white backdrop-blur-sm hover:bg-black/40"
                            >
                                {paused ? <Play size={13} /> : <Pause size={13} />}
                            </button>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}
