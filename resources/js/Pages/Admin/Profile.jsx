import { useForm } from '@inertiajs/react';
import { Button, Card, FormActions, PageHeader, TextInput } from '@/Components/Admin/ui';
import NotificationsCard from '@/Components/Admin/Notifications';
import TwoFactorCard from '@/Components/Admin/TwoFactor';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Profile({ profile, pendingEmail, twoFactor, recoveryCodes, push }) {
    const details = useForm({ name: profile.name, email: profile.email, current_password: '' });
    const verification = useForm({ code: '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <AdminLayout title="Profile">
            <PageHeader title="Profile" description="Your administrator login details." />
            <div className="grid max-w-3xl gap-6">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        details.put('/admin/profile', { preserveScroll: true, onSuccess: () => details.reset('current_password') });
                    }}
                >
                    <Card title="Account details">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextInput label="Name" value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} error={details.errors.name} required />
                            <TextInput label="Login email" type="email" value={details.data.email} onChange={(e) => details.setData('email', e.target.value)} error={details.errors.email} help="Also used for password reset emails." required />
                        </div>
                        {details.data.email !== profile.email && <TextInput label="Current password" type="password" autoComplete="current-password" value={details.data.current_password} onChange={(e) => details.setData('current_password', e.target.value)} error={details.errors.current_password} required />}
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

                {pendingEmail && <form onSubmit={(e) => { e.preventDefault(); verification.post('/admin/profile/verify-email', { onSuccess: () => verification.reset() }); }}>
                    <Card title="Verify your new email" description="Your login email changes once you enter the code we sent. Until then, keep signing in with your current email.">
                        <div className="space-y-4">
                            <TextInput label={`Code sent to ${pendingEmail}`} autoComplete="one-time-code" inputMode="numeric" value={verification.data.code} onChange={(e) => verification.setData('code', e.target.value)} error={verification.errors.code} required />
                            <Button loading={verification.processing}>Verify email</Button>
                        </div>
                    </Card>
                </form>}

                <NotificationsCard push={push} />

                <TwoFactorCard twoFactor={twoFactor} recoveryCodes={recoveryCodes} />
            </div>
        </AdminLayout>
    );
}
