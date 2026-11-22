import axios from 'axios';
import { store } from '@/lib/pwa';

/**
 * Admin notifications on this device (Web Push). Browsers only allow
 * notifications after the person taps "Allow" once; after that this device
 * turns itself on automatically whenever the admin opens the dashboard,
 * unless they switched it off here.
 */

export const pushSupported = () =>
    typeof window !== 'undefined' && window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

/** "Turn off here" on this device: never auto-enable again until "Turn on". */
export const OFF_KEY = 'push_off_here';
export const SNOOZE_KEY = 'push_prompt_snoozed';
export const optedOut = () => store.get(OFF_KEY) === '1';

export async function sha256(text) {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

function keyToBytes(base64url) {
    const pad = '='.repeat((4 - (base64url.length % 4)) % 4);
    const raw = atob((base64url + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

export async function registration() {
    const url = document.querySelector('meta[name="sw-url"]')?.content;
    if (url && !(await navigator.serviceWorker.getRegistration('/'))) await navigator.serviceWorker.register(url, { scope: '/' });
    return navigator.serviceWorker.ready;
}

export async function currentSubscription() {
    const reg = await registration();
    return reg.pushManager.getSubscription();
}

/** Subscribes this browser (needs permission already granted). */
export async function subscribe(publicKey) {
    const reg = await registration();
    return (await reg.pushManager.getSubscription()) || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(publicKey) });
}

/** Tells the server quietly (no page reload, no test notification). */
export async function registerSilently(subscription) {
    const { data } = await axios.post('/admin/push-subscriptions', { ...subscription.toJSON(), silent: true }, { headers: { Accept: 'application/json' } });
    return data?.ok === true;
}

/**
 * Runs on every admin page. Returns what to show:
 *  'on'      – this device gets notifications (nothing to show)
 *  'prompt'  – ask (permission not given yet)
 *  'install' – iPhone/iPad in Safari: add the app to the Home Screen first
 *  'none'    – unsupported, blocked, switched off here or removed from the list
 */
export async function ensurePush(push) {
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true;
    if (!pushSupported()) return ios && !standalone ? 'install' : 'none';
    if (optedOut() || Notification.permission === 'denied') return 'none';

    const existing = await currentSubscription();
    const hash = existing ? await sha256(existing.endpoint) : null;
    if (hash && push.off?.includes(hash)) return 'none'; // switched off from the device list
    if (hash && push.on?.includes(hash)) return 'on';

    if (Notification.permission === 'granted') {
        // Allowed before (this or an earlier login): turn on without asking.
        try {
            return (await registerSilently(existing || (await subscribe(push.publicKey)))) ? 'on' : 'none';
        } catch {
            return 'none';
        }
    }

    return 'prompt';
}
