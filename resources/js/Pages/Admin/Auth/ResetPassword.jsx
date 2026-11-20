import { useForm } from '@inertiajs/react';
import { Button, TextInput } from '@/Components/Admin/ui';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ResetPassword({ token, email }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    return (
        <AuthLayout title="Choose a new password">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
                }}
                className="space-y-4"
            >
                <TextInput label="Email address" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} required />
                <TextInput label="New password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} error={form.errors.password} help="At least 8 characters, with letters and numbers." required />
                <TextInput label="Confirm new password" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} required />
                <Button loading={form.processing} className="w-full">
                    Reset password
                </Button>
            </form>
        </AuthLayout>
    );
}
