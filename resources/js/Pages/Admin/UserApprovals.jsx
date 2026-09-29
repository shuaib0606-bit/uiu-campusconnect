import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import UserAvatar from '../../Components/UserAvatar';

const SMALL = '!px-3 !py-1.5 text-xs';

const TABS = [
    ['pending', 'Pending requests'],
    ['active', 'Active users'],
    ['rejected', 'Rejected'],
    ['suspended', 'Suspended'],
    ['all', 'All users'],
];

const COUNTS = [
    ['pending', 'Pending'],
    ['active', 'Active'],
    ['rejected', 'Rejected'],
    ['suspended', 'Suspended'],
];

const STATUS_CHIP = {
    active: '!bg-emerald-500/10 !text-emerald-600 dark:!text-emerald-300',
    pending: '!bg-amber-500/10 !text-amber-600 dark:!text-amber-300',
    suspended: '!bg-red-500/10 !text-red-600 dark:!text-red-300',
    rejected: '!bg-red-500/10 !text-red-600 dark:!text-red-300',
};

function Fact({ label, children }) {
    return (
        <div className="rounded-lg bg-ink-900/[0.03] p-2 dark:bg-white/[0.04]">
            <span className="block font-semibold text-ink-400">{label}</span>
            {children}
        </div>
    );
}

function ReviewCard({ req, tab }) {
    const form = useForm({ admin_note: '', tab, decision: '' });

    const decide = (decision) => {
        form.transform((data) => ({ ...data, decision }));
        form.post(req.review_url, { preserveScroll: true });
    };

    return (
        <article className="surface grid gap-4 p-5 sm:grid-cols-[1fr_260px]">
            <div className="flex gap-3">
                <UserAvatar user={req} size="h-12 w-12" text="text-sm" />
                <div className="min-w-0">
                    <h2 className="flex flex-wrap items-center gap-2 text-[15px] font-semibold">
                        {req.full_name}
                        <span className={`chip ${STATUS_CHIP[req.status] ?? ''}`}>{req.status_label}</span>
                    </h2>
                    <p className="section-sub">{req.details}</p>
                    <p className="mt-1 text-sm">{req.email}</p>

                    <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <Fact label="Requested">{req.requested_at}</Fact>
                        <Fact label="Reviewed by">{req.reviewed_by}</Fact>
                        <Fact label="Review note">{req.review_note}</Fact>
                        <Fact label="Verified">{req.verified}</Fact>
                    </div>
                </div>
            </div>

            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap gap-2">
                    <a href={req.profile_url} target="_blank" rel="noreferrer" className={`btn-secondary ${SMALL}`}>View profile</a>
                    {req.is_student && (
                        req.id_card_url ? (
                            <a href={req.id_card_url} target="_blank" rel="noreferrer" className={`btn-secondary ${SMALL}`}>View UIU ID</a>
                        ) : (
                            <span className="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">No UIU ID uploaded</span>
                        )
                    )}
                </div>

                <textarea
                    value={form.data.admin_note}
                    onChange={(e) => form.setData('admin_note', e.target.value)}
                    placeholder="Optional admin note"
                    rows={2}
                    className="field mb-2"
                />

                <div className="flex flex-wrap gap-2">
                    {req.can.approve && (
                        <button type="button" onClick={() => decide('approve')} className={`btn-primary ${SMALL}`}>Approve</button>
                    )}
                    {req.can.reject && (
                        <button type="button" onClick={() => decide('reject')} className={`btn-secondary ${SMALL}`}>Reject</button>
                    )}
                    {req.can.suspend && (
                        <button type="button" onClick={() => decide('suspend')} className={`btn-danger ${SMALL}`}>Suspend</button>
                    )}
                    {req.can.reactivate && (
                        <button type="button" onClick={() => decide('reactivate')} className={`btn-primary ${SMALL}`}>Reactivate</button>
                    )}
                </div>
            </div>
        </article>
    );
}

export default function UserApprovals({ requests, statusCounts, tab, filters }) {
    const [q, setQ] = useState(filters.q);

    useEffect(() => setQ(filters.q), [filters.q]);

    const go = (params) => router.get('/user-approvals', params, { preserveState: true, replace: true });

    return (
        <AppLayout title="User Approvals">
            <div className="mx-auto max-w-4xl">
                <div className="surface mb-5 flex flex-wrap items-center justify-between gap-6 bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-white">
                    <div className="max-w-md">
                        <p className="text-xs font-semibold uppercase tracking-wide text-white/80">Master admin</p>
                        <h1 className="mt-1 text-[22px] font-semibold">User Approval Center</h1>
                        <p className="mt-1 text-sm text-white/85">
                            Approve every new signup before the user becomes valid. Student requests should include a UIU ID
                            card for verification.
                        </p>
                    </div>
                    <div className="grid shrink-0 grid-cols-4 gap-2">
                        {COUNTS.map(([key, label]) => (
                            <div key={key} className="rounded-2xl bg-white/15 px-3 py-2.5 text-center">
                                <strong className="block text-lg">{statusCounts[key]}</strong>
                                <span className="text-xs text-white/80">{label}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="surface mb-5 flex flex-wrap items-center justify-between gap-3 p-3">
                    <nav className="flex flex-wrap gap-1.5">
                        {TABS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => go({ tab: key, q: filters.q })}
                                className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                                    tab === key ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </nav>

                    <form onSubmit={(e) => { e.preventDefault(); go({ tab, q }); }} className="flex gap-2">
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search name, email, ID, department"
                            className="field w-64"
                        />
                    </form>
                </div>

                {requests.length === 0 ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="user-check" className="h-6 w-6" /></span>
                        <h3>No records found</h3>
                        <p>There are no users in this approval category right now.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {requests.map((req) => <ReviewCard key={req.id} req={req} tab={tab} />)}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
