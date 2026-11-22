import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button, TextInput } from '@/Components/Admin/ui';
import AuthLayout from '@/Layouts/AuthLayout';

export default function TwoFactorChallenge() {
    const [recovery, setRecovery] = useState(false);
    const form = useForm({ code: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/two-factor-challenge', { onFinish: () => form.reset('code') });
    };

    return (
        <AuthLayout title="Two-factor sign-in" subtitle={recovery ? 'Enter one of your recovery codes.' : 'Enter the 6-digit code from your authenticator app.'}>
            <form onSubmit={submit} className="space-y-4">
                {recovery ? (
                    <TextInput key="recovery" label="Recovery code" autoComplete="off" autoFocus spellCheck={false} placeholder="XXXXX-XXXXX" maxLength={11} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} error={form.errors.code} required />
                ) : (
                    <TextInput key="code" label="Code" inputMode="numeric" autoComplete="one-time-code" autoFocus placeholder="123456" maxLength={7} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} error={form.errors.code} required />
                )}
                <Button loading={form.processing} className="w-full">
                    Verify
                </Button>
                <div className="flex items-center justify-between text-sm">
                    <button
                        type="button"
                        onClick={() => {
                            setRecovery(!recovery);
                            form.setData('code', '');
                            form.clearErrors();
                        }}
                        className="font-medium text-zinc-700 hover:text-zinc-900"
                    >
                        {recovery ? 'Use the app code instead' : 'Lost your phone? Use a recovery code'}
                    </button>
                    <Link href="/admin/login" className="text-zinc-500 hover:text-zinc-900">
                        Back
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}
