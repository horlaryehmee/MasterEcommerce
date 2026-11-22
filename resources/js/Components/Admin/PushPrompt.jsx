import { router, usePage } from '@inertiajs/react';
import { BellRing, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { InstallApp } from '@/Components/InstallApp';
import { Button } from '@/Components/Admin/ui';
import { store } from '@/lib/pwa';
import { ensurePush, SNOOZE_KEY, subscribe, OFF_KEY } from '@/lib/push';

const SNOOZE_DAYS = 7;

/**
 * On every admin page: turns notifications on automatically when this device
 * already allows them, otherwise asks once with a card ("Not now" hides it
 * for a week). Nothing is shown when they're on, blocked or switched off here.
 */
export default function PushPrompt() {
    const { admin } = usePage().props;
    const push = admin?.push;
    const [state, setState] = useState(null);
    const [busy, setBusy] = useState(false);

    const onKey = (push?.on ?? []).join(',');
    useEffect(() => {
        if (!push) return;
        let alive = true;
        ensurePush(push).then((s) => alive && setState(s)).catch(() => {});
        return () => {
            alive = false;
        };
        // Re-check when the server's device list changes (turned on/off elsewhere).
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [onKey]);

    const snoozed = Date.now() - Number(store.get(SNOOZE_KEY) || 0) < SNOOZE_DAYS * 86400000;
    if (!push || snoozed || (state !== 'prompt' && state !== 'install')) return null;

    const snooze = () => {
        store.set(SNOOZE_KEY, String(Date.now()));
        setState('none');
    };

    const enable = async () => {
        setBusy(true);
        try {
            // Must run straight from the tap: browsers only show the permission dialog then.
            const result = await Notification.requestPermission();
            if (result !== 'granted') {
                setState('none');
                return;
            }
            const sub = await subscribe(push.publicKey);
            store.set(OFF_KEY, '');
            router.post('/admin/push-subscriptions', sub.toJSON(), { preserveScroll: true, onSuccess: () => setState('on') });
        } catch {
            setState('none');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div role="region" aria-label="Order notifications" className="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:flex-nowrap">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-white">
                {state === 'install' ? <Smartphone size={20} /> : <BellRing size={20} />}
            </span>
            <div className="min-w-0 flex-1 basis-48">
                <p className="text-sm font-semibold text-zinc-900">{state === 'install' ? 'Get new-order alerts on your iPhone' : 'Get notified when an order comes in'}</p>
                <p className="mt-0.5 text-sm text-zinc-600">
                    {state === 'install'
                        ? 'Add the admin app to your Home Screen, open it from there, and turn notifications on.'
                        : 'An alert on this device for every new order, even when the app is closed. You can turn it off any time in Profile.'}
                </p>
            </div>
            <div className="flex w-full items-center gap-2 sm:w-auto">
                {state === 'install' ? (
                    <InstallApp name="the admin app" always>
                        {(install) => (
                            <Button type="button" onClick={install} className="flex-1 sm:flex-none">
                                Show me how
                            </Button>
                        )}
                    </InstallApp>
                ) : (
                    <Button type="button" loading={busy} onClick={enable} className="flex-1 sm:flex-none">
                        Turn on
                    </Button>
                )}
                <Button type="button" variant="secondary" onClick={snooze}>
                    Not now
                </Button>
            </div>
        </div>
    );
}
