import { Head, Link, usePage } from '@inertiajs/react';
import Icon from '../Components/Icon';
import UserAvatar from '../Components/UserAvatar';
import UiuSites from '../Components/UiuSites';

/**
 * Paths served by Inertia. Everything else is still a Blade page, so the
 * sidebar has to hand it to the browser instead of fetching it: an Inertia
 * visit to a Blade route gets HTML back and throws a modal error. Matching is
 * exact, because a converted listing such as /clubs usually still has a Blade
 * detail page under it. Add a path here the moment its page is converted.
 */
const INERTIA_ROUTES = [
    '/assistant',
    '/network', '/connect-requests', '/clubs', '/events',
    '/groups', '/notices', '/notifications', '/marketplace', '/lost-found',
    '/developer-admin', '/user-approvals', '/moderation', '/password-requests',
    '/administration', '/settings/password',
];

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/** Writes the cookie, then tells the server so server-rendered pages match. */
function setTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.cookie = `cc_theme=${theme}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`;

    fetch('/theme', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        body: JSON.stringify({ theme }),
    }).catch(() => {});
}

function currentTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

/** Inertia visit for converted pages, ordinary link for the rest. */
function SmartLink({ href, className, children, ...rest }) {
    const Tag = INERTIA_ROUTES.includes(href.split('?')[0]) ? Link : 'a';

    return (
        <Tag href={href} className={className} {...rest}>
            {children}
        </Tag>
    );
}

function NavLink({ href, icon, label, active, badge = 0 }) {
    return (
        <SmartLink href={href} className={`nav-link ${active ? 'is-active' : ''}`}>
            <Icon name={icon} className="h-5 w-5" />
            {label}
            {badge > 0 && (
                <span className="ml-auto rounded-full bg-primary-500 px-2 py-0.5 text-[11px] font-semibold text-white">
                    {badge}
                </span>
            )}
        </SmartLink>
    );
}

