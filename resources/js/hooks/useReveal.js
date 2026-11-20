import { useEffect, useRef } from 'react';

let observer = null;

/** One observer for the whole page; each element is revealed once, then forgotten. */
function getObserver() {
    if (observer || typeof IntersectionObserver === 'undefined') return observer;
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.dataset.reveal = 'in';
                    observer.unobserve(entry.target);
                }
            }
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
    );
    return observer;
}

/**
 * Fade-and-rise an element as it scrolls into view. Pair the returned ref with
 * `data-reveal` on the element (styled in app.css; skipped for reduced motion).
 */
export default function useReveal() {
    const ref = useRef(null);

    useEffect(() => {
        const el = ref.current;
        const io = getObserver();
        if (!el) return;
        // Already on screen when it mounts (or no observer support): show it straight away,
        // so visible content never waits on the observer's first callback.
        const r = el.getBoundingClientRect();
        if (!io || (r.top < window.innerHeight && r.bottom > 0 && r.width > 0)) {
            el.dataset.reveal = 'in';
            return;
        }
        io.observe(el);
        return () => io.unobserve(el);
    }, []);

    return ref;
}
