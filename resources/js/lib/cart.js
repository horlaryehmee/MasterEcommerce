import { router } from '@inertiajs/react';
import { useState } from 'react';

const OPEN_EVENT = 'cart:open';

/** Open the cart drawer from anywhere (e.g. the header cart icon). */
export function openCart() {
    window.dispatchEvent(new Event(OPEN_EVENT));
}

export function onOpenCart(handler) {
    window.addEventListener(OPEN_EVENT, handler);
    return () => window.removeEventListener(OPEN_EVENT, handler);
}

const STATE_EVENT = 'cart:state';

/** The drawer announces when it opens/closes, so the bottom nav can highlight its Cart tab. */
export function announceCartState(open) {
    window.dispatchEvent(new CustomEvent(STATE_EVENT, { detail: open }));
}

export function onCartState(handler) {
    const listener = (e) => handler(e.detail);
    window.addEventListener(STATE_EVENT, listener);
    return () => window.removeEventListener(STATE_EVENT, listener);
}

// Cart requests keep the current page as it is (scroll, open menus, form state);
// the refreshed cart arrives in the shared `cart` prop.
const KEEP = { preserveScroll: true, preserveState: true };

/** Add a product. The server flashes `cart_added`, which opens the drawer. */
export function useAddToCart() {
    const [busy, setBusy] = useState(null);
    const add = (productId, { quantity = 1, checkout = false, onSuccess } = {}) =>
        router.post(
            '/cart',
            { product_id: productId, quantity, checkout },
            {
                ...KEEP,
                // Going to checkout is a real page change.
                preserveState: !checkout,
                onStart: () => setBusy(checkout ? 'checkout' : 'cart'),
                onFinish: () => setBusy(null),
                onSuccess,
            },
        );
    return { add, busy };
}

export function updateCartItem(productId, quantity, options = {}) {
    return quantity > 0 ? router.patch(`/cart/${productId}`, { quantity }, { ...KEEP, ...options }) : router.delete(`/cart/${productId}`, { ...KEEP, ...options });
}
