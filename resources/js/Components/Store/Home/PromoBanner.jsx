import { ArrowRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import Img from '../Img';
import SmartLink from '../SmartLink';

/** Light colours get dark text; dark ones white text. */
function isDark(hex) {
    if (!/^#[0-9a-f]{6}$/i.test(hex || '')) return false;
    const n = parseInt(hex.slice(1), 16);
    return (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255 < 0.55;
}

function useCountdown(endsAt) {
    const target = endsAt ? new Date(endsAt).getTime() : null;
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        if (!target) return;
        const t = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(t);
    }, [target]);
    if (!target) return null;
    const left = Math.max(0, target - now);
    if (left === 0) return { over: true };
    const s = Math.floor(left / 1000);
    return { over: false, days: Math.floor(s / 86400), hours: Math.floor((s % 86400) / 3600), minutes: Math.floor((s % 3600) / 60), seconds: s % 60 };
}

/** "Offer ends in 02d 14h 33m 05s" — hidden when there's no end date or it has passed. */
function Countdown({ endsAt, dark }) {
    const c = useCountdown(endsAt);
    if (!c || c.over) return null;
    const units = [
        [c.days, 'Days'],
        [c.hours, 'Hrs'],
        [c.minutes, 'Min'],
        [c.seconds, 'Sec'],
    ];
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
            <p className={`text-[11px] font-semibold tracking-[0.16em] uppercase ${dark ? 'text-white/70' : 'text-stone-500'}`}>Offer ends in</p>
            <div className="flex gap-1.5" role="timer" aria-label={`Offer ends in ${c.days} days, ${c.hours} hours and ${c.minutes} minutes`}>
                {units.map(([value, label]) => (
                    <span key={label} className={`flex min-w-[3.25rem] flex-col items-center rounded-xl px-2 py-1.5 ${dark ? 'bg-white/10 ring-1 ring-white/15' : 'bg-white/75 ring-1 ring-stone-900/5'}`}>
                        <span className={`text-lg leading-tight font-bold tabular-nums ${dark ? 'text-white' : 'text-stone-950'}`}>{String(value).padStart(2, '0')}</span>
                        <span className={`text-[9px] font-semibold tracking-wider uppercase ${dark ? 'text-white/60' : 'text-stone-500'}`}>{label}</span>
                    </span>
                ))}
            </div>
        </div>
    );
}

/** Round, slightly tilted sticker: "Up to 30% off". */
function Sticker({ text, className = '' }) {
    if (!text) return null;
    return (
        <span className={`pointer-events-none flex aspect-square items-center justify-center rounded-full bg-accent p-2 text-center text-on-accent shadow-xl shadow-black/15 ${className}`} aria-hidden>
            <span className="flex h-full w-full items-center justify-center rounded-full border border-dashed border-current/40 px-2 text-[13px] leading-[1.05] font-extrabold tracking-tight uppercase sm:text-[15px]">{text}</span>
        </span>
    );
}

/**
 * Promotional banner, light editorial style: a soft card that melts into the
 * photo (the background colour can be matched to the photo's backdrop), a big
 * headline with an accent stroke, a sticker and an optional countdown. The
 * banner hides itself once the offer's end date passes (HomeController).
 */
export default function PromoBanner({ promo }) {
    if (!promo) return null;
    const bg = promo.bg || 'var(--brand-secondary)';
    const dark = promo.bg ? isDark(promo.bg) : false;
    const hasImage = !!promo.image;

    return (
        <section className="py-8 sm:py-12" aria-label={promo.heading}>
            <div className="container-page">
                <div className={`relative isolate overflow-hidden rounded-[2rem] ${promo.bg ? (dark ? 'text-white' : 'text-stone-950') : 'text-on-surface'}`} style={{ backgroundColor: bg }}>
                    {hasImage && (
                        <>
                            {/* Phones and tablets: the photo on top, fading into the card below. */}
                            <div className="relative aspect-[16/10] lg:hidden">
                                <Img image={promo.image} sizes="100vw" className="h-full w-full object-cover object-right" />
                                <div className="absolute inset-x-0 bottom-0 h-1/3" style={{ background: `linear-gradient(to top, ${bg}, transparent)` }} aria-hidden />
                                <Sticker text={promo.badge} className="absolute top-4 left-4 w-24 -rotate-12 sm:w-28" />
                            </div>
                            {/* Desktop: the photo fills the right side and melts into the card on its left edge. */}
                            <div className="absolute inset-y-0 right-0 -z-10 hidden w-[64%] lg:block" aria-hidden>
                                <Img image={promo.image} sizes="(min-width: 1280px) 800px, 64vw" className="h-full w-full object-cover object-right" />
                                <div className="absolute inset-y-0 left-0 w-2/5" style={{ background: `linear-gradient(to right, ${bg}, transparent)` }} />
                                <Sticker text={promo.badge} className="absolute bottom-10 left-[16%] w-32 -rotate-12 xl:w-36" />
                            </div>
                        </>
                    )}

                    <div className={`relative flex flex-col justify-center px-6 pt-2 pb-8 sm:px-10 sm:pb-10 lg:min-h-[460px] lg:py-14 lg:pl-14 xl:pl-16 ${hasImage ? 'lg:max-w-[46%]' : 'lg:max-w-2xl lg:pt-14'}`}>
                        {!hasImage && <Sticker text={promo.badge} className="absolute top-6 right-6 w-24 rotate-12 sm:w-28" />}
                        {promo.eyebrow && (
                            <p className={`flex items-center gap-2 text-xs font-semibold tracking-[0.18em] uppercase ${dark ? 'text-white/80' : 'text-accent'}`}>
                                <span className="h-px w-6 bg-current" aria-hidden />
                                {promo.eyebrow}
                            </p>
                        )}
                        <h2 className="font-heading mt-4 text-[2.2rem] leading-[1.02] font-extrabold tracking-tight text-balance sm:text-5xl xl:text-6xl">{promo.heading}</h2>
                        {/* Hand-drawn accent stroke under the headline. */}
                        <svg viewBox="0 0 220 14" className="mt-3 h-3 w-36 text-accent sm:w-44" fill="none" aria-hidden>
                            <path d="M2 9.5C40 3.5 92 2.5 132 5.5c30 2.2 56 4.4 86 1" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" />
                        </svg>
                        {promo.description && <p className={`mt-5 max-w-md text-[15px] leading-relaxed sm:text-base ${dark ? 'text-white/80' : 'opacity-75'}`}>{promo.description}</p>}

                        {(promo.button || promo.ends_at) && (
                            <div className="mt-8 flex flex-col gap-6">
                                {promo.button && (
                                    <SmartLink href={promo.button.link} className={`btn group h-12 w-fit px-7 text-[15px] shadow-lg shadow-black/10 ${dark ? 'bg-white text-stone-950 hover:bg-white/90' : 'btn-primary'}`}>
                                        {promo.button.text} <ArrowRight size={17} className="transition-transform group-hover:translate-x-0.5" />
                                    </SmartLink>
                                )}
                                <Countdown endsAt={promo.ends_at} dark={dark} />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
