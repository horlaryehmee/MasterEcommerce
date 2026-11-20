/**
 * Sends storefront events to whichever pixels are configured (Website
 * Settings → Tracking & ads). Events arrive from the server in GA4's
 * e-commerce format: { event, params: { currency, value, items, … } }.
 *
 * The pixel scripts themselves are loaded in resources/views/partials/head-extras.blade.php;
 * when they aren't (admin, logged-in owner, nothing configured) everything here is a no-op.
 */

const META_EVENTS = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'Purchase', search: 'Search' };
const TIKTOK_EVENTS = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'CompletePayment', search: 'Search' };

function safely(fn) {
    try {
        fn();
    } catch (e) {
        // A blocked or broken pixel must never break the shop.
        if (import.meta.env.DEV) console.warn('[analytics]', e);
    }
}

export function trackEvent({ event, params = {} }, config) {
    if (!config) return;
    const items = params.items || [];

    // GA4 (and Google Ads remarketing) via gtag.
    if (window.gtag && (config.ga4 || config.ads)) {
        safely(() => window.gtag('event', event, params));
        if (event === 'purchase' && config.adsPurchaseLabel) {
            safely(() => window.gtag('event', 'conversion', { send_to: config.adsPurchaseLabel, value: params.value, currency: params.currency, transaction_id: params.transaction_id }));
        }
    }

    // Google Tag Manager: standard GA4 e-commerce data layer events.
    if (config.gtm && window.dataLayer) {
        safely(() => {
            window.dataLayer.push({ ecommerce: null });
            const { items: list, ...rest } = params;
            window.dataLayer.push(event === 'search' ? { event, ...params } : { event, ecommerce: { ...rest, items: list } });
        });
    }

    // Meta Pixel.
    if (config.meta && window.fbq && META_EVENTS[event]) {
        safely(() => {
            const data =
                event === 'search'
                    ? { search_string: params.search_term }
                    : { content_type: 'product', content_ids: items.map((i) => i.item_id), contents: items.map((i) => ({ id: i.item_id, quantity: i.quantity })), num_items: items.reduce((n, i) => n + (i.quantity || 1), 0), value: params.value, currency: params.currency };
            // The order number deduplicates browser and server-side purchase events.
            window.fbq('track', META_EVENTS[event], data, params.transaction_id ? { eventID: `order-${params.transaction_id}` } : undefined);
        });
    }

    // TikTok Pixel.
    if (config.tiktok && window.ttq && TIKTOK_EVENTS[event]) {
        safely(() => {
            const data =
                event === 'search'
                    ? { query: params.search_term }
                    : { content_type: 'product', contents: items.map((i) => ({ content_id: i.item_id, content_name: i.item_name, quantity: i.quantity, price: i.price })), value: params.value, currency: params.currency };
            window.ttq.track(TIKTOK_EVENTS[event], data);
        });
    }
}

/** Page views for in-app (Inertia) navigation; the first page view is sent by the pixel snippets. */
export function trackPageView(config, title) {
    if (!config) return;
    const page = { page_location: window.location.href, page_path: window.location.pathname + window.location.search, page_title: title || document.title };
    if (window.gtag && (config.ga4 || config.ads)) safely(() => window.gtag('event', 'page_view', page));
    // GTM: use this event (or the built-in History Change trigger) for virtual page views.
    if (config.gtm && window.dataLayer) safely(() => window.dataLayer.push({ event: 'virtual_page_view', ...page }));
    if (config.meta && window.fbq) safely(() => window.fbq('track', 'PageView'));
    if (config.tiktok && window.ttq) safely(() => window.ttq.page());
}
