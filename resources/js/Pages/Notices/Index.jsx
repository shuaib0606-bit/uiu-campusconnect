import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import Pagination from '../../Components/Pagination';

const PRIORITIES = [['normal', 'Normal'], ['high', 'High'], ['urgent', 'Urgent']];
const AUDIENCES = [['all', 'Everyone'], ['students', 'Students'], ['faculties', 'Faculty'], ['officials', 'Officials']];

/** Empty filters are the default, so they do not belong in the address bar. */
function clean(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== '' && v !== false && v !== null && v !== undefined)
    );
}

function PublishForm({ types }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: '',
        notice_type: 'exam',
        body: '',
        priority: 'normal',
        audience: 'all',
        department: '',
        batch: '',
        semester: '',
        is_urgent: false,
        attachments: [],
    });

    const field = (name) => ({
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        className: 'field',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/notices', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { form.reset(); setOpen(false); },
        });
    };

    return (
        <div className="surface mb-5 p-6">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="inline-flex items-center gap-1.5 text-[15px] font-semibold"
            >
                <Icon name="edit" className="h-4 w-4" />
                Publish a notice
            </button>

            {open && (
                <form onSubmit={submit} className="mt-4 space-y-3">
                    <div className="grid gap-2 sm:grid-cols-2">
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium">Title</span>
                            <input {...field('title')} required />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium">Type</span>
                            <select {...field('notice_type')}>
                                {Object.entries(types).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                        </label>
                    </div>

                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Notice details</span>
                        <textarea {...field('body')} rows={4} required />
                    </label>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium">Priority</span>
                            <select {...field('priority')}>
                                {PRIORITIES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium">Audience</span>
                            <select {...field('audience')}>
                                {AUDIENCES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </label>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-3">
                        <input {...field('department')} placeholder="Department (optional)" />
                        <input {...field('batch')} placeholder="Batch / year" />
                        <input {...field('semester')} placeholder="Semester" />
                    </div>

                    <label className="block">
                        <span className="mb-1 flex items-center gap-1.5 text-xs font-medium">
                            <Icon name="paperclip" className="h-3.5 w-3.5" />
                            Attachments (optional)
                        </span>
                        <input
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                            onChange={(e) => form.setData('attachments', Array.from(e.target.files))}
                            className="field !py-1.5"
                        />
                        <span className="section-sub mt-1 block">
                            Seat plans, routines, or forms &mdash; PDF, image, Word, or Excel, up to 10 MB each.
                        </span>
                    </label>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_urgent}
                            onChange={(e) => form.setData('is_urgent', e.target.checked)}
                            className="h-4 w-4 shrink-0 rounded border-ink-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span>Mark as urgent</span>
                    </label>

                    {Object.keys(form.errors).length > 0 && (
                        <p className="text-sm text-red-600 dark:text-red-300">{Object.values(form.errors)[0]}</p>
                    )}

                    <button type="submit" disabled={form.processing} className="btn-primary w-full">
                        {form.processing ? 'Publishing…' : 'Publish notice'}
                    </button>
                </form>
            )}
        </div>
    );
}

function NoticeRow({ notice, delay }) {
    return (
        <a href={notice.url} className="surface surface-interactive flex p-0" style={{ '--cc-stagger': `${delay}ms` }}>
            <div className={`accent-bar ${notice.is_important ? 'is-urgent' : ''}`} />
            <div className="flex min-w-0 flex-1 gap-4 p-5">
                <span className={`card-icon grid h-10 w-10 shrink-0 place-items-center rounded-xl ${
                    notice.is_important
                        ? 'bg-red-500/10 text-red-600 dark:text-red-300'
                        : 'bg-primary-500/10 text-primary-600 dark:text-primary-300'
                }`}>
                    <Icon name={notice.is_important ? 'alert-triangle' : 'file-text'} className="h-5 w-5" />
                </span>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap gap-1.5">
                        <span className="chip">{notice.type_label}</span>
                        {/* A class or club announcement, shown only to the
                            people in that group. Saying so keeps it from
                            reading as campus-wide news. */}
                        {notice.from_group && (
                            <span className="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">
                                <Icon name="users" className="h-3.5 w-3.5" />
                                {notice.from_group}
                            </span>
                        )}
                        {notice.is_important && (
                            <span className="chip chip-alert !bg-red-500/10 !text-red-600 dark:!text-red-300">Important</span>
                        )}
                        {notice.attachments_count > 0 && (
                            <span className="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">
                                <Icon name="paperclip" className="h-3.5 w-3.5" />
                                {notice.attachments_count} {notice.attachments_count === 1 ? 'file' : 'files'}
                            </span>
                        )}
                    </div>

                    <h2 className="card-title mt-1.5 text-[15px] font-semibold">{notice.title}</h2>

                    {/* Only shown when somebody actually uploaded one. A row
                        of generated placeholders would make the board look
                        busier without telling the reader anything. */}
                    {notice.thumb_url && (
                        <img
                            src={notice.thumb_url}
                            alt=""
                            loading="lazy"
                            className="mt-2 h-32 w-full rounded-xl object-cover sm:h-40"
                        />
                    )}

                    <p className="mt-1 text-sm text-ink-600 dark:text-ink-300">{notice.excerpt}</p>

                    <div className="section-sub mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        {notice.meta && <span>{notice.meta}</span>}
                        <span>Issued by {notice.issuer}</span>
                        <span>{notice.ago}</span>
                    </div>

                    <span className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-300">
                        Read full notice<Icon name="chevron-right" className="h-4 w-4" />
                    </span>
                </div>
            </div>
        </a>
    );
}

export default function NoticesIndex({ notices, featured, stats, types, filters, canIssue }) {
    const hasAdvanced = filters.department !== '' || filters.batch !== '' || filters.semester !== '';
    const [filtersOpen, setFiltersOpen] = useState(hasAdvanced);
    const [draft, setDraft] = useState(filters);

    useEffect(() => setDraft(filters), [filters]);

    const go = (params) => router.get('/notices', clean(params), { preserveState: true, replace: true });

    const activeFilterCount =
        [filters.department, filters.batch, filters.semester].filter((v) => v !== '').length + (filters.urgent ? 1 : 0);

    return (
        <AppLayout title="Notices">
            <div className="mx-auto max-w-4xl">
                <div className="surface mb-5 p-4">
                    <form
                        onSubmit={(e) => { e.preventDefault(); go({ ...draft }); }}
                        className="flex flex-col gap-2 sm:flex-row"
                    >
                        <div className="group relative flex-1">
                            <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400 transition-all duration-300 ease-out group-focus-within:scale-110 group-focus-within:text-primary-500" />
                            <input
                                type="search"
                                value={draft.q}
                                onChange={(e) => setDraft({ ...draft, q: e.target.value })}
                                placeholder="Search notices by title or details"
                                className="field pl-10 transition-all duration-300 ease-out focus:shadow-[0_10px_28px_-18px_var(--color-primary-500)]"
                            />
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setFiltersOpen(!filtersOpen)}
                                className={`btn-secondary flex-1 whitespace-nowrap sm:flex-initial ${
                                    filtersOpen ? '!bg-primary-500/10 !text-primary-700 dark:!text-primary-300' : ''
                                }`}
                            >
                                <Icon name="sliders" className={`h-4 w-4 transition-transform duration-500 ease-out ${filtersOpen ? 'rotate-180' : ''}`} />
                                <span>Filters</span>
                                {activeFilterCount > 0 && (
                                    <span className="rounded-full bg-primary-500 px-1.5 text-xs text-white">{activeFilterCount}</span>
                                )}
                            </button>
                            <button type="submit" className="btn-primary flex-1 whitespace-nowrap sm:flex-initial">Search</button>
                        </div>
                    </form>

                    <div className="cc-stagger mt-3 flex flex-wrap gap-1.5 border-t border-ink-100 pt-3 dark:border-ink-800/70">
                        <button
                            type="button"
                            onClick={() => go({ q: filters.q })}
                            className={`filter-pill ${filters.type === '' ? 'is-active' : ''}`}
                        >
                            All types
                        </button>
                        {Object.entries(types).map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => go({ type: key, q: filters.q })}
                                className={`filter-pill ${filters.type === key ? 'is-active' : ''}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    {filtersOpen && (
                        <div className="mt-3 grid origin-top gap-2 border-t border-ink-100 pt-3 dark:border-ink-800/70 sm:grid-cols-2 lg:grid-cols-4">
                            <input
                                value={draft.department}
                                onChange={(e) => setDraft({ ...draft, department: e.target.value })}
                                placeholder="Department"
                                className="field"
                            />
                            <input
                                value={draft.batch}
                                onChange={(e) => setDraft({ ...draft, batch: e.target.value })}
                                placeholder="Batch / year"
                                className="field"
                            />
                            <input
                                value={draft.semester}
                                onChange={(e) => setDraft({ ...draft, semester: e.target.value })}
                                placeholder="Semester"
                                className="field"
                            />
                            <label className="flex items-center gap-2 rounded-xl border border-ink-200 px-3 text-sm dark:border-ink-800">
                                <input
                                    type="checkbox"
                                    checked={Boolean(draft.urgent)}
                                    onChange={(e) => setDraft({ ...draft, urgent: e.target.checked })}
                                    className="h-4 w-4 shrink-0 rounded border-ink-300 text-primary-500 focus:ring-primary-500"
                                />
                                <span>Important only</span>
                            </label>
                            <div className="flex gap-2 sm:col-span-2 lg:col-span-4">
                                <button type="button" onClick={() => go({ ...draft })} className="btn-primary">Apply filters</button>
                                <button type="button" onClick={() => go({})} className="btn-secondary">Reset all</button>
                            </div>
                        </div>
                    )}
                </div>

                <div className="hero-panel mb-5 p-7 sm:p-8">
                    <Icon name="megaphone" className="hero-icon" />
                    <div className="relative z-10 flex flex-wrap items-start justify-between gap-4">
                        <div className="max-w-md">
                            <p className="eyebrow"><Icon name="megaphone" className="h-3.5 w-3.5" />Official notice board</p>
                            <h1 className="hero-title mt-2">Important campus updates in one place</h1>
                            <p className="section-sub mt-2">
                                Exam notices, payment deadlines, class routines, scholarships, transport, and department updates &mdash; without digging through the feed.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <a href="/feed" className="btn-primary whitespace-nowrap">Go to Feed</a>
                            <a href="/events" className="btn-secondary whitespace-nowrap">Upcoming Events</a>
                        </div>
                    </div>

                    <div className="cc-stagger relative z-10 mt-5 grid grid-cols-3 gap-2 sm:gap-3" style={{ '--cc-offset': '220ms' }}>
                        <div className="hero-stat">
                            <span className="stat-value block truncate text-lg font-semibold tracking-tight sm:text-2xl">{stats.all}</span>
                            <span className="mt-0.5 block truncate text-[10px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400 sm:text-xs">Published</span>
                        </div>
                        <div className={`hero-stat ${stats.urgent > 0 ? 'is-alert' : ''}`}>
                            <span className="stat-value block truncate text-lg font-semibold tracking-tight text-red-600 dark:text-red-300 sm:text-2xl">{stats.urgent}</span>
                            <span className="mt-0.5 block truncate text-[10px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400 sm:text-xs">Urgent</span>
                        </div>
                        <div className="hero-stat">
                            <span className="stat-value block truncate text-lg font-semibold tracking-tight sm:text-2xl">{stats.academic}</span>
                            <span className="mt-0.5 block truncate text-[10px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400 sm:text-xs">Academic</span>
                        </div>
                    </div>
                </div>

                {featured && (
                    <a href={featured.url} className="surface surface-interactive sheen-once mb-5 flex gap-4 p-0">
                        <div className={`accent-bar ${featured.is_important ? 'is-urgent' : 'is-accent'}`} />
                        <div className="flex min-w-0 flex-1 gap-4 py-5 pr-6">
                            <div className="card-icon flex w-14 shrink-0 flex-col items-center rounded-xl bg-ink-900/[0.04] py-2 dark:bg-white/[0.06]">
                                <span className="text-[11px] font-semibold uppercase text-ink-400">{featured.month}</span>
                                <span className="text-lg font-bold">{featured.day}</span>
                            </div>
                            <div className="min-w-0">
                                <div className="flex flex-wrap gap-1.5">
                                    <span className="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">Featured</span>
                                    <span className="chip">{featured.type_label}</span>
                                    {featured.is_important && (
                                        <span className="chip chip-alert !bg-red-500/10 !text-red-600 dark:!text-red-300">Important</span>
                                    )}
                                    {featured.attachments_count > 0 && (
                                        <span className="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">
                                            <Icon name="paperclip" className="h-3.5 w-3.5" />
                                            {featured.attachments_count}
                                        </span>
                                    )}
                                </div>
                                <h2 className="card-title mt-1.5 text-[17px] font-semibold">{featured.title}</h2>
                                <p className="mt-1 text-sm text-ink-600 dark:text-ink-300">{featured.excerpt}</p>
                                <p className="section-sub mt-2">Issued by {featured.issuer} &middot; {featured.issued_at}</p>
                                <span className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-300">
                                    Read full notice<Icon name="chevron-right" className="h-4 w-4" />
                                </span>
                            </div>
                        </div>
                    </a>
                )}

                {canIssue && <PublishForm types={types} />}

                {notices.data.length === 0 && !featured ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="flag" className="h-6 w-6" /></span>
                        <h3>No notices found</h3>
                        <p>Try clearing your filters or searching with a different keyword.</p>
                    </div>
                ) : notices.data.length > 0 ? (
                    <>
                        <div className="cc-stagger space-y-3">
                            {notices.data.map((notice, i) => (
                                <NoticeRow key={notice.id} notice={notice} delay={Math.min(i, 14) * 55} />
                            ))}
                        </div>
                        <div className="mt-6"><Pagination meta={notices} /></div>
                    </>
                ) : null}
            </div>
        </AppLayout>
    );
}
