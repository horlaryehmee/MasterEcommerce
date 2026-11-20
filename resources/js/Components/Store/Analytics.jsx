import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { trackEvent } from '@/lib/analytics';
import { trackStat } from '@/lib/stats';

/**
 * Fires the storefront's analytics events (page views are sent from app.jsx):
 * - the page's own events (`track` prop: view item, begin checkout, search) once per visit,
 * - one-off events the server flashed (`analytics`: add to cart, purchase).
 */
/** Built-in stats get the shop events too (label = what, value = money). */
function statEvent({ event, params = {} }) {
    const first = params.items?.[0];
    const label = { view_item: first?.item_name, add_to_cart: first?.item_name, purchase: params.transaction_id && `#${params.transaction_id}`, search: params.search_term }[event];
    trackStat(event, label ?? null, params.value ?? null);
}

function dispatch(e, config) {
    if (config) trackEvent(e, config);
    statEvent(e);
}

// Module-level (not component state): the layout can mount more than once for the same
// visit (e.g. a prefetched page being refreshed), and page events must still fire once.
let lastTrackedUrl = null;
let lastFlashed = null;

export default function Analytics() {
    const { props, url } = usePage();
    const config = props.tracking;

    // Page events: only when the URL changes, so cart updates on the same page don't repeat them.
    useEffect(() => {
        if (lastTrackedUrl === url) return;
        lastTrackedUrl = url;
        // After the page view has been sent (app.jsx), so the event lands in this visit.
        setTimeout(() => (props.track || []).forEach((e) => dispatch(e, config)), 0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, config]);

    // Flashed events arrive once, in the response that follows the action.
    useEffect(() => {
        if (!props.analytics || props.analytics === lastFlashed) return;
        lastFlashed = props.analytics;
        setTimeout(() => props.analytics.forEach((e) => dispatch(e, config)), 0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [props.analytics]);

    return null;
}
