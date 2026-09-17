import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import UserAvatar from '../../Components/UserAvatar';

const capped = (n) => (n > 99 ? '99+' : n);

function Row({ item }) {
    return (
        <a
            href={item.url}
            className={`group relative flex items-center gap-3 px-5 py-4 transition-colors hover:bg-ink-900/[0.03] dark:hover:bg-white/[0.04] ${
                item.is_read ? '' : 'bg-primary-500/[0.06]'
            }`}
        >
            <span className="relative shrink-0">
                <UserAvatar user={item.actor} size="h-10 w-10" text="text-xs" />
                <span className="absolute -bottom-0.5 -right-0.5 grid h-5 w-5 place-items-center rounded-full bg-primary-500 text-white ring-2 ring-[var(--surface-card)]">
                    <Icon name={item.icon} className="h-3 w-3" />
                </span>
            </span>

            <span className="min-w-0 flex-1">
                <span className={`block text-sm ${item.is_read ? 'text-ink-600 dark:text-ink-300' : 'font-medium text-ink-900 dark:text-white'}`}>
                    {item.message}
                </span>
                <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-ink-400 dark:text-ink-500">
                    <span>{item.ago}</span>
                    {item.is_aggregated && <span className="chip !px-2 !py-0 !text-[11px]">{item.group_count} people</span>}
                </span>
            </span>

            {!item.is_read && <span className="h-2 w-2 shrink-0 rounded-full bg-primary-500" aria-label="Unread" />}
            <Icon name="chevron-right" className="h-4 w-4 shrink-0 text-ink-300 opacity-0 transition-opacity group-hover:opacity-100 dark:text-ink-600" />
        </a>
    );
}

export default function NotificationsIndex({ buckets, isEmpty, counts, filter, tabs }) {
    const go = (key) => router.get('/notifications', key === 'all' ? {} : { filter: key }, {
        preserveState: true,
        replace: true,
    });

    const badgeFor = (key) => {
        if (key === 'unread' && counts.unread > 0) return capped(counts.unread);
        if (key === 'mentions' && counts.mentions > 0) return capped(counts.mentions);

        return null;
    };

    return (
        <AppLayout title="Notifications">
            <div className="mx-auto max-w-2xl">
                <div className="surface mb-5 flex flex-wrap items-center justify-between gap-6 p-6">
                    <div className="max-w-sm">
                        <p className="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">
                            <Icon name="bell" className="h-4 w-4" />
                            Notification center
                        </p>
                        <h1 className="mt-1 text-[19px] font-semibold">Updates that need your attention</h1>
                        <p className="section-sub mt-1">
                            Reactions, comments, mentions, connections and university notices - open one to jump straight to it.
                        </p>
                    </div>
                    <div className="grid shrink-0 grid-cols-2 gap-3">
                        <div className="stat-card items-center text-center">
                            <span className="stat-value">{counts.unread.toLocaleString()}</span>
                            <span className="stat-label">Unread</span>
                        </div>
                        <div className="stat-card items-center text-center">
                            <span className="stat-value">{counts.total.toLocaleString()}</span>
                            <span className="stat-label">Total</span>
                        </div>
                    </div>
                </div>

                <div className="surface-flat mb-5 flex flex-wrap items-center justify-between gap-3 p-2">
                    <div className="flex flex-wrap gap-1.5">
                        {Object.entries(tabs).map(([key, label]) => {
                            const active = filter === key;
                            const badge = badgeFor(key);

                            return (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => go(key)}
                                    className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                                        active
                                            ? 'bg-primary-500 text-white'
                                            : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.06]'
                                    }`}
                                >
                                    {label}
                                    {badge !== null && (
                                        <span className={`rounded-full px-1.5 text-[11px] font-semibold ${
                                            active ? 'bg-white/25' : 'bg-primary-500/15 text-primary-600 dark:text-primary-300'
                                        }`}>
                                            {badge}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    {counts.unread > 0 && (
                        <button
                            type="button"
                            onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}
                            className="btn-secondary !px-3 !py-1.5 text-xs"
                        >
                            <Icon name="check" className="h-3.5 w-3.5" />
                            Mark all read
                        </button>
                    )}
                </div>

                {isEmpty && (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="bell" className="h-6 w-6" /></span>
                        <h3>Nothing here yet</h3>
                        <p>
                            When someone reacts to your post, replies to you, mentions you, follows you, or the university
                            publishes a notice, it lands here.
                        </p>
                    </div>
                )}

                {buckets.map((bucket) => (
                    <div key={bucket.label}>
                        <h2 className="mb-2 mt-6 text-sm font-semibold text-ink-500 first:mt-0 dark:text-ink-400">
                            {bucket.label}
                        </h2>
                        <div className="surface cc-stagger divide-y divide-ink-100 overflow-hidden dark:divide-ink-800/70">
                            {bucket.items.map((item) => <Row key={item.id} item={item} />)}
                        </div>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
