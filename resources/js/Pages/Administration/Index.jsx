import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';

const BLANK = {
    trimester_id: '',
    course_code: '',
    course_title: '',
    section_name: '',
    schedule_pattern: '',
    start_time: '',
    end_time: '',
    room: '',
    seat_limit: '',
    teacher_identifier: '',
};

/**
 * The create form and the per-section edit form are the same fields, so they
 * are the same component: `section` null means create.
 */
function SectionForm({ trimesterId, section = null, onDone }) {
    const form = useForm(section
        ? {
            course_code: section.course_code,
            course_title: section.course_title,
            section_name: section.section_name,
            schedule_pattern: section.schedule_pattern ?? '',
            start_time: section.start_time ?? '',
            end_time: section.end_time ?? '',
            room: section.room ?? '',
            seat_limit: section.seat_limit ?? '',
            teacher_identifier: '',
        }
        : { ...BLANK, trimester_id: trimesterId ?? '' });

    const field = (name, props = {}) => ({
        name,
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        className: `field !py-1.5 text-sm ${form.errors[name] ? '!border-red-400' : ''}`,
        ...props,
    });

    const submit = (e) => {
        e.preventDefault();

        const done = { preserveScroll: true, onSuccess: () => { form.reset(); onDone?.(); } };

        if (section) {
            form.put(section.update_url, done);
        } else {
            form.post('/administration', done);
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-2 rounded-xl bg-ink-900/[0.03] p-3.5 sm:grid-cols-2 dark:bg-white/[0.04]">
            {!section && (
                <>
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Course code</span>
                        <input {...field('course_code', { required: true, placeholder: 'CSE 2213', maxLength: 30 })} />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Section</span>
                        <input {...field('section_name', { required: true, placeholder: 'C', maxLength: 30 })} />
                    </label>
                </>
            )}

            <label className="block sm:col-span-2">
                <span className="mb-1 block text-xs font-medium">Course title</span>
                <input {...field('course_title', { required: true, placeholder: 'Data Structures', maxLength: 190 })} />
            </label>

            <label className="block">
                <span className="mb-1 block text-xs font-medium">Schedule</span>
                <input {...field('schedule_pattern', { placeholder: 'Sun-Tue', maxLength: 60 })} />
            </label>
            <label className="block">
                <span className="mb-1 block text-xs font-medium">Room</span>
                <input {...field('room', { placeholder: 'Room 412', maxLength: 120 })} />
            </label>

            <label className="block">
                <span className="mb-1 block text-xs font-medium">Starts</span>
                <input {...field('start_time', { type: 'time' })} />
            </label>
            <label className="block">
                <span className="mb-1 block text-xs font-medium">Ends</span>
                <input {...field('end_time', { type: 'time' })} />
            </label>

            <label className="block">
                <span className="mb-1 block text-xs font-medium">Seats</span>
                <input {...field('seat_limit', { type: 'number', min: 1, max: 500, placeholder: '40' })} />
            </label>
            <label className="block">
                <span className="mb-1 block text-xs font-medium">
                    Teacher <span className="font-normal text-ink-400">(ID or email)</span>
                </span>
                <input {...field('teacher_identifier', { placeholder: section?.teacher ?? 'EMP-DEMO-11' })} />
            </label>

            {Object.keys(form.errors).length > 0 && (
                <p className="text-sm text-red-600 sm:col-span-2 dark:text-red-300">{Object.values(form.errors)[0]}</p>
            )}

            <div className="flex flex-wrap gap-2 sm:col-span-2">
                <button type="submit" className="btn-primary !py-1.5 text-xs" disabled={form.processing}>
                    {section ? 'Save section' : 'Add section'}
                </button>
                {section && (
                    <button type="button" onClick={onDone} className="btn-ghost !py-1.5 text-xs">Cancel</button>
                )}
            </div>
        </form>
    );
}

function SectionRow({ section, canManage }) {
    const [editing, setEditing] = useState(false);

    return (
        <div className="surface p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 gap-4">
                    <div className="flex w-14 shrink-0 flex-col items-center rounded-xl bg-ink-900/[0.04] py-2 dark:bg-white/[0.06]">
                        <strong className="text-sm">{section.code_badge}</strong>
                        <span className="text-xs text-ink-400">{section.section_name}</span>
                    </div>

                    <div className="min-w-0">
                        <h3 className="text-[15px] font-semibold">
                            {section.course_code} {section.section_name} — {section.course_title}
                        </h3>
                        <p className="section-sub">{section.schedule}</p>

                        <div className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                            <span className="inline-flex items-center gap-1">
                                <Icon name="class" className="h-3.5 w-3.5" />
                                {section.student_count} enrolled
                                {section.seat_limit ? ` of ${section.seat_limit}` : ''}
                            </span>
                            {section.teacher ? (
                                <span className="inline-flex items-center gap-1">
                                    <Icon name="user" className="h-3.5 w-3.5" />{section.teacher}
                                </span>
                            ) : (
                                <span className="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">No teacher assigned</span>
                            )}
                            {section.source === 'csv' && <span className="chip">From roster import</span>}
                        </div>
                    </div>
                </div>

                <div className="flex shrink-0 flex-wrap gap-2">
                    {section.group_url && (
                        <a href={section.group_url} className="btn-secondary !px-3 !py-1.5 text-xs">Class group</a>
                    )}
                    {canManage && (
                        <button type="button" onClick={() => setEditing(!editing)} className="btn-ghost !px-3 !py-1.5 text-xs">
                            {editing ? 'Close' : 'Edit'}
                        </button>
                    )}
                </div>
            </div>

            {editing && (
                <div className="mt-4">
                    <SectionForm section={section} onDone={() => setEditing(false)} />
                </div>
            )}
        </div>
    );
}

export default function Administration({ trimesters, trimester, sections, totals, canManage }) {
    const [adding, setAdding] = useState(false);

    const stats = [
        [totals.sections, 'Sections'],
        [totals.students, 'Enrolled'],
        [totals.untaught, 'Without a teacher'],
    ];

    return (
        <AppLayout title="Administration">
            <div className="mx-auto max-w-4xl">
                <div className="surface mb-5 flex flex-wrap items-start justify-between gap-6 p-6">
                    <div className="max-w-md">
                        <p className="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">
                            University administration
                        </p>
                        <h1 className="mt-1 text-[19px] font-semibold">Course sections</h1>
                        <p className="section-sub mt-1">
                            The sections running this trimester. Each one becomes a class group with its enrolled
                            students and teacher in it.
                        </p>

                        {trimesters.length > 0 && (
                            <select
                                value={trimester?.id ?? ''}
                                onChange={(e) => router.get('/administration', { trimester: e.target.value }, { preserveState: true })}
                                className="field mt-3 !w-auto !py-1.5 text-sm"
                            >
                                {trimesters.map((t) => (
                                    <option key={t.id} value={t.id}>{t.name} ({t.code})</option>
                                ))}
                            </select>
                        )}
                    </div>

                    <div className="grid shrink-0 grid-cols-3 gap-2">
                        {stats.map(([value, label]) => (
                            <div key={label} className="stat-card items-center text-center">
                                <span className="stat-value">{value}</span>
                                <span className="stat-label">{label}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {!trimester && (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="calendar" className="h-6 w-6" /></span>
                        <h3>No trimester defined yet</h3>
                        <p>
                            Create one on the command line first:
                            {' '}
                            <code className="font-mono text-xs">php artisan uiu:trimester &quot;Fall 2026&quot; --code=2026-3 --start=… --end=…</code>
                        </p>
                    </div>
                )}

                {trimester && (
                    <>
                        {trimester.has_ended && (
                            <div className="surface-flat mb-5 border-l-4 border-l-amber-500 px-4 py-3 text-sm">
                                Teaching ended on {trimester.range.split(' - ')[1]}. These class groups become read-only
                                on {trimester.archives_on}
                                {trimester.days_until_archive > 0 ? `, in ${trimester.days_until_archive} days` : ' — today'}.
                            </div>
                        )}

                        {canManage && (
                            <div className="surface mb-5 p-5">
                                <button
                                    type="button"
                                    onClick={() => setAdding(!adding)}
                                    className="flex w-full items-center justify-between text-left text-[15px] font-semibold"
                                >
                                    Add a section to {trimester.name}
                                    <Icon name="chevron-right" className={`h-4 w-4 transition ${adding ? 'rotate-90' : ''}`} />
                                </button>

                                {adding && (
                                    <div className="mt-4">
                                        <SectionForm trimesterId={trimester.id} onDone={() => setAdding(false)} />
                                    </div>
                                )}

                                <p className="section-sub mt-3">
                                    Most sections arrive through the roster import. Add one here when the registrar&apos;s
                                    export has missed it.
                                </p>
                            </div>
                        )}

                        <h2 className="mb-2 text-sm font-semibold text-ink-500 dark:text-ink-400">
                            {trimester.name} &middot; {trimester.range}
                        </h2>

                        <div className="space-y-3">
                            {sections.length === 0 ? (
                                <div className="empty-state">
                                    <span className="empty-icon"><Icon name="briefcase" className="h-6 w-6" /></span>
                                    <h3>No sections in this trimester</h3>
                                    <p>Import a class list, or add one above.</p>
                                </div>
                            ) : (
                                sections.map((section) => (
                                    <SectionRow key={section.id} section={section} canManage={canManage} />
                                ))
                            )}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
