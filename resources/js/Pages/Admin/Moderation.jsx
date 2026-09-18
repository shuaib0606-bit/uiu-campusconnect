import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';

const SMALL = '!px-3 !py-1.5 text-xs';

const TABS = [
    ['open', 'Open reports'],
    ['posts', 'Posts'],
    ['comments', 'Comments'],
    ['profiles', 'Profiles'],
    ['resolved', 'Resolved'],
];

const COUNTS = [
    ['posts', 'Posts'],
    ['comments', 'Comments'],
    ['profiles', 'Profiles'],
];

function chipFor(report) {
    if (report.is_open) return '!bg-amber-500/10 !text-amber-700 dark:!text-amber-300';
    if (report.status === 'resolved') return '!bg-emerald-500/10 !text-emerald-600 dark:!text-emerald-300';
    return '!bg-ink-500/10 !text-ink-600 dark:!text-ink-300';
}

function ReportCard({ report, tab }) {
    const form = useForm({ moderator_note: '', tab, decision: '' });

    const decide = (decision) => {
        form.transform((data) => ({ ...data, decision }));
        form.post(report.review_url, { preserveScroll: true });
    };

    return (
        <article className="surface p-5">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className={`chip ${chipFor(report)}`}>{report.status_label}</span>
                <span className="section-sub">{report.created_at}</span>
            </div>

            <p className="mt-2 text-sm">
                <strong>Reported by</strong> {report.reporter}
                {report.against ? (
                    <>
                        {' · '}
                        <strong>Against:</strong>{' '}
                        <a href={report.against.url} target="_blank" rel="noreferrer" className="hover:underline">
                            {report.against.name}
                        </a>
                    </>
                ) : (
                    <>
                        {' · '}
                        <strong>Reason:</strong> {report.reason}
                    </>
                )}
            </p>

            {report.against && (
                <p className="mt-1 text-sm"><strong>Reason:</strong> {report.reason}</p>
            )}

            {report.quote && (
                report.against ? (
                    <p className="mt-1 text-sm text-ink-600 dark:text-ink-300">{report.quote}</p>
                ) : (
                    <p className="mt-2 rounded-xl bg-ink-900/[0.03] p-3 text-sm text-ink-600 dark:bg-white/[0.04] dark:text-ink-300">
                        {report.quote}
                    </p>
                )
            )}

            {report.missing && <p className="mt-2 text-sm text-ink-400">{report.missing}</p>}

            {report.is_open ? (
                <div className="mt-3">
                    <textarea
                        value={form.data.moderator_note}
                        onChange={(e) => form.setData('moderator_note', e.target.value)}
                        placeholder="Moderator note (optional)"
                        rows={2}
                        className="field mb-2"
                    />
                    <div className="flex flex-wrap gap-2">
                        <button type="button" onClick={() => decide('action')} className={`btn-danger ${SMALL}`} disabled={form.processing}>
                            {report.action_label}
                        </button>
                        <button type="button" onClick={() => decide('dismiss')} className={`btn-secondary ${SMALL}`} disabled={form.processing}>
                            Dismiss report
                        </button>
                    </div>
                </div>
            ) : (
                report.moderator_note && <p className="section-sub mt-2">Moderator note: {report.moderator_note}</p>
            )}
        </article>
    );
}

function Section({ heading, reports, tab, last = false }) {
    if (reports.length === 0) return null;

    return (
        <>
            <h2 className="mb-2 mt-2 text-sm font-semibold text-ink-500 dark:text-ink-400">{heading}</h2>
            <div className={`space-y-3 ${last ? '' : 'mb-5'}`}>
                {reports.map((report) => <ReportCard key={report.id} report={report} tab={tab} />)}
            </div>
        </>
    );
}

export default function Moderation({ posts, comments, profiles, counts, tab }) {
    const empty = posts.length === 0 && comments.length === 0 && profiles.length === 0;
    const totals = { posts: counts.posts, comments: counts.comments, profiles: counts.profiles };

    return (
        <AppLayout title="Moderation Center">
            <div className="mx-auto max-w-4xl">
                <div className="surface mb-5 flex flex-wrap items-center justify-between gap-6 bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-white">
                    <div className="max-w-md">
                        <p className="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-white/80">
                            <Icon name="shield" className="h-4 w-4" />
                            Safety
                        </p>
                        <h1 className="mt-1 text-[22px] font-semibold">Moderation Center</h1>
                        <p className="mt-1 text-sm text-white/85">
                            Review post, comment, and profile reports. Take action to remove content or suspend the
                            reported user, or dismiss the report.
                        </p>
                    </div>
                    <div className="grid shrink-0 grid-cols-3 gap-2">
                        {COUNTS.map(([key, label]) => (
                            <div key={key} className="rounded-2xl bg-white/15 px-3 py-2.5 text-center">
                                <strong className="block text-lg">{totals[key]}</strong>
                                <span className="text-xs text-white/80">{label}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="surface-flat mb-5 flex flex-wrap gap-1.5 p-2">
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => router.get('/moderation', { tab: key }, { preserveState: true, replace: true })}
                            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                                tab === key ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {empty && (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="check-circle" className="h-6 w-6" /></span>
                        <h3>Nothing to review</h3>
                        <p>There are no reports in this category right now.</p>
                    </div>
                )}

                <Section heading="Post reports" reports={posts} tab={tab} />
                <Section heading="Comment reports" reports={comments} tab={tab} />
                <Section heading="Profile reports" reports={profiles} tab={tab} last />
            </div>
        </AppLayout>
    );
}
