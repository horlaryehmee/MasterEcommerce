import { Link, useForm } from '@inertiajs/react';
import { Button, TextInput } from '@/Components/Admin/ui';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ForgotPassword({ status }) {
    const form = useForm({ email: '' });

    return (
        <AuthLayout title="Reset your password" subtitle="Enter the administrator email address and we'll send you a reset link.">
            {status && <p className="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{status}</p>}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/forgot-password');
                }}
                className="space-y-4"
            >
                <TextInput label="Email address" type="email" autoFocus value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} required />
                <Button loading={form.processing} className="w-full">
                    Send reset link
                </Button>
                <p className="text-center text-sm">
                    <Link href="/admin/login" className="text-zinc-600 hover:text-zinc-900">
                        Back to login
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
