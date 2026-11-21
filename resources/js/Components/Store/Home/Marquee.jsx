import { Fragment, useEffect, useRef, useState } from 'react';

// Scroll speed in pixels per second, so long and short messages move at the same pace.
const SPEED = { slow: 35, normal: 55, fast: 85 };

/**
 * Scrolling text strip under the hero ("Open Mon – Sun ✱ Same-day delivery ✱ …").
 * The messages are repeated until they fill the screen, then the strip is shown
 * twice side by side and slid by one copy's width, so the loop is seamless at
 * any width. Pauses on hover; stands still for visitors who prefer reduced motion.
 */
export default function Marquee({ marquee }) {
    const box = useRef(null);
    const unit = useRef(null);
    const [reps, setReps] = useState(1);
    const [duration, setDuration] = useState(30);

    const speed = SPEED[marquee?.speed] || SPEED.normal;
    const key = marquee ? JSON.stringify([marquee.items, marquee.separator, marquee.uppercase]) : '';

    useEffect(() => {
        if (!box.current || !unit.current || typeof ResizeObserver === 'undefined') return;
        const measure = () => {
            const width = unit.current.offsetWidth;
            if (!width) return;
            const count = Math.max(1, Math.ceil(box.current.clientWidth / width));
            setReps(count);
            setDuration((width * count) / speed);
        };
        const ro = new ResizeObserver(measure);
        ro.observe(box.current);
        measure();
        return () => ro.disconnect();
    }, [key, speed]);

    if (!marquee) return null;

    const accent = marquee.accent || 'var(--brand-accent)';
    const list = (copy) => (
        <span ref={copy === 0 ? unit : undefined} className="flex shrink-0 items-center">
            {marquee.items.map((text, i) => (
                <Fragment key={i}>
                    <span className="px-5 whitespace-nowrap sm:px-8">{text}</span>
                    {marquee.separator && (
                        <span className="text-[1.1em] leading-none" style={{ color: accent }}>
                            {marquee.separator}
                        </span>
                    )}
                </Fragment>
            ))}
        </span>
    );
    const half = (h) => (
        <span className="flex shrink-0 items-center" aria-hidden>
            {Array.from({ length: reps }, (_, r) => (
                <Fragment key={r}>{list(h * reps + r)}</Fragment>
            ))}
        </span>
    );

    return (
        <section
            aria-label="Highlights"
            className={`overflow-hidden ${marquee.border ? 'border-y' : ''}`}
            style={{ backgroundColor: marquee.bg, color: marquee.color, borderColor: marquee.border ? accent : undefined }}
        >
            <ul className="sr-only">
                {marquee.items.map((text, i) => (
                    <li key={i}>{text}</li>
                ))}
            </ul>
            <div ref={box} className={`group/marquee py-3 text-[11px] font-semibold tracking-wide sm:py-4 sm:text-[13px] ${marquee.uppercase ? 'uppercase' : 'text-[13px] font-medium tracking-normal sm:text-[15px]'} motion-reduce:overflow-x-auto`}>
                <div className="flex w-max motion-safe:animate-marquee group-hover/marquee:[animation-play-state:paused]" style={{ animationDuration: `${duration}s` }}>
                    {half(0)}
                    <span className="contents motion-reduce:hidden">{half(1)}</span>
                </div>
            </div>
        </section>
    );
}