export default function AppLayout({ title, children }) {
    const page = usePage();
    const { auth, badges = {}, flash = {}, uiuSites = [] } = page.props;
    const path = page.url.split('?')[0];
    const user = auth?.user;

    const starts = (prefix) => path === prefix || path.startsWith(`${prefix}/`);

    const main = [
        ['/feed', 'home', 'Feed'],
        ['/assistant', 'lightbulb', 'Assistant'],
        ['/network', 'users', 'Network'],
        ['/connect-requests', 'handshake', 'Applications', badges.applications],
        ['/messages', 'message', 'Messages', badges.messages],
        ['/clubs', 'award', 'Clubs'],
        ['/events', 'calendar', 'Events'],
        ['/calendar', 'calendar', 'Academic calendar'],
        ['/groups', 'users', 'Groups', badges.groups],
        ['/notices', 'flag', 'Notices'],
        ['/marketplace', 'shopping-bag', 'Marketplace'],
        ['/lost-found', 'inbox', 'Lost & Found'],
    ];

    const adminLinks = [
        ['/developer-admin', 'shield', 'Admin Center'],
        ['/user-approvals', 'user-plus', 'User Approvals'],
        ['/moderation', 'flag', 'Moderation'],
    ];

    const notice = flash.success
        ? ['border-l-emerald-500', flash.success]
        : flash.warning
            ? ['border-l-amber-500', flash.warning]
            : flash.error
                ? ['border-l-red-500', flash.error]
                : null;

    return (
        <>
            {title && <Head title={title} />}

            <div className="flex min-h-screen">
                <aside className="rail-scroll hidden shrink-0 flex-col border-r border-ink-100 px-4 py-6 dark:border-ink-800/70 lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-[264px] lg:overflow-y-auto">
                    {user ? (
                        <SmartLink href={`/profile/${user.public_id}`} className="mb-8 flex flex-col items-center gap-3 px-2 text-center">
                            <span className="relative">
                                <UserAvatar user={user} size="h-20 w-20" text="text-2xl" />
                                <span className="absolute -left-1 -top-1 grid h-6 w-6 place-items-center rounded-lg bg-primary-500 text-white ring-[3px] ring-[var(--surface-page)]">
                                    <Icon name="home" className="h-3.5 w-3.5" />
                                </span>
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-[15px] font-semibold tracking-tight">{user.full_name}</span>
                                <span className="block text-xs text-ink-500 dark:text-ink-400">{user.handle}</span>
                            </span>
                        </SmartLink>
                    ) : (
                        <SmartLink href="/feed" className="mb-8 flex items-center gap-2.5 px-2">
                            <img src="/img/logo.png" alt="" width="36" height="36" className="h-9 w-9 shrink-0 rounded-xl object-cover" />
                            <span className="text-[15px] font-semibold tracking-tight">CampusConnect</span>
                        </SmartLink>
                    )}

                    <nav className="flex flex-col gap-1">
                        {main.map(([href, icon, label, badge]) => (
                            (href !== '/connect-requests' || user) && (
                                <NavLink
                                    key={href + label}
                                    href={href}
                                    icon={icon}
                                    label={label}
                                    active={starts(href)}
                                    badge={badge ?? 0}
                                />
                            )
                        ))}

                        {/* The university's own systems, each explained, in one dialog. */}
                        <UiuSites sites={uiuSites} />

                        {user?.can_issue_section_announcements && (
                            <NavLink href="/administration" icon="building" label="Administration" active={starts('/administration')} />
                        )}

                        {user?.is_admin && (
                            <>
                                {adminLinks.map(([href, icon, label]) => (
                                    <NavLink key={href} href={href} icon={icon} label={label} active={starts(href)} />
                                ))}
                                <a href="/admin" className="nav-link">
                                    <Icon name="settings" className="h-5 w-5" />
                                    Filament Admin
                                </a>
                            </>
                        )}
                    </nav>

                    {user && (
                        <div className="mt-auto pt-6">
                            <div className="flex items-center gap-1 px-1">
                                <SmartLink href="/settings/profile" className="btn-icon" title="Settings">
                                    <Icon name="settings" className="h-4.5 w-4.5" />
                                </SmartLink>
                                <span className="ml-auto truncate text-xs text-ink-500 dark:text-ink-400">
                                    {user.role_label}
                                </span>
                            </div>
                        </div>
                    )}
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-30 flex items-center gap-3 border-b border-ink-100 bg-[var(--surface-page)]/80 px-4 py-3 backdrop-blur-xl dark:border-ink-800/70 lg:px-8">
                        <div className="flex min-w-0 flex-1 justify-center">
                            <form action="/search" method="GET" className="relative w-full max-w-md">
                                <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                                <input
                                    type="search"
                                    name="q"
                                    placeholder="Search"
                                    className="field border-transparent bg-ink-900/[0.03] py-2 pl-10 focus:bg-[var(--surface-sunken)] dark:bg-white/[0.04]"
                                />
                            </form>
                        </div>

                        <div className="flex shrink-0 items-center gap-1.5">
                            <button
                                type="button"
                                onClick={() => setTheme(currentTheme() === 'dark' ? 'light' : 'dark')}
                                className="btn-icon active:rotate-180"
                                aria-label="Toggle theme"
                            >
                                <Icon name="sun" className="h-5 w-5 dark:hidden" />
                                <Icon name="moon" className="hidden h-5 w-5 dark:block" />
                            </button>

                            {user && (
                                <>
                                    <form method="POST" action="/logout" className="contents">
                                        <input type="hidden" name="_token" value={csrfToken()} />
                                        <button type="submit" className="btn-icon" title="Sign out" aria-label="Sign out">
                                            <Icon name="logout" className="h-5 w-5" />
                                        </button>
                                    </form>
                                    <SmartLink href="/notifications" className="btn-icon relative">
                                        <Icon name="bell" className="h-5 w-5" />
                                        {badges.notifications > 0 && (
                                            <span className="absolute right-1.5 top-1.5 flex h-2 w-2">
                                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary-400 opacity-75" />
                                                <span className="relative inline-flex h-2 w-2 rounded-full bg-primary-500" />
                                            </span>
                                        )}
                                    </SmartLink>
                                </>
                            )}
                        </div>
                    </header>

                    {notice && (
                        <div className="px-4 pt-4 lg:px-8">
                            <div className={`surface-flat flex items-center gap-2 border-l-4 px-4 py-3 text-sm ${notice[0]}`}>
                                {notice[1]}
                            </div>
                        </div>
                    )}

                    <main className="flex-1 px-4 py-6 lg:px-8">
                        <div className="animate-page-in">{children}</div>
                    </main>

                    <nav className="sticky bottom-0 z-30 flex items-center justify-around border-t border-ink-100 bg-[var(--surface-page)]/95 px-2 py-2 backdrop-blur-xl dark:border-ink-800/70 lg:hidden">
                        <SmartLink href="/feed" className="btn-icon"><Icon name="home" className="h-5 w-5" /></SmartLink>
                        <SmartLink href="/network" className="btn-icon"><Icon name="users" className="h-5 w-5" /></SmartLink>
                        <SmartLink href="/messages" className="btn-icon"><Icon name="message" className="h-5 w-5" /></SmartLink>
                        <SmartLink href="/events" className="btn-icon"><Icon name="calendar" className="h-5 w-5" /></SmartLink>
                        <SmartLink href="/me" className="btn-icon"><Icon name="user" className="h-5 w-5" /></SmartLink>
                    </nav>
                </div>
            </div>
        </>
    );
}
