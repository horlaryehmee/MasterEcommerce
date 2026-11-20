import { useEffect, useState } from 'react';

/** Phones: below Tailwind's "md" breakpoint (768px). Tablets use the desktop design. */
export const MOBILE_QUERY = '(max-width: 767px)';

/**
 * True on phone-sized screens, updated live on rotate/resize. The site renders
 * in the browser, so the correct value is known on the very first render and
 * only the matching hero design (and its images) is ever loaded.
 */
export default function useIsMobile() {
    const [isMobile, setIsMobile] = useState(() => typeof window !== 'undefined' && window.matchMedia(MOBILE_QUERY).matches);

    useEffect(() => {
        const mq = window.matchMedia(MOBILE_QUERY);
        const onChange = (e) => setIsMobile(e.matches);
        setIsMobile(mq.matches);
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, []);

    return isMobile;
}
