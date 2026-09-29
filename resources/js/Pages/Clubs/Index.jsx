import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';

function ClubCard({ club }) {
    return (
        <article className="surface surface-hover flex flex-col overflow-hidden">
            <div className="h-28 w-full bg-cover bg-center" style={{ backgroundImage: `url('${club.cover_url}')` }} />

            <div className="flex flex-1 flex-col p-5">
                <div className="flex items-center gap-3">
                    {club.logo_url ? (
                        <img src={club.logo_url} alt={`${club.name} logo`} className="h-12 w-12 shrink-0 rounded-2xl object-cover" />
                    ) : (
                        <span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary-500/10 text-primary-600 dark:text-primary-300">
                            <Icon name="pennant" className="h-6 w-6" />
                        </span>
                    )}
                    <div className="min-w-0">
                        <h2 className="truncate text-[15px] font-semibold">
                            <a href={club.url} className="hover:underline">{club.name}</a>
                        </h2>
                        <span className="chip">{club.category}</span>
                    </div>
                </div>

                <p className="mt-3 flex-1 text-sm text-ink-600 dark:text-ink-300">{club.description}</p>

                <p className="mt-3 text-sm">
                    <span className="font-semibold">{club.members_count}</span>
                    {club.members_count === 1 ? ' member' : ' members'} &middot;{' '}
                    {club.events_count} {club.events_count === 1 ? 'event' : 'events'}
                </p>
                <p className="section-sub">Moderator: {club.moderator}</p>
                {club.meeting && <p className="section-sub">{club.meeting}</p>}

                <a href={club.url} className="btn-primary mt-4 w-fit">Open Club</a>
            </div>
        </article>
    );
}

export default function ClubsIndex({ clubs, filters }) {
    const [q, setQ] = useState(filters.q);

    useEffect(() => setQ(filters.q), [filters.q]);

    const search = (event) => {
        event.preventDefault();
        router.get('/clubs', { q }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Clubs">
            <div className="mx-auto max-w-5xl">
                <div className="section-heading mb-5">
                    <div>
                        <h1>University Clubs</h1>
                        <p className="section-sub">Discover verified clubs, request membership, see executives, and follow club events.</p>
                    </div>
                </div>

                <form onSubmit={search} className="relative mb-5">
                    <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search clubs by name, category, or description"
                        className="field pl-10"
                    />
                </form>

                {clubs.length === 0 ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="pennant" className="h-6 w-6" /></span>
                        <h3>No clubs found</h3>
                        <p>Try a different search term.</p>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {clubs.map((club) => <ClubCard key={club.id} club={club} />)}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
