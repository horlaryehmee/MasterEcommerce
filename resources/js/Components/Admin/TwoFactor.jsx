import { router, useForm } from '@inertiajs/react';
import { Check, Copy, Download, KeyRound, ShieldCheck, ShieldOff, Smartphone } from 'lucide-react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import { Badge, Button, Card, Modal, TextInput } from '@/Components/Admin/ui';

function QrImage({ uri }) {
    const [src, setSrc] = useState(null);
    useEffect(() => {
        // Made in the browser: the secret never goes to a third-party QR service.
        QRCode.toDataURL(uri, { width: 220, margin: 1, errorCorrectionLevel: 'M' }).then(setSrc).catch(() => setSrc(null));
    }, [uri]);
    return (
        <div className="flex h-[220px] w-[220px] shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white p-2">
            {src ? <img src={src} alt="QR code for your authenticator app" width={204} height={204} /> : <span className="text-xs text-zinc-400">Loading…</span>}
        </div>
    );
}

/** Asks for the current password before a sensitive change. */
function PasswordDialog({ open, onClose, title, confirmLabel, danger, method = 'post', url, children }) {
    const form = useForm({ current_password: '' });
    useEffect(() => {
        if (open) form.reset();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);
    const submit = (e) => {
        e?.preventDefault();
        form.submit(method, url, { preserveScroll: true, onSuccess: onClose });
    };
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            footer={
                <>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="button" variant={danger ? 'danger' : 'primary'} loading={form.processing} onClick={submit}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                {children}
                <TextInput label="Your current password" type="password" autoComplete="current-password" autoFocus value={form.data.current_password} onChange={(e) => form.setData('current_password', e.target.value)} error={form.errors.current_password} required />
            </form>
        </Modal>
    );
}

function RecoveryCodes({ codes }) {
    const [copied, setCopied] = useState(false);
    const text = codes.join('\n');
    const download = () => {
        const url = URL.createObjectURL(new Blob([`Recovery codes for ${window.location.host} admin\nEach code works once.\n\n${text}\n`], { type: 'text/plain' }));
        const a = Object.assign(document.createElement('a'), { href: url, download: 'admin-recovery-codes.txt' });
        a.click();
        URL.revokeObjectURL(url);
    };
    return (
        <div className="rounded-xl border-2 border-amber-300 bg-amber-50 p-4">
            <p className="text-sm font-semibold text-amber-950">Save these recovery codes now</p>
            <p className="mt-1 text-sm text-amber-900">If you lose your phone, each code lets you sign in once. They won’t be shown again. Keep them somewhere safe, like a password manager.</p>
            <ul className="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-zinc-900">
                {codes.map((c) => (
                    <li key={c} className="rounded-lg bg-white px-3 py-2 text-center tracking-wider">
                        {c}
                    </li>
                ))}
            </ul>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() =>
                        navigator.clipboard?.writeText(text).then(() => {
                            setCopied(true);
                            setTimeout(() => setCopied(false), 1500);
                        })
                    }
                >
                    {copied ? <Check size={16} /> : <Copy size={16} />} {copied ? 'Copied' : 'Copy'}
                </Button>
                <Button type="button" variant="secondary" onClick={download}>
                    <Download size={16} /> Download
                </Button>
            </div>
        </div>
    );
}

export default function TwoFactorCard({ twoFactor, recoveryCodes }) {
    const [dialog, setDialog] = useState(null);
    const confirm = useForm({ code: '' });
    const { enabled, setup } = twoFactor;

    return (
        <Card
            title={
                <span className="flex items-center gap-2">
                    Two-factor sign-in {enabled ? <Badge color="green">On</Badge> : <Badge color="amber">Off</Badge>}
                </span>
            }
            description="After your password, you also enter a 6-digit code from an app on your phone. Even someone who knows your password can’t get in without your phone."
        >
            <div className="space-y-5">
                {recoveryCodes?.length > 0 && <RecoveryCodes codes={recoveryCodes} />}

                {enabled && (
                    <>
                        <p className="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">
                            <ShieldCheck size={20} className="mt-0.5 shrink-0 text-emerald-700" />
                            <span>
                                Your account is protected since {twoFactor.confirmed_at}. You have <strong>{twoFactor.recovery_left}</strong> unused recovery {twoFactor.recovery_left === 1 ? 'code' : 'codes'}.
                            </span>
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="secondary" onClick={() => setDialog('codes')}>
                                <KeyRound size={16} /> New recovery codes
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => setDialog('disable')} className="text-red-600">
                                <ShieldOff size={16} /> Turn off
                            </Button>
                        </div>
                    </>
                )}

                {!enabled && !setup && (
                    <div className="flex flex-wrap items-center gap-4">
                        <p className="flex min-w-0 flex-1 items-start gap-3 text-sm text-zinc-600">
                            <Smartphone size={20} className="mt-0.5 shrink-0 text-zinc-400" />
                            You’ll need an authenticator app such as Google Authenticator, Microsoft Authenticator or Authy.
                        </p>
                        <Button type="button" onClick={() => setDialog('enable')}>
                            <ShieldCheck size={16} /> Turn on
                        </Button>
                    </div>
                )}

                {!enabled && setup && (
                    <div className="space-y-4">
                        <ol className="list-decimal space-y-1 pl-5 text-sm text-zinc-600">
                            <li>Open your authenticator app and add an account (usually a “+” button).</li>
                            <li>Scan this QR code, or type the setup key.</li>
                            <li>Enter the 6-digit code the app shows.</li>
                        </ol>
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
                            <QrImage uri={setup.uri} />
                            <div className="min-w-0 flex-1 space-y-4">
                                <div>
                                    <span className="mb-1.5 block text-sm font-medium text-zinc-800">Setup key</span>
                                    <code className="block rounded-lg bg-zinc-100 px-3 py-2 font-mono text-sm break-all text-zinc-900 select-all">{setup.secret}</code>
                                </div>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        confirm.post('/admin/profile/two-factor/confirm', { preserveScroll: true, onSuccess: () => confirm.reset() });
                                    }}
                                    className="space-y-3"
                                >
                                    <TextInput label="6-digit code" inputMode="numeric" autoComplete="one-time-code" maxLength={7} placeholder="123456" value={confirm.data.code} onChange={(e) => confirm.setData('code', e.target.value)} error={confirm.errors.code} required />
                                    <div className="flex flex-wrap gap-2">
                                        <Button loading={confirm.processing}>Confirm and turn on</Button>
                                        <Button type="button" variant="secondary" onClick={() => router.delete('/admin/profile/two-factor', { preserveScroll: true })}>
                                            Cancel
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <PasswordDialog open={dialog === 'enable'} onClose={() => setDialog(null)} title="Turn on two-factor sign-in" confirmLabel="Continue" url="/admin/profile/two-factor" />
            <PasswordDialog open={dialog === 'codes'} onClose={() => setDialog(null)} title="Make new recovery codes" confirmLabel="Make new codes" url="/admin/profile/two-factor/recovery-codes">
                <p>Your old recovery codes will stop working.</p>
            </PasswordDialog>
            <PasswordDialog open={dialog === 'disable'} onClose={() => setDialog(null)} title="Turn off two-factor sign-in?" confirmLabel="Turn off" danger method="delete" url="/admin/profile/two-factor">
                <p>Anyone who learns your password will be able to sign in. We recommend keeping it on.</p>
            </PasswordDialog>
        </Card>
    );
}
