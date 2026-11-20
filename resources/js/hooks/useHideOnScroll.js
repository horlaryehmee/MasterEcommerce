import { useEffect, useRef, useState } from 'react';

/**
 * Tuck a bar away while scrolling down and bring it back on scroll up.
 * Always shown near the top and bottom of the page, and while `pinned`.
 * Also reports whether the page has scrolled at all (for a raised shadow).
 */
export default function useHideOnScroll(pinned = false) {
    const [hidden, setHidden] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const last = useRef(0);
    const hiddenRef = useRef(false);

    useEffect(() => {
        let frame = null;
        last.current = window.scrollY;

        const onScroll = () => {
            if (frame) return;
            frame = requestAnimationFrame(() => {
                frame = null;
                const y = window.scrollY;
                setScrolled(y > 4);

                const nearBottom = window.innerHeight + y >= document.documentElement.scrollHeight - 80;
                let next = hiddenRef.current;
                if (pinned || y < 120 || nearBottom) next = false;
                else if (y - last.current > 8) next = true;
                else if (last.current - y > 8) next = false;
                else return; // small jitter: keep the reference point
                last.current = y;
                if (next !== hiddenRef.current) {
                    hiddenRef.current = next;
                    setHidden(next);
                }
            });
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => {
            window.removeEventListener('scroll', onScroll);
            if (frame) cancelAnimationFrame(frame);
        };
    }, [pinned]);

    return { hidden: hidden && !pinned, scrolled };
}
