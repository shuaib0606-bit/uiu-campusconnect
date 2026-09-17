import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';

const TILES = [
    ['pending_users', 'Pending users'],
    ['pending_apps', 'Connection applications'],
    ['reports', 'Open reports'],
    ['active_users', 'Active users'],
];

const SUMMARY = [
    ['suspended_users', 'Suspended users'],
    ['clubs', 'Active clubs'],
    ['events', 'Published events'],
    ['notices', 'Published notices'],
];

export default function AdminCenter({ stats, cards, recentUsers, reviewUrl, settingsUrl }) {
    return (
        <AppLayout title="Admin Center">
            <div className="mx-auto max-w-5xl">
                <div className="surface mb-5 flex flex-wrap items-center justify-between gap-4 bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-white">
                    <div className="max-w-xl">
                        <p className="text-xs font-semibold uppercase tracking-wide text-white/80">Master administration</p>
                        <h1 className="mt-1 text-[22px] font-semibold">Admin Control Center</h1>
                        <p className="mt-1 text-sm text-white/85">
                            Manage approvals, reports, clubs, notices, events, and user access from one clean dashboard.
                        </p>
                    </div>
                    <a href={settingsUrl} className="btn-secondary !bg-white/15 !text-white hover:!bg-white/25">Account settings</a>
                </div>

                <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {TILES.map(([key, label]) => (
                        <div key={key} className="stat-card items-center text-center">
                            <span className="stat-value">{stats[key]}</span>
                            <span className="stat-label">{label}</span>
                        </div>
                    ))}
                </div>

                <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {cards.map((card) => (
                        <article key={card.title} className="surface surface-hover flex flex-col p-5">
                            <span className="chip w-fit">{card.pill}</span>
                            <h2 className="mt-2 inline-flex items-center gap-1.5 text-[15px] font-semibold">
                                <Icon name={card.icon} className="h-4 w-4 text-primary-600 dark:text-primary-300" />
                                {card.title}
                            </h2>
                            <p className="mt-1 flex-1 text-sm text-ink-600 dark:text-ink-300">{card.desc}</p>
                            <a href={card.href} className="btn-primary mt-3 w-fit">{card.cta}</a>
                        </article>
                    ))}
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div className="surface p-6">
                        <h2 className="mb-3 text-[15px] font-semibold">Recent users</h2>
                        {recentUsers.length === 0 ? (
                            <p className="text-sm text-ink-400">No user records found.</p>
                        ) : (
                            recentUsers.map((user) => (
                                <div key={user.id} className="flex items-center justify-between gap-3 border-b border-ink-100 py-2.5 last:border-0 dark:border-ink-800/70">
                                    <div className="min-w-0">
                                        <a href={user.url} className="truncate text-sm font-semibold hover:underline">{user.full_name}</a>
                                        <p className="section-sub truncate">{user.meta}</p>
                                    </div>
                                    <a href={reviewUrl} className="btn-secondary shrink-0 !px-3 !py-1.5 text-xs">Review</a>
                                </div>
                            ))
                        )}
                    </div>

                    <div className="surface p-6">
                        <h2 className="mb-3 text-[15px] font-semibold">System summary</h2>
                        <div className="space-y-2.5 text-sm">
                            {SUMMARY.map(([key, label], i) => (
                                <div
                                    key={key}
                                    className={`flex items-center justify-between ${
                                        i < SUMMARY.length - 1 ? 'border-b border-ink-100 pb-2.5 dark:border-ink-800/70' : ''
                                    }`}
                                >
                                    <span>{label}</span>
                                    <span className="font-semibold">{stats[key]}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
