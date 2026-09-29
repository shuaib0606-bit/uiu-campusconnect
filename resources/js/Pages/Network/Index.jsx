import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import Pagination from '../../Components/Pagination';
import UserAvatar from '../../Components/UserAvatar';

const ROLES = [
    ['', 'All roles'],
    ['student', 'Students'],
    ['faculty', 'Faculty'],
    ['official', 'Officials'],
    ['admin', 'Admin'],
];

const SMALL = '!px-3 !py-1.5 text-xs';

function post(url) {
    router.post(url, {}, { preserveScroll: true });
}

/** The connect / accept / unfriend controls, which depend on where the two stand. */
function Relationship({ person }) {
    const { relationship: state, actions } = person;

    if (state === 'connected') {
        return (
            <button type="button" onClick={() => post(actions.remove)} className={`btn-secondary ${SMALL}`}>
                Unfriend
            </button>
        );
    }

    if (state === 'sent') {
        return <span className="chip">Request sent</span>;
    }

    if (state === 'incoming') {
        return (
            <>
                <button type="button" onClick={() => post(actions.accept)} className={`btn-primary ${SMALL}`}>Accept</button>
                <button type="button" onClick={() => post(actions.decline)} className={`btn-secondary ${SMALL}`}>Decline</button>
            </>
        );
    }

    if (state === 'permission') {
        return (
            <a href={actions.permission} className={`btn-primary ${SMALL}`}>Request permission to connect</a>
        );
    }

    if (state === 'connect') {
        return (
            <button type="button" onClick={() => post(actions.connect)} className={`btn-primary ${SMALL}`}>Connect</button>
        );
    }

    return null;
}

function PersonCard({ person }) {
    return (
        <article className="surface surface-hover flex flex-col p-5">
            <div className="flex items-start gap-3">
                <a href={person.profile_url} className="shrink-0">
                    <UserAvatar user={person} size="h-14 w-14" text="text-lg" />
                </a>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <a href={person.profile_url} className="truncate text-[15px] font-semibold hover:underline">
                            {person.full_name}
                        </a>
                        <span className="chip shrink-0">{person.role_label}</span>
                    </div>
                    <p className="truncate text-sm text-ink-600 dark:text-ink-300">{person.title}</p>
                    <p className="truncate text-xs text-ink-400">{person.department}</p>
                </div>
            </div>

            {person.special_role && (
                <div className="mt-3 inline-flex w-fit items-center gap-1.5 rounded-lg bg-primary-500/10 px-2.5 py-1 text-xs font-semibold text-primary-700 dark:text-primary-300">
                    <Icon name="award" className="h-3.5 w-3.5" />
                    {person.special_role}
                </div>
            )}

            <p className="mt-3 flex-1 text-sm text-ink-600 dark:text-ink-300">
                {person.bio || 'No public bio added yet.'}
            </p>

            {person.skills.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1.5">
                    {person.skills.map((skill) => (
                        <span key={skill} className="chip">{skill}</span>
                    ))}
                </div>
            )}

            <div className="mt-4 flex flex-wrap gap-2 border-t border-ink-100 pt-4 dark:border-ink-800/70">
                <a href={person.profile_url} className={`btn-secondary ${SMALL}`}>View Profile</a>

                {person.message_url && (
                    <a href={person.message_url} className={`btn-secondary ${SMALL}`}>Message</a>
                )}

                <Relationship person={person} />
            </div>
        </article>
    );
}

export default function NetworkIndex({ people, filters }) {
    const [q, setQ] = useState(filters.q);
    const [role, setRole] = useState(filters.role);

    // A visit from the pager or the browser's back button carries its own filters.
    useEffect(() => {
        setQ(filters.q);
        setRole(filters.role);
    }, [filters.q, filters.role]);

    const search = (event) => {
        event.preventDefault();
        router.get('/network', { q, role }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Network">
            <div className="mx-auto max-w-6xl">
                <div className="section-heading mb-5 flex-wrap gap-4">
                    <div>
                        <h1>University Network</h1>
                        <p className="section-sub">Find students, faculty, officials, and active campus members.</p>
                    </div>

                    <form onSubmit={search} className="flex flex-1 flex-wrap justify-end gap-2 sm:flex-nowrap">
                        <input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search name, department, title, or expertise"
                            className="field min-w-[220px] flex-1 sm:max-w-xs"
                        />
                        <select value={role} onChange={(e) => setRole(e.target.value)} className="field w-auto">
                            {ROLES.map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                        <button type="submit" className="btn-primary shrink-0">Search</button>
                    </form>
                </div>

                {people.data.length === 0 ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="network" className="h-6 w-6" /></span>
                        <h3>No people found</h3>
                        <p>Try another keyword or clear the role filter.</p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {people.data.map((person) => (
                                <PersonCard key={person.id} person={person} />
                            ))}
                        </div>

                        <div className="mt-6">
                            <Pagination meta={people} />
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
