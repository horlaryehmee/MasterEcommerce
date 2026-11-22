import { router } from '@inertiajs/react';
import { Bell, BellOff, BellRing, Laptop, Send, Smartphone, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { InstallApp } from '@/Components/InstallApp';
import { Badge, Button, Card } from '@/Components/Admin/ui';
import { isIOS, isStandalone } from '@/lib/pwa';

function keyToBytes(base64url) {
    const pad = '='.repeat((4 - (base64url.length % 4)) % 4);
    const raw = atob((base64url + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

async function sha256(text) {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

async function registration() {
    const url = document.querySelector('meta[name="sw-url"]')?.content;
    if (url && !(await navigator.serviceWorker.getRegistration('/'))) await navigator.serviceWorker.register(url, { scope: '/' });
    return navigator.serviceWorker.ready;
}

/** Phone/desktop notifications for new orders, turned on per device. */
export default function NotificationsCard({ push }) {
    const supported = typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
    const iosNeedsInstall = isIOS() && !isStandalone();
    const [permission, setPermission] = useState(supported ? Notification.permission : 'unsupported');
    const [current, setCurrent] = useState(null); // sha256 of this device's subscription endpoint
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!supported) return;
        registration()
            .then((reg) => reg.pushManager.getSubscription())
            .then(async (sub) => setCurrent(sub ? await sha256(sub.endpoint) : null))
            .catch(() => {});
    }, [supported]);

    const enable = async () => {
        setError(null);
        setBusy(true);
        try {
            const result = await Notification.requestPermission();
            setPermission(result);
            if (result !== 'granted') return;
            const reg = await registration();
            const sub = (await reg.pushManager.getSubscription()) || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(push.publicKey) }));
            setCurrent(await sha256(sub.endpoint));
            router.post('/admin/push-subscriptions', sub.toJSON(), { preserveScroll: true });
        } catch (e) {
            setError('This browser couldn’t turn notifications on. Try again, or use Chrome, Edge, Firefox or Safari.');
        } finally {
            setBusy(false);
        }
    };

    const disable = async () => {
        setBusy(true);
        try {
            const reg = await registration();
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                router.delete('/admin/push-subscriptions', { data: { endpoint: sub.endpoint }, preserveScroll: true });
                await sub.unsubscribe();
            }
            setCurrent(null);
        } finally {
            setBusy(false);
        }
    };

    // On here = this browser's subscription is one the server has saved.
    const onHere = current !== null && push.devices.some((d) => d.endpoint_hash === current);

    return (
        <Card
            title={
                <span className="flex items-center gap-2">
                    Phone notifications {push.devices.length > 0 ? <Badge color="green">{push.devices.length === 1 ? '1 device' : `${push.devices.length} devices`}</Badge> : <Badge color="gray">Off</Badge>}
                </span>
            }
            description="Get an alert on your phone or computer the moment an order comes in, even when the app is closed. The admin app’s icon also shows how many new orders are waiting."
        >
            <div className="space-y-4">
                {!supported && !iosNeedsInstall && (
                    <p className="rounded-xl bg-zinc-50 p-4 text-sm text-zinc-600">This browser can’t show notifications from websites. Use Chrome, Edge, Firefox or Safari (on a secure https:// address).</p>
                )}

                {iosNeedsInstall && (
                    <div className="space-y-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-950">
                        <p>
                            <strong>On iPhone and iPad, add the admin app to your Home Screen first</strong> (iOS 16.4 or newer). Then open it from the Home Screen and turn notifications on here.
                        </p>
                        <InstallApp name="the admin app" always>
                            {(install) => (
                                <Button type="button" variant="secondary" onClick={install}>
                                    <Smartphone size={16} /> How to add it
                                </Button>
                            )}
                        </InstallApp>
                    </div>
                )}

                {supported && permission === 'denied' && (
                    <p className="rounded-xl bg-red-50 p-4 text-sm text-red-800">
                        Notifications are blocked for this site. Open your browser’s site settings (the icon left of the address), allow <strong>Notifications</strong>, then reload this page.
                    </p>
                )}

                {supported && permission !== 'denied' && (
                    <div className="flex flex-wrap items-center gap-3">
                        {onHere ? (
                            <>
                                <span className="flex items-center gap-2 text-sm font-medium text-emerald-700">
                                    <BellRing size={18} /> On for this device
                                </span>
                                <Button type="button" variant="secondary" onClick={() => router.post('/admin/push-subscriptions/test', {}, { preserveScroll: true })}>
                                    <Send size={16} /> Send a test
                                </Button>
                                <Button type="button" variant="secondary" loading={busy} onClick={disable}>
                                    <BellOff size={16} /> Turn off here
                                </Button>
                            </>
                        ) : (
                            <Button type="button" loading={busy} onClick={enable}>
                                <Bell size={16} /> Turn on for this device
                            </Button>
                        )}
                    </div>
                )}
                {error && <p className="text-sm text-red-600">{error}</p>}

                {push.devices.length > 0 && (
                    <div>
                        <p className="mb-2 text-xs font-semibold tracking-wide text-zinc-500 uppercase">Devices getting notifications</p>
                        <ul className="divide-y divide-zinc-100 rounded-xl border border-zinc-100">
                            {push.devices.map((d) => {
                                const Icon = /iPhone|iPad|Android/.test(d.device) ? Smartphone : Laptop;
                                return (
                                    <li key={d.id} className="flex items-center gap-3 px-3 py-2.5">
                                        <Icon size={18} className="shrink-0 text-zinc-400" />
                                        <span className="min-w-0 flex-1 text-sm">
                                            <span className="block font-medium text-zinc-900">{d.device}</span>
                                            <span className="block text-xs text-zinc-500">
                                                Added {d.added}
                                                {d.last_used ? ` · last notified ${d.last_used}` : ''}
                                            </span>
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => router.delete('/admin/push-subscriptions', { data: { id: d.id }, preserveScroll: true })}
                                            className="rounded-lg p-2 text-zinc-400 hover:bg-red-50 hover:text-red-600"
                                            aria-label={`Stop notifications on ${d.device}`}
                                            title="Stop notifications on this device"
                                        >
                                            <Trash2 size={16} />
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </div>
        </Card>
    );
}
