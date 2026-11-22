import { Download, Plus, Share, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { store, useInstall } from '@/lib/pwa';

/** Steps for Safari on iPhone/iPad, which has no install button. */
export function IosSteps({ open, onClose, name }) {
    useEffect(() => {
        if (!open) return;
        const esc = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    }, [open, onClose]);
    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label={`Install ${name}`}>
            <div className="absolute inset-0 bg-black/45" onClick={onClose} />
            <div className="relative w-full max-w-sm rounded-t-3xl bg-white p-6 pb-[calc(env(safe-area-inset-bottom)+1.5rem)] text-zinc-900 shadow-2xl sm:rounded-3xl">
                <button type="button" onClick={onClose} className="absolute top-4 right-4 rounded-full p-2 text-zinc-500 hover:bg-zinc-100" aria-label="Close">
                    <X size={18} />
                </button>
                <h2 className="pr-8 text-lg font-semibold">Install {name}</h2>
                <p className="mt-1 text-sm text-zinc-600">Add it to your Home Screen to open it like an app.</p>
                <ol className="mt-5 space-y-4 text-sm">
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-blue-600">
                            <Share size={18} />
                        </span>
                        <span>
                            Tap the <strong>Share</strong> button in Safari’s toolbar.
                        </span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-800">
                            <Plus size={18} />
                        </span>
                        <span>
                            Scroll down and tap <strong>Add to Home Screen</strong>.
                        </span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-sm font-bold text-zinc-800">Add</span>
                        <span>
                            Tap <strong>Add</strong> in the top corner.
                        </span>
                    </li>
                </ol>
            </div>
        </div>,
        document.body,
    );
}

