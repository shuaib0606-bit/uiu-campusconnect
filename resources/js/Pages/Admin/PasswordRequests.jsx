import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import Pagination from '../../Components/Pagination';

const SMALL = '!px-3 !py-1.5 text-xs';

const TABS = [
    ['pending', 'Pending'],
    ['approved', 'Approved'],
    ['rejected', 'Rejected'],
    ['all', 'All'],
];

const STATUS_CHIP = {
    pending: '!bg-primary-500/10 !text-primary-700 dark:!text-primary-300',
    approved: '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-300',
    rejected: '!bg-red-500/10 !text-red-600 dark:!text-red-300',
};

function RequestCard({ req }) {
    const form = useForm({ review_note: '', decision: '' });
    // Approval kills the current password immediately, so it asks first. An
    // inline step rather than a browser dialog, which blocks the whole page.
    const [confirming, setConfirming] = useState(false);

    const decide = (decision) => {
        form.transform((data) => ({ ...data, decision }));
        form.post(req.review_url, { preserveScroll: true });
    };

    return (
        <article className="surface p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-1.5">
                        {req.is_unmatched ? (
                            <span className="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">No matching account</span>
                        ) : (
                            <span className="chip">{req.role_label}</span>
                        )}
                        <span className={`chip ${STATUS_CHIP[req.status] ?? ''}`}>{req.status_label}</span>
                    </div>

                    <h2 className="mt-1.5 text-[15px] font-semibold">{req.full_name}</h2>

                    <p className="section-sub mt-0.5">
                        Submitted <span className="font-medium">{req.submitted_identifier}</span>
                        {' · '}{req.submitted_ago}
                        {req.ip_address && ` · from ${req.ip_address}`}
                    </p>

                    {req.identity && <p className="section-sub mt-0.5">{req.identity}</p>}

                    {req.note && (
                        <p className="mt-2 rounded-xl bg-ink-900/[0.03] px-3.5 py-2 text-sm dark:bg-white/[0.04]">{req.note}</p>
                    )}

                    {req.reviewed_line && <p className="section-sub mt-2">{req.reviewed_line}</p>}
                </div>

                {req.is_pending && (
                    <div className="shrink-0 space-y-2 sm:w-56">
                        <input
                            value={form.data.review_note}
                            onChange={(e) => form.setData('review_note', e.target.value)}
                            maxLength={500}
                            placeholder="Note (optional)"
                            className="field !py-1.5 text-xs"
                        />

                        {confirming ? (
                            <div className="rounded-xl bg-amber-500/10 p-2.5">
                                <p className="text-xs text-amber-800 dark:text-amber-200">
                                    Issue a new temporary password for {req.full_name}? Their current password stops
                                    working immediately.
                                </p>
                                <div className="mt-2 flex gap-2">
                                    <button type="button" onClick={() => decide('approve')} className={`btn-primary flex-1 ${SMALL}`} disabled={form.processing}>
                                        Yes, issue it
                                    </button>
                                    <button type="button" onClick={() => setConfirming(false)} className={`btn-ghost flex-1 ${SMALL}`}>
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div className="flex gap-2">
                                {!req.is_unmatched && (
                                    <button type="button" onClick={() => setConfirming(true)} className={`btn-primary flex-1 ${SMALL}`}>
                                        Approve
                                    </button>
                                )}
                                <button type="button" onClick={() => decide('reject')} className={`btn-secondary flex-1 ${SMALL}`} disabled={form.processing}>
                                    Reject
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </article>
    );
}

export default function PasswordRequests({ requests, tab, counts, issued, issuedFor }) {
    return (
        <AppLayout title="Password recovery">
            <div className="mx-auto max-w-4xl">
                <div className="hero-panel mb-5 p-7">
                    <Icon name="lock" className="hero-icon" />
                    <div className="relative z-10">
                        <p className="eyebrow"><Icon name="shield" className="h-3.5 w-3.5" />Admin only</p>
                        <h1 className="hero-title mt-2">Password recovery requests</h1>
                        <p className="section-sub mt-2 max-w-lg">
                            Verify who the person is before approving. Approving issues a new temporary password and
                            forces them to change it at next sign-in.
                        </p>
                    </div>
                </div>

                {issued && (
                    <div className="surface mb-5 border-l-4 border-l-emerald-500 p-5">
                        <h2 className="inline-flex items-center gap-1.5 text-[15px] font-semibold">
                            <Icon name="check-circle" className="h-4 w-4 text-emerald-500" />
                            Temporary password for {issuedFor}
                        </h2>
                        <p className="section-sub mt-1">
                            Give this to them directly. It is shown once and cannot be recovered from this screen again.
                        </p>
                        <p className="mt-3 select-all rounded-xl bg-ink-900/[0.05] px-4 py-3 font-mono text-lg tracking-wider dark:bg-white/[0.07]">
                            {issued}
                        </p>
                        <p className="mt-2 text-xs text-ink-500 dark:text-ink-400">
                            They will be required to set their own password before they can use the site.
                        </p>
                    </div>
                )}

                <div className="surface mb-5 flex flex-wrap gap-1.5 p-3">
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => router.get('/password-requests', { tab: key }, { preserveState: true, replace: true })}
                            className={`filter-pill ${tab === key ? 'is-active' : ''}`}
                        >
                            {label}
                            {key !== 'all' && counts[key] > 0 && (
                                <span className="ml-1 text-xs opacity-70">{counts[key]}</span>
                            )}
                        </button>
                    ))}
                </div>

                {requests.data.length === 0 ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="lock" className="h-6 w-6" /></span>
                        <h3>Nothing here</h3>
                        <p>No {tab === 'all' ? '' : tab} recovery requests.</p>
                    </div>
                ) : (
                    <>
                        <div className="space-y-3">
                            {requests.data.map((req) => <RequestCard key={req.id} req={req} />)}
                        </div>
                        <div className="mt-6"><Pagination meta={requests} /></div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
