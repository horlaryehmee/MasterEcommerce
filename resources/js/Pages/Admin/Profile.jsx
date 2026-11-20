import { useForm } from '@inertiajs/react';
import { Button, Card, FormActions, PageHeader, TextInput } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Profile({ profile }) {
    const details = useForm({ name: profile.name, email: profile.email });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <AdminLayout title="Profile">
            <PageHeader title="Profile" description="Your administrator login details." />
            <div className="grid max-w-3xl gap-6">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        details.put('/admin/profile', { preserveScroll: true });
                    }}
                >
                    <Card title="Account details">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextInput label="Name" value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} error={details.errors.name} required />
                            <TextInput label="Login email" type="email" value={details.data.email} onChange={(e) => details.setData('email', e.target.value)} error={details.errors.email} help="Also used for password reset emails." required />
                        </div>
                        <FormActions dirty={details.isDirty}>
                            <Button loading={details.processing}>Save details</Button>
                        </FormActions>
                    </Card>
                </form>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        password.put('/admin/profile/password', { preserveScroll: true, onSuccess: () => password.reset() });
                    }}
                >
                    <Card title="Change password" description="Other devices will be signed out after you change your password.">
                        <div className="space-y-5">
                            <TextInput label="Current password" type="password" autoComplete="current-password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} error={password.errors.current_password} required />
                            <div className="grid gap-5 sm:grid-cols-2">
                                <TextInput label="New password" type="password" autoComplete="new-password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} error={password.errors.password} help="At least 8 characters, with letters and numbers." required />
                                <TextInput label="Confirm new password" type="password" autoComplete="new-password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} required />
                            </div>
                        </div>
                        <FormActions dirty={password.isDirty}>
                            <Button loading={password.processing}>Change password</Button>
                        </FormActions>
                    </Card>
                </form>
            </div>
        </AdminLayout>
    );
}
