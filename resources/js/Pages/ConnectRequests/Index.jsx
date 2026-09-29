import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import UserAvatar from '../../Components/UserAvatar';

const SMALL = '!px-3 !py-1.5 text-xs';

const PURPOSES = [
    'Academic support',
    'Course advising',
    'Project supervision',
    'Club or event work',
    'Administrative help',
    'Other',
];

const STATUS_CHIP = {
    pending: '!bg-amber-500/10 !text-amber-700 dark:!text-amber-300',
    reviewing: '!bg-amber-500/10 !text-amber-700 dark:!text-amber-300',
    approved: '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-300',
    rejected: '!bg-red-500/10 !text-red-700 dark:!text-red-300',
    cancelled: '!bg-red-500/10 !text-red-700 dark:!text-red-300',
};

const capitalise = (word) => word.charAt(0).toUpperCase() + word.slice(1);

function Banner({ tone, children }) {
    const edge = { good: 'border-l-emerald-500', warn: 'border-l-amber-500', bad: 'border-l-red-500' }[tone];

    return <div className={`surface-flat mt-4 border-l-4 px-4 py-3 text-sm ${edge}`}>{children}</div>;
}

function ApplyPanel({ target }) {
    const form = useForm({ purpose: PURPOSES[0], course_department: '', message: '' });

    const submit = (event) => {
        event.preventDefault();
        form.post(target.apply_url, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <div className="surface mb-5 p-6">
            <div className="flex items-center gap-3">
                <UserAvatar user={target} size="h-12 w-12" text="text-sm" />
                <div>
                    <h2 className="text-[15px] font-semibold">Apply to connect with {target.full_name}</h2>
                    <p className="section-sub">{target.subtitle}</p>
                </div>
            </div>

            {target.connected ? (
                <>
                    <Banner tone="good">You are already approved to connect with this user.</Banner>
                    <a href={target.message_url} className="btn-primary mt-3 inline-flex">Open message</a>
                </>
            ) : target.pending ? (
                <Banner tone="warn">Your application is {target.status_label}.</Banner>
            ) : target.approved ? (
                <Banner tone="good">This application was approved.</Banner>
            ) : (
                <>
                    {target.blocked_reason && <Banner tone="bad">{target.blocked_reason}</Banner>}

                    <form onSubmit={submit} className="mt-4 space-y-3">
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">Purpose</span>
                            <select
                                value={form.data.purpose}
                                onChange={(e) => form.setData('purpose', e.target.value)}
                                className="field"
                            >
                                {PURPOSES.map((p) => <option key={p} value={p}>{p}</option>)}
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">Course / Department</span>
                            <input
                                value={form.data.course_department}
                                onChange={(e) => form.setData('course_department', e.target.value)}
                                placeholder="Example: CSE, thesis, project, course code"
                                className="field"
                            />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">Reason for contact</span>
                            <textarea
                                value={form.data.message}
                                onChange={(e) => form.setData('message', e.target.value)}
                                rows={4}
                                required
                                placeholder="Explain why admin should approve this connection..."
                                className="field"
                            />
                        </label>

                        {Object.keys(form.errors).length > 0 && (
                            <p className="text-sm text-red-600 dark:text-red-300">{Object.values(form.errors)[0]}</p>
                        )}

                        <button type="submit" disabled={form.processing} className="btn-primary">
                            {form.processing ? 'Sending…' : 'Send application to admin'}
                        </button>
                    </form>
                </>
            )}
        </div>
    );
}

function ReviewPanel({ app, tab }) {
    const form = useForm({ admin_note: app.admin_note ?? '', tab, application_action: '' });
    const [confirmSuspend, setConfirmSuspend] = useState(false);

    const decide = (action) => {
        form.transform((data) => ({ ...data, application_action: action }));
        form.post(app.actions.review, { preserveScroll: true });
    };

    return (
        <>
            <div className="mt-4 space-y-2 border-t border-ink-100 pt-4 dark:border-ink-800/70">
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-ink-500">Admin note</span>
                    <textarea
                        value={form.data.admin_note}
                        onChange={(e) => form.setData('admin_note', e.target.value)}
                        rows={2}
                        placeholder="Add reviewer note or decision reason..."
                        className="field !py-2 text-sm"
                    />
                </label>

                {app.can_review && (
                    <div className="flex flex-wrap gap-2">
                        <button type="button" onClick={() => decide('reviewing')} className={`btn-secondary ${SMALL}`}>Review</button>
                        <button type="button" onClick={() => decide('approve')} className={`btn-primary ${SMALL}`}>Approve</button>
                        <button type="button" onClick={() => decide('reject')} className={`btn-secondary ${SMALL}`}>Reject</button>
                    </div>
                )}
            </div>

            {/* Two taps rather than a browser confirm box, which blocks the page. */}
            <div className="mt-2 flex items-center gap-2">
                {confirmSuspend ? (
                    <>
                        <span className="text-xs text-ink-500 dark:text-ink-400">Suspend this student account?</span>
                        <button
                            type="button"
                            onClick={() => router.post(app.actions.suspend, { tab }, { preserveScroll: true })}
                            className={`btn-danger ${SMALL}`}
                        >
                            Yes, suspend
                        </button>
                        <button type="button" onClick={() => setConfirmSuspend(false)} className={`btn-ghost ${SMALL}`}>Keep</button>
                    </>
                ) : (
                    <button type="button" onClick={() => setConfirmSuspend(true)} className={`btn-danger ${SMALL}`}>
                        Suspend student
                    </button>
                )}
            </div>
        </>
    );
}

function ApplicationCard({ app, isAdmin, tab }) {
    return (
        <article className="surface p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-1.5 text-[15px] font-semibold">
                        <a href={app.student.url} className="hover:underline">{app.student.full_name}</a>
                        <span className="text-ink-400">&rarr;</span>
                        <a href={app.target.url} className="hover:underline">{app.target.full_name}</a>
                    </div>
                    <p className="section-sub mt-0.5">
                        {app.student.email}
                        {app.student.department ? ` · ${app.student.department}` : ''}
                        {` · Target: ${app.target.role}`}
                    </p>
                </div>
                <span className={`chip shrink-0 ${STATUS_CHIP[app.status] ?? ''}`}>{app.status_label}</span>
            </div>

            <div className="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div><span className="block text-xs font-semibold uppercase text-ink-400">Purpose</span>{app.purpose}</div>
                <div><span className="block text-xs font-semibold uppercase text-ink-400">Course/Dept</span>{app.course_department}</div>
                <div><span className="block text-xs font-semibold uppercase text-ink-400">Submitted</span>{app.submitted_at}</div>
                <div><span className="block text-xs font-semibold uppercase text-ink-400">Last update</span>{app.updated_at}</div>
            </div>

            {app.message && (
                <div className="mt-3 rounded-xl bg-ink-900/[0.03] p-3 text-sm dark:bg-white/[0.04]">
                    <span className="mb-1 block text-xs font-semibold uppercase text-ink-400">Student message</span>
                    {app.message}
                </div>
            )}

            {app.admin_note && (
                <div className="mt-3 rounded-xl bg-primary-500/5 p-3 text-sm">
                    <span className="mb-1 block text-xs font-semibold uppercase text-primary-600 dark:text-primary-300">Admin note</span>
                    {app.admin_note}
                </div>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <a href={app.student.url} className={`btn-secondary ${SMALL}`}>View student</a>
                <a href={app.target.url} className={`btn-secondary ${SMALL}`}>View faculty/official</a>
                {app.can_message && <a href={app.message_url} className={`btn-primary ${SMALL}`}>Message</a>}
            </div>

            {isAdmin ? (
                <ReviewPanel app={app} tab={tab} />
            ) : app.can_cancel ? (
                <button
                    type="button"
                    onClick={() => router.post(app.actions.cancel, {}, { preserveScroll: true })}
                    className={`btn-secondary mt-3 ${SMALL}`}
                >
                    Cancel application
                </button>
            ) : null}
        </article>
    );
}

const ROLE_CHIP = {
    pending: '!bg-amber-500/10 !text-amber-700 dark:!text-amber-300',
    approved: '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-300',
    rejected: '!bg-red-500/10 !text-red-600 dark:!text-red-300',
    cancelled: '',
};

/**
 * A club officer putting somebody forward for a designation. The moderator is
 * the only person who can answer it; everybody else on this card is watching.
 */
function ClubRoleCard({ req }) {
    const line = {
        moderator: `${req.nominator} asked to make ${req.nominee} ${req.club_role}.`,
        nominator: `You asked to make ${req.nominee} ${req.club_role}.`,
        nominee: `${req.nominator} put you forward as ${req.club_role}.`,
    }[req.viewer];

    return (
        <article className="surface p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className={`chip ${ROLE_CHIP[req.status] ?? ''}`}>{req.status_label}</span>
                        <a href={req.club_url} className="chip hover:underline">{req.club}</a>
                    </div>
                    <p className="mt-2 text-sm font-semibold">{line}</p>
                    <p className="section-sub">{req.submitted_at}</p>
                    {req.reason && <p className="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{req.reason}</p>}
                    {req.decision_note && (
                        <p className="section-sub mt-1.5">
                            {req.decided_by ? `${req.decided_by}: ` : ''}{req.decision_note}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 flex-wrap gap-2">
                    {req.can_review && (
                        <>
                            <form method="POST" action={req.review_url}>
                                <input type="hidden" name="_token" value={csrf()} />
                                <input type="hidden" name="decision" value="approve" />
                                <button type="submit" className="btn-primary !px-3 !py-1.5 text-xs">Approve</button>
                            </form>
                            <form method="POST" action={req.review_url}>
                                <input type="hidden" name="_token" value={csrf()} />
                                <input type="hidden" name="decision" value="reject" />
                                <button type="submit" className="btn-secondary !px-3 !py-1.5 text-xs">Decline</button>
                            </form>
                        </>
                    )}
                    {req.can_cancel && (
                        <form method="POST" action={req.cancel_url}>
                            <input type="hidden" name="_token" value={csrf()} />
                            <button type="submit" className="btn-ghost !px-3 !py-1.5 text-xs">Withdraw</button>
                        </form>
                    )}
                </div>
            </div>
        </article>
    );
}

/**
 * A whole student panel changing hands. Shown as a slate because that is what
 * is being agreed: approving it drops every designation the club has today.
 */
function PanelChangeCard({ change }) {
    return (
        <article className="surface p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className={`chip ${ROLE_CHIP[change.status] ?? ''}`}>{change.status_label}</span>
                        <a href={change.club_url} className="chip hover:underline">{change.club}</a>
                    </div>

                    <p className="mt-2 text-sm font-semibold">
                        {change.proposer} proposed a new panel of {change.seats.length}
                    </p>
                    <p className="section-sub">{change.submitted_at}</p>
                    {change.note && <p className="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{change.note}</p>}

                    <div className="mt-2 grid gap-1 sm:grid-cols-2">
                        {change.seats.map((seat) => (
                            <div key={`${seat.name}-${seat.role}`} className="flex items-center gap-2 rounded-lg bg-ink-900/[0.03] px-2.5 py-1 text-xs dark:bg-white/[0.05]">
                                <span className="min-w-0 truncate">{seat.name}</span>
                                <span className="ml-auto shrink-0 font-semibold">{seat.role}</span>
                            </div>
                        ))}
                    </div>

                    {change.can_review && (
                        <p className="section-sub mt-2">
                            Approving drops every designation the club holds today and gives them to these people.
                            Those replaced stay members.
                        </p>
                    )}

                    {change.status === 'approved' && change.replaced_count > 0 && (
                        <p className="section-sub mt-2">
                            {change.replaced_count} previous designation{change.replaced_count === 1 ? '' : 's'} were
                            cleared. Those members kept their place in the club.
                        </p>
                    )}

                    {change.decision_note && (
                        <p className="section-sub mt-1.5">
                            {change.decided_by ? `${change.decided_by}: ` : ''}{change.decision_note}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 flex-wrap gap-2">
                    {change.can_review && (
                        <>
                            <form method="POST" action={change.review_url}>
                                <input type="hidden" name="_token" value={csrf()} />
                                <input type="hidden" name="decision" value="approve" />
                                <button type="submit" className="btn-primary !px-3 !py-1.5 text-xs">Approve</button>
                            </form>
                            <form method="POST" action={change.review_url}>
                                <input type="hidden" name="_token" value={csrf()} />
                                <input type="hidden" name="decision" value="reject" />
                                <button type="submit" className="btn-secondary !px-3 !py-1.5 text-xs">Decline</button>
                            </form>
                        </>
                    )}
                    {change.can_cancel && (
                        <form method="POST" action={change.cancel_url}>
                            <input type="hidden" name="_token" value={csrf()} />
                            <button type="submit" className="btn-ghost !px-3 !py-1.5 text-xs">Withdraw</button>
                        </form>
                    )}
                </div>
            </div>
        </article>
    );
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

const INVITE_CHIP = {
    pending: '!bg-primary-500/10 !text-primary-700 dark:!text-primary-300',
    accepted: '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-300',
    declined: '',
};

/**
 * An invitation to join a group. Everything needed to decide is on the card:
 * what the group is, who invited you, who runs it and how posting works.
 * Nothing happens until you accept - you are not a member before that.
 */
function GroupInviteCard({ invite, highlighted }) {
    const { group, inviter } = invite;
    const details = [
        ['Members', group.members],
        ['Owner', group.owner],
        ['Who can post', group.posting],
        ['Visibility', group.privacy],
        ['Department', group.department],
    ].filter(([, value]) => value !== null && value !== undefined && value !== '');

    return (
        <article
            id={`invite-${invite.id}`}
            className={`surface scroll-mt-24 p-5 transition ${highlighted ? 'ring-2 ring-primary-500' : ''}`}
        >
            <div className="flex flex-wrap items-center gap-1.5">
                <span className={`chip ${INVITE_CHIP[invite.status] ?? ''}`}>
                    {invite.pending ? 'Group invitation' : invite.status_label}
                </span>
                <span className="chip">{group.type}</span>
                {group.privacy === 'Members only' && (
                    <span className="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">
                        <Icon name="lock" className="h-3 w-3" />Private
                    </span>
                )}
            </div>

            <h3 className="mt-2 text-[17px] font-semibold tracking-tight">
                <a href={group.url} className="hover:underline">{group.name}</a>
            </h3>

            {inviter && (
                <div className="mt-2 flex items-center gap-2.5">
                    <UserAvatar url={inviter.avatar_url} initials={inviter.initials} size="h-8 w-8" text="text-[11px]" />
                    <p className="min-w-0 text-sm">
                        <a href={inviter.url} className="font-semibold hover:underline">{inviter.name}</a>
                        <span className="text-ink-500 dark:text-ink-400"> invited you · {invite.invited_ago}</span>
                        {inviter.subtitle && <span className="block truncate text-xs text-ink-400">{inviter.subtitle}</span>}
                    </p>
                </div>
            )}

            {group.description && (
                <p className="mt-3 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{group.description}</p>
            )}

            <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 rounded-xl bg-ink-900/[0.03] p-3 text-sm dark:bg-white/[0.04] sm:grid-cols-3">
                {details.map(([label, value]) => (
                    <div key={label} className="min-w-0">
                        <dt className="text-xs text-ink-500 dark:text-ink-400">{label}</dt>
                        <dd className="truncate font-medium">{value}</dd>
                    </div>
                ))}
            </dl>

            {invite.pending ? (
                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <form method="POST" action={invite.accept_url}>
                        <input type="hidden" name="_token" value={csrf()} />
                        <button type="submit" className="btn-primary">
                            <Icon name="check" className="h-4 w-4" />Accept and join
                        </button>
                    </form>
                    <form method="POST" action={invite.decline_url}>
                        <input type="hidden" name="_token" value={csrf()} />
                        <button type="submit" className="btn-secondary">Decline</button>
                    </form>
                    <span className="section-sub">Sent {invite.invited_at}</span>
                </div>
            ) : (
                <p className="section-sub mt-3">
                    {invite.status_label}{invite.answered_at ? ` on ${invite.answered_at}` : ''}.
                    {invite.status === 'accepted' && (
                        <> <a href={group.url} className="font-semibold text-primary-600 hover:underline dark:text-primary-300">Open the group</a></>
                    )}
                </p>
            )}
        </article>
    );
}

export default function ConnectRequestsIndex({ applications, groupInvites = [], clubRoleRequests = [], clubPanelChanges = [], tab, tabs, role, target, missingTarget }) {
    const isAdmin = role === 'admin';

    // A notification links to /connect-requests#invite-12: bring that card
    // into view and mark it.
    const [highlighted, setHighlighted] = useState(null);
    useEffect(() => {
        const match = window.location.hash.match(/^#invite-(\d+)$/);
        if (!match) return;
        const id = Number(match[1]);
        setHighlighted(id);
        requestAnimationFrame(() => document.getElementById(`invite-${id}`)?.scrollIntoView({ block: 'center' }));
    }, []);

    const waitingInvites = groupInvites.filter((invite) => invite.pending);
    const answeredInvites = groupInvites.filter((invite) => !invite.pending);

    const heading = isAdmin ? `${capitalise(tab)} applications` : 'records';
    const ownLabel = role === 'student' ? 'My Applications' : 'Approved Students';

    return (
        <AppLayout title="Connection Applications">
            <div className="mx-auto max-w-3xl">
                <div className="surface mb-5 flex flex-wrap items-center justify-between gap-4 p-6">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">
                            Admin-handled access
                        </p>
                        <h1 className="mt-1 text-[22px] font-semibold tracking-tight">Connection Applications</h1>
                        <p className="section-sub mt-1 max-w-lg">
                            Students apply here to contact faculty or university officials. Admin reviews every application
                            first, so faculty and officials don&apos;t receive requests directly.
                        </p>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-semibold">{applications.length}</div>
                        <div className="section-sub">{heading}</div>
                    </div>
                </div>

                {isAdmin ? (
                    <div className="surface-flat mb-5 flex gap-1 p-1">
                        {tabs.map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => router.get('/connect-requests', { tab: key }, { preserveState: true, replace: true })}
                                className={`flex-1 rounded-xl px-3 py-2 text-center text-sm font-medium ${
                                    tab === key ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300'
                                }`}
                            >
                                {capitalise(key)}
                            </button>
                        ))}
                    </div>
                ) : (
                    <div className="surface-flat mb-5 p-1">
                        <span className="block rounded-xl bg-primary-500 px-3 py-2 text-center text-sm font-medium text-white">
                            {ownLabel}
                        </span>
                    </div>
                )}

                {target && <ApplyPanel target={target} />}

                {missingTarget && (
                    <div className="surface-flat mb-5 border-l-4 border-l-red-500 px-4 py-3 text-sm">
                        Faculty/official account not found.
                    </div>
                )}

                {waitingInvites.length > 0 && (
                    <div className="mb-6">
                        <h2 className="mb-2 text-sm font-semibold text-ink-500 dark:text-ink-400">
                            Group invitations ({waitingInvites.length})
                        </h2>
                        <div className="space-y-4">
                            {waitingInvites.map((invite) => (
                                <GroupInviteCard key={invite.id} invite={invite} highlighted={highlighted === invite.id} />
                            ))}
                        </div>
                    </div>
                )}

                {clubPanelChanges.length > 0 && (
                    <div className="mb-6">
                        <h2 className="mb-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Panel handovers</h2>
                        <div className="space-y-4">
                            {clubPanelChanges.map((change) => <PanelChangeCard key={change.id} change={change} />)}
                        </div>
                    </div>
                )}

                {clubRoleRequests.length > 0 && (
                    <div className="mb-6">
                        <h2 className="mb-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Club designations</h2>
                        <div className="space-y-4">
                            {clubRoleRequests.map((req) => <ClubRoleCard key={req.id} req={req} />)}
                        </div>
                    </div>
                )}

                {answeredInvites.length > 0 && (
                    <details className="mb-6" open={answeredInvites.some((invite) => invite.id === highlighted)}>
                        <summary className="mb-2 cursor-pointer text-sm font-semibold text-ink-500 dark:text-ink-400">
                            Past group invitations ({answeredInvites.length})
                        </summary>
                        <div className="space-y-4">
                            {answeredInvites.map((invite) => (
                                <GroupInviteCard key={invite.id} invite={invite} highlighted={highlighted === invite.id} />
                            ))}
                        </div>
                    </details>
                )}

                <div className="space-y-4">
                    {applications.length === 0 && (groupInvites.length > 0 || clubRoleRequests.length > 0 || clubPanelChanges.length > 0) ? (
                        <p className="section-sub px-1">No connection applications.</p>
                    ) : applications.length === 0 ? (
                        <div className="empty-state">
                            <span className="empty-icon"><Icon name="users" className="h-6 w-6" /></span>
                            <h3>No applications found</h3>
                            <p>
                                {isAdmin
                                    ? 'There are no applications in this tab yet.'
                                    : 'Your connection application history will appear here.'}
                            </p>
                        </div>
                    ) : (
                        applications.map((app) => (
                            <ApplicationCard key={app.id} app={app} isAdmin={isAdmin} tab={tab} />
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
