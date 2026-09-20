import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import Pagination from '../../Components/Pagination';

const TYPES = ['course', 'batch', 'project', 'club', 'community'];

const label = (type) => type.charAt(0).toUpperCase() + type.slice(1);

function CreateForm() {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', group_type: 'course', department: '', description: '', visibility: 'public' });

    const field = (name) => ({
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        className: 'field',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/groups', {
            preserveScroll: true,
            onSuccess: () => { form.reset(); setOpen(false); },
        });
    };

    return (
        <div className="surface mb-5 p-6">
            <button type="button" onClick={() => setOpen(!open)} className="text-[15px] font-semibold">
                Create a group
            </button>

            {open && (
                <form onSubmit={submit} className="mt-4 space-y-3">
                    <div className="grid grid-cols-2 gap-2">
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium">Name</span>
                            <input {...field('name')} placeholder="CSE 101 / BBA Batch 23" required />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium">Type</span>
                            <select {...field('group_type')}>
                                {TYPES.map((type) => <option key={type} value={type}>{label(type)}</option>)}
                            </select>
                        </label>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium">Department</span>
                            <input {...field('department')} placeholder="Department (optional)" />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-xs font-medium">Who can see inside</span>
                            <select {...field('visibility')}>
                                <option value="public">Anyone at UIU</option>
                                <option value="private">Members only</option>
                            </select>
                        </label>
                    </div>

                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Description</span>
                        <textarea {...field('description')} rows={2} placeholder="What is this group for?" required />
                    </label>

                    {Object.keys(form.errors).length > 0 && (
                        <p className="text-sm text-red-600 dark:text-red-300">{Object.values(form.errors)[0]}</p>
                    )}

                    <button type="submit" disabled={form.processing} className="btn-primary w-full">
                        {form.processing ? 'Creating…' : 'Create Group'}
                    </button>
                </form>
            )}
        </div>
    );
}

function MyGroups({ mine }) {
    if (mine.length === 0) {
        return null;
    }

    const courses = mine.filter((g) => g.kind === 'course');
    const clubs = mine.filter((g) => g.kind === 'club');

    const card = (group) => (
        <a
            key={group.id}
            href={group.url}
            className={`surface surface-hover flex flex-col p-5 ${group.is_archived ? 'opacity-70' : ''}`}
        >
            <div className="flex flex-wrap items-center gap-1.5">
                <span className="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">{group.kind_label}</span>
                {group.term && <span className="chip">{group.term}</span>}
                {group.is_archived && <span className="chip">Archived</span>}
            </div>

            <h3 className="mt-2 text-[15px] font-semibold">{group.name}</h3>
            <p className="mt-1 flex-1 text-sm text-ink-600 dark:text-ink-300">{group.line}</p>

            {group.notice && (
                <p className="mt-2 rounded-lg bg-amber-500/10 px-2.5 py-1.5 text-xs text-amber-800 dark:text-amber-300">
                    {group.notice}
                </p>
            )}

            <p className="section-sub mt-3">
                {group.members_count} {group.members_count === 1 ? 'member' : 'members'}
                {' · '}
                {group.posts_count} {group.posts_count === 1 ? 'post' : 'posts'}
            </p>
        </a>
    );

    return (
        <>
            {courses.length > 0 && (
                <section className="mb-6">
                    <h2 className="mb-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Your classes</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{courses.map(card)}</div>
                </section>
            )}

            {clubs.length > 0 && (
                <section className="mb-6">
                    <h2 className="mb-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Your clubs</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{clubs.map(card)}</div>
                </section>
            )}
        </>
    );
}

export default function GroupsIndex({ mine, groups, filters, canCreate }) {
    const [draft, setDraft] = useState(filters);

    useEffect(() => setDraft(filters), [filters]);

    const search = (event) => {
        event.preventDefault();
        router.get('/groups', draft, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Groups">
            <div className="mx-auto max-w-5xl">
                <div className="section-heading mb-5">
                    <div>
                        <h1>Groups</h1>
                        <p className="section-sub">
                            Your classes and clubs appear here on their own. Below them is everything students have
                            started themselves.
                        </p>
                    </div>
                </div>

                <MyGroups mine={mine} />

                <h2 className="mb-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Open groups</h2>

                {canCreate && <CreateForm />}

                <form onSubmit={search} className="mb-5 flex flex-wrap gap-2">
                    <div className="relative min-w-[200px] flex-1">
                        <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                        <input
                            type="search"
                            value={draft.q}
                            onChange={(e) => setDraft({ ...draft, q: e.target.value })}
                            placeholder="Search groups"
                            className="field pl-10"
                        />
                    </div>
                    <select
                        value={draft.type}
                        onChange={(e) => setDraft({ ...draft, type: e.target.value })}
                        className="field w-auto"
                    >
                        <option value="">All types</option>
                        {TYPES.map((type) => <option key={type} value={type}>{label(type)}</option>)}
                    </select>
                    <button type="submit" className="btn-secondary">Search</button>
                </form>

                {groups.data.length === 0 ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="users" className="h-6 w-6" /></span>
                        <h3>No groups found</h3>
                        <p>Try a different search or type filter, or be the first to create one.</p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {groups.data.map((group) => (
                                <article key={group.id} className="surface surface-hover flex flex-col p-5">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <span className="chip">{group.type_label}</span>
                                        {group.is_private && (
                                            <span className="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">
                                                <Icon name="lock" className="h-3 w-3" />Private
                                            </span>
                                        )}
                                    </div>
                                    <h2 className="mt-2 text-[15px] font-semibold">
                                        <a href={group.url} className="hover:underline">{group.name}</a>
                                    </h2>
                                    <p className="mt-1 flex-1 text-sm text-ink-600 dark:text-ink-300">{group.description}</p>
                                    <p className="section-sub mt-3">{group.meta}</p>
                                    <a href={group.url} className="btn-primary mt-4 w-fit">Open Group</a>
                                </article>
                            ))}
                        </div>
                        <div className="mt-6"><Pagination meta={groups} /></div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