/** Which browser we're in, for the right "how to install" steps. */
function browserKind() {
    const ua = navigator.userAgent;
    const android = /Android/i.test(ua);
    if (/SamsungBrowser/i.test(ua)) return 'samsung';
    if (/Firefox|FxiOS/i.test(ua)) return android ? 'firefox-android' : 'firefox';
    if (/Edg\//i.test(ua)) return android ? 'chrome-android' : 'edge';
    if (/Chrome|CriOS/i.test(ua)) return android ? 'chrome-android' : 'chrome';
    if (/Safari/i.test(ua) && /Macintosh/i.test(ua)) return 'safari-mac';
    return 'other';
}

const STEPS = {
    chrome: ['Look at the right end of the address bar for the install icon (a screen with a down arrow) and click it.', 'Or open the ⋮ menu → “Cast, save and share” → “Install page as app”.'],
    edge: ['Click the install icon at the right of the address bar (a box with a plus).', 'Or open the ··· menu → “Apps” → “Install this site as an app”.'],
    'chrome-android': ['Tap the ⋮ menu at the top right.', 'Tap “Install app” or “Add to Home screen”, then “Install”.'],
    samsung: ['Tap the ☰ menu at the bottom.', 'Tap “Add page to” → “Home screen”.'],
    'firefox-android': ['Tap the ⋮ menu.', 'Tap “Install” or “Add to Home screen”.'],
    firefox: ['Firefox on computers can’t install web apps.', 'Open this page in Chrome or Edge to install it.'],
    'safari-mac': ['In the menu bar, choose File → “Add to Dock” (macOS Sonoma or newer).'],
    other: ['Open your browser’s menu and look for “Install app” or “Add to Home screen”.', 'Chrome, Edge and Safari support this.'],
};

/** Steps for browsers that didn't offer their own install prompt. */
function BrowserSteps({ open, onClose, name }) {
    if (!open) return null;
    const insecure = !window.isSecureContext;
    const steps = insecure ? ['Installing needs a secure address (https://). Open the site using its https:// address and try again.'] : STEPS[browserKind()];
    return createPortal(
        <div className="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label={`Install ${name}`}>
            <div className="absolute inset-0 bg-black/45" onClick={onClose} />
            <div className="relative w-full max-w-sm rounded-t-3xl bg-white p-6 pb-[calc(env(safe-area-inset-bottom)+1.5rem)] text-zinc-900 shadow-2xl sm:rounded-3xl">
                <button type="button" onClick={onClose} className="absolute top-4 right-4 rounded-full p-2 text-zinc-500 hover:bg-zinc-100" aria-label="Close">
                    <X size={18} />
                </button>
                <h2 className="pr-8 text-lg font-semibold">Install {name}</h2>
                <ol className="mt-4 list-decimal space-y-2 pl-5 text-sm text-zinc-700">
                    {steps.map((step) => (
                        <li key={step}>{step}</li>
                    ))}
                </ol>
                <p className="mt-4 text-xs text-zinc-500">Already installed? Your browser shows “Open in app” in the same place, or open it from your home screen, dock or start menu.</p>
            </div>
        </div>,
        document.body,
    );
}

/**
 * Renders `children(install)` when the app can be installed here, and handles
 * the instructions. With `always`, it also shows when the browser hasn't
 * offered its own prompt (dismissed before, already installed, or a browser
 * without one): the button then shows the steps for that browser.
 * Hidden inside the installed app itself.
 */
export function InstallApp({ name, children, always = false }) {
    const pwa = useInstall();
    const [steps, setSteps] = useState(null); // 'ios' | 'browser'
    const [mounted, setMounted] = useState(false);
    useEffect(() => setMounted(true), []);
    if (!mounted || pwa.installed || (!pwa.available && !always)) return null;

    const install = async () => {
        const outcome = await pwa.install();
        if (outcome === 'ios') setSteps('ios');
        if (outcome === 'unavailable') setSteps('browser');
        return outcome;
    };

    return (
        <>
            {children(install)}
            <IosSteps open={steps === 'ios'} onClose={() => setSteps(null)} name={name} />
            <BrowserSteps open={steps === 'browser'} onClose={() => setSteps(null)} name={name} />
        </>
    );
}

/**
 * A small, dismissible card offering to install the shop on phones. Shown from
 * the second page a visitor views; "Not now" hides it for 30 days.
 */
export function ShopInstallPrompt({ name }) {
    const [show, setShow] = useState(false);
    useEffect(() => {
        const views = Number(store.get('pwa_views') || 0) + 1;
        store.set('pwa_views', String(views));
        const dismissed = Number(store.get('pwa_dismissed') || 0);
        setShow(views >= 2 && Date.now() - dismissed > 30 * 86400000 && window.matchMedia('(max-width: 767px)').matches);
    }, []);
    if (!show) return null;

    const dismiss = () => {
        store.set('pwa_dismissed', String(Date.now()));
        setShow(false);
    };

    return (
        <InstallApp name={name}>
            {(install) => (
                <div className="fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+4.75rem)] z-40 flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-3 shadow-xl shadow-stone-900/10 md:hidden" role="region" aria-label="Install app">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--brand-primary)] text-[var(--brand-on-primary)]">
                        <Download size={18} />
                    </span>
                    <span className="min-w-0 flex-1 text-sm leading-snug text-stone-700">
                        <strong className="block text-stone-900">Get the {name} app</strong>
                        Faster shopping from your home screen.
                    </span>
                    <button
                        type="button"
                        onClick={async () => {
                            // iOS: keep the card (and its steps dialog) until they close it.
                            if ((await install()) !== 'ios') dismiss();
                        }}
                        className="shrink-0 rounded-xl bg-[var(--brand-primary)] px-3.5 py-2 text-sm font-semibold text-[var(--brand-on-primary)]"
                    >
                        Install
                    </button>
                    <button type="button" onClick={dismiss} className="shrink-0 rounded-full p-1.5 text-stone-400 hover:text-stone-700" aria-label="Not now">
                        <X size={16} />
                    </button>
                </div>
            )}
        </InstallApp>
    );
}
