import { useForm } from '@inertiajs/react';
import { Button, TextInput } from '@/Components/Admin/ui';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ConfirmPassword() {
    const form = useForm({ current_password: '' });
    return (
        <AuthLayout title="Confirm your password" subtitle="Enter your administrator password to continue.">
            <form className="space-y-4" onSubmit={(e) => {
                e.preventDefault();
                form.post('/admin/confirm-password', { onFinish: () => form.reset() });
            }}>
                <TextInput type="password" label="Current password" autoComplete="current-password" value={form.data.current_password} required autoFocus
                    onChange={(e) => form.setData('current_password', e.target.value)} error={form.errors.current_password} />
                <Button loading={form.processing}>Confirm password</Button>
            </form>
        </AuthLayout>
    );
}
