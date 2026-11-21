import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Img from './Img';

/**
 * Product images in a native swipe track: the image follows the finger and
 * snaps into place. Dots on phones, thumbnails from small tablets up, arrows
 * (and arrow keys) for mouse and keyboard.
 *
 * `activeIndex` jumps to an image from outside (the chosen variation's photo).
 */
export default function ProductGallery({ images, name, activeIndex = null }) {
    const [index, setIndex] = useState(0);
    const track = useRef(null);
    const count = images.length;

    // Keep the active dot/thumbnail in step with the swipe position.
    useEffect(() => {
        const el = track.current;
        if (!el || count < 2) return;
        let frame = null;
        const onScroll = () => {
            if (frame) return;
            frame = requestAnimationFrame(() => {
                frame = null;
                setIndex(Math.round(el.scrollLeft / el.clientWidth));
            });
        };
        el.addEventListener('scroll', onScroll, { passive: true });
        return () => {
            el.removeEventListener('scroll', onScroll);
            if (frame) cancelAnimationFrame(frame);
        };
    }, [count]);

    useEffect(() => {
        const el = track.current;
        if (activeIndex === null || activeIndex === undefined || !el || activeIndex >= count) return;
        el.scrollTo({ left: activeIndex * el.clientWidth, behavior: 'smooth' });
        setIndex(activeIndex);
    }, [activeIndex, count]);

    if (count === 0) {
        return (
            <div className="aspect-square overflow-hidden rounded-3xl">
                <Img image={null} alt={name} />
            </div>
        );
    }

    const go = (i) => {
        const el = track.current;
        const next = (i + count) % count;
        if (el) el.scrollTo({ left: next * el.clientWidth, behavior: 'smooth' });
        setIndex(next);
    };

    const onKey = (e) => {
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            go(index + 1);
        }
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            go(index - 1);
        }
    };

    const arrow = 'absolute top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-stone-800 shadow-sm backdrop-blur transition hover:bg-white active:scale-90 [@media(hover:hover)]:flex';

    return (
        <div>
            <div className="relative overflow-hidden rounded-3xl bg-stone-100" onKeyDown={onKey} aria-roledescription="carousel" aria-label={`${name} images`}>
                <div ref={track} className="no-scrollbar flex aspect-square snap-x snap-mandatory overflow-x-auto overscroll-x-contain" tabIndex={count > 1 ? 0 : -1}>
                    {images.map((img, i) => (
                        <div key={i} className="relative h-full w-full shrink-0 snap-center snap-always" aria-roledescription="slide" aria-label={`${i + 1} of ${count}`} aria-hidden={i !== index}>
                            <Img image={img} eager={i === 0} sizes="(min-width: 1024px) 50vw, 100vw" className="h-full w-full object-cover" />
                        </div>
                    ))}
                </div>

                {count > 1 && (
                    <>
                        <button type="button" onClick={() => go(index - 1)} aria-label="Previous image" className={`${arrow} left-3`}>
                            <ChevronLeft size={20} />
                        </button>
                        <button type="button" onClick={() => go(index + 1)} aria-label="Next image" className={`${arrow} right-3`}>
                            <ChevronRight size={20} />
                        </button>

                        {/* Phones: dots (the active one stretches into a pill). */}
                        <div className="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center sm:hidden">
                            <div className="pointer-events-auto flex items-center gap-1.5 rounded-full bg-stone-900/45 px-2.5 py-1.5 backdrop-blur">
                                {images.map((_, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => go(i)}
                                        aria-label={`Show image ${i + 1}`}
                                        aria-current={i === index}
                                        className={`h-1.5 rounded-full transition-all duration-300 ${i === index ? 'w-5 bg-white' : 'w-1.5 bg-white/55'}`}
                                    />
                                ))}
                            </div>
                        </div>
                        <span className="absolute right-3 bottom-3 hidden rounded-full bg-stone-900/70 px-2.5 py-1 text-xs font-medium text-white sm:block">
                            {index + 1} / {count}
                        </span>
                    </>
                )}
            </div>

            {count > 1 && (
                <div className="no-scrollbar mt-3 hidden gap-3 overflow-x-auto pb-1 sm:flex" role="tablist" aria-label="Product images">
                    {images.map((img, i) => (
                        <button
                            key={i}
                            type="button"
                            role="tab"
                            aria-selected={i === index}
                            aria-label={`Show image ${i + 1}`}
                            onClick={() => go(i)}
                            className={`h-20 w-20 shrink-0 overflow-hidden rounded-xl border-2 bg-stone-100 transition sm:h-24 sm:w-24 ${i === index ? 'border-stone-900' : 'border-transparent opacity-70 hover:opacity-100'}`}
                        >
                            <img src={img.sm || img.src} alt="" loading="lazy" className="h-full w-full object-cover" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
