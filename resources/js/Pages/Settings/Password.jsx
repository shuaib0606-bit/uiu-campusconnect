import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';

const FIELDS = [
    ['current_password', 'Current password', 'current-password', null],
    ['password', 'New password', 'new-password', 'Minimum 6 characters'],
    ['password_confirmation', 'Confirm new password', 'new-password', null],
];

const ROWS = [
    ['email', 'Email'],
    ['role', 'Role'],
    ['last_login', 'Last login'],
    ['password_changed', 'Password changed'],
];

export default function SettingsPassword({ forced, security, profileUrl }) {
    const { errors } = usePage().props;
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    const firstError = Object.values(errors ?? {})[0];

    const submit = (e) => {
        e.preventDefault();
        form.put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AppLayout title="Account settings">
            <div className="mx-auto max-w-3xl">
                <div className="surface mb-5 flex flex-wrap items-center justify-between gap-4 p-6">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">
                            Account security
                        </p>
                        <h1 className="mt-1 text-[19px] font-semibold">Account settings</h1>
                        <p className="section-sub mt-1">
                            Manage your password and basic security settings for UIU CampusConnect.
                        </p>
                    </div>
                    <a href={profileUrl} className="btn-secondary">
                        <Icon name="user" className="h-4 w-4" />
                        View profile
                    </a>
                </div>

                {forced && (
                    <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                        You must set a new password before continuing.
                    </div>
                )}

                {firstError && (
                    <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                        {firstError}
                    </div>
                )}

                <div className="grid gap-5 sm:grid-cols-2">
                    <section className="surface p-6">
                        <h2 className="mb-1 text-[15px] font-semibold">Change password</h2>
                        <p className="section-sub mb-4">Use a unique password that you do not use on other websites.</p>

                        <form onSubmit={submit} className="space-y-3">
                            {FIELDS.map(([name, label, autoComplete, placeholder]) => (
                                <label key={name} className="block">
                                    <span className="mb-1 block text-xs font-medium">{label}</span>
                                    <input
                                        type="password"
                                        name={name}
                                        value={form.data[name]}
                                        onChange={(e) => form.setData(name, e.target.value)}
                                        autoComplete={autoComplete}
                                        minLength={name === 'current_password' ? undefined : 6}
                                        placeholder={placeholder ?? undefined}
                                        required
                                        className={`field ${errors?.[name] ? '!border-red-400' : ''}`}
                                    />
                                </label>
                            ))}

                            <button type="submit" className="btn-primary w-full" disabled={form.processing}>
                                <Icon name="check" className="h-4 w-4" />
                                {form.processing ? 'Updating…' : 'Update password'}
                            </button>
                        </form>
                    </section>

                    <aside className="surface p-6">
                        <h2 className="mb-3 text-[15px] font-semibold">Security status</h2>
                        <ul className="space-y-3 text-sm">
                            {ROWS.map(([key, label], i) => (
                                <li
                                    key={key}
                                    className={`flex items-center justify-between gap-3 ${
                                        i < ROWS.length - 1 ? 'border-b border-ink-100 pb-2.5 dark:border-ink-800/70' : ''
                                    }`}
                                >
                                    <span className="font-medium">{label}</span>
                                    <span className="truncate text-ink-500 dark:text-ink-400">{security[key]}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="section-sub mt-4">
                            Forgot-password and email-verification support is prepared, but email delivery should be
                            configured before enabling public reset links.
                        </p>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
