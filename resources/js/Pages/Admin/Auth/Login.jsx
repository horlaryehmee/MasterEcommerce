import { Link, useForm } from '@inertiajs/react';
import { Button, TextInput } from '@/Components/Admin/ui';
import AuthLayout from '@/Layouts/AuthLayout';

export default function Login({ status }) {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/login', { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout title="Admin login" subtitle="Sign in to manage your website.">
            {status && <p className="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{status}</p>}
            <form onSubmit={submit} className="space-y-4">
                <TextInput label="Email address" type="email" autoComplete="username" autoFocus value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} required />
                <TextInput label="Password" type="password" autoComplete="current-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} error={form.errors.password} required />
                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm text-zinc-600">
                        <input type="checkbox" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} className="h-4 w-4 rounded border-zinc-300" />
                        Remember me
                    </label>
                    <Link href="/admin/forgot-password" className="text-sm font-medium text-zinc-700 hover:text-zinc-900">
                        Forgot password?
                    </Link>
                </div>
                <Button loading={form.processing} className="w-full">
                    Log in
                </Button>
            </form>
        </AuthLayout>
    );
}
