import { useEffect, useState } from 'react';

/**
 * Installable app support: registers the service worker and keeps the
 * browser's install prompt (Chrome, Edge, Samsung Internet) so a button can
 * show it later. Safari on iPhone/iPad has no prompt: people use
 * Share → Add to Home Screen, so we show those steps instead.
 */

let deferredPrompt = null;
const listeners = new Set();
const notify = () => listeners.forEach((fn) => fn());

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        notify();
    });
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        notify();
    });

    // Service workers need https (or localhost).
    if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
        window.addEventListener('load', () => {
            const url = document.querySelector('meta[name="sw-url"]')?.content;
            if (url) navigator.serviceWorker.register(url, { scope: '/' }).catch(() => {});
        });
    }
}

export function isStandalone() {
    if (typeof window === 'undefined') return false;
    return window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

export function isIOS() {
    if (typeof navigator === 'undefined') return false;
    return /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

/**
 * { available, ios, installed, install() }. `available`: a button makes sense
 * (prompt ready, or iOS where we show instructions). install() resolves to
 * 'accepted', 'dismissed' or 'ios' (show the steps).
 */
export function useInstall() {
    const [, setTick] = useState(0);
    useEffect(() => {
        const fn = () => setTick((t) => t + 1);
        listeners.add(fn);
        return () => listeners.delete(fn);
    }, []);

    const installed = isStandalone();
    const ios = isIOS();

    return {
        installed,
        ios,
        available: !installed && (deferredPrompt !== null || ios),
        install: async () => {
            if (deferredPrompt) {
                const prompt = deferredPrompt;
                deferredPrompt = null;
                prompt.prompt();
                const { outcome } = await prompt.userChoice;
                notify();
                return outcome;
            }
            return ios ? 'ios' : 'unavailable';
        },
    };
}

/** localStorage that never throws (private mode, blocked storage). */
export const store = {
    get: (key) => {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set: (key, value) => {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            /* ignore */
        }
    },
};
