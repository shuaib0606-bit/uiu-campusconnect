import { Link } from '@inertiajs/react';

const PILL = 'inline-flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm font-medium transition';
const IDLE = 'text-ink-600 hover:bg-ink-900/[0.06] hover:text-ink-900 dark:text-ink-300 dark:hover:bg-white/[0.08] dark:hover:text-white';
const MUTED = 'cursor-not-allowed text-ink-300 dark:text-ink-600';
const ACTIVE = 'bg-primary-500 text-white shadow-[0_6px_16px_-8px_var(--color-primary-500)]';

/** Matches the Blade pagination partial, so both kinds of page look alike. */
export default function Pagination({ meta }) {
    if (!meta || meta.last_page < 2) {
        return null;
    }

    // Laravel puts Previous and Next at the ends of the same list.
    const numbers = meta.links.slice(1, -1);
    const previous = meta.links[0];
    const next = meta.links[meta.links.length - 1];

    return (
        <nav role="navigation" aria-label="Pagination" className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-ink-500 dark:text-ink-400">
                Showing {meta.from ?? 0} to {meta.to ?? 0} of {meta.total}
            </p>

            <div className="flex items-center gap-1">
                {previous.url ? (
                    <Link href={previous.url} rel="prev" preserveScroll className={`${PILL} ${IDLE}`}>Previous</Link>
                ) : (
                    <span className={`${PILL} ${MUTED}`} aria-disabled="true">Previous</span>
                )}

                <span className="hidden items-center gap-1 sm:flex">
                    {numbers.map((link, i) => {
                        if (!link.url) {
                            return (
                                <span key={`gap-${i}`} className="px-1.5 text-sm text-ink-400 dark:text-ink-500">
                                    {link.label}
                                </span>
                            );
                        }

                        return link.active ? (
                            <span key={link.label} className={`${PILL} ${ACTIVE}`} aria-current="page">{link.label}</span>
                        ) : (
                            <Link key={link.label} href={link.url} preserveScroll className={`${PILL} ${IDLE}`}>{link.label}</Link>
                        );
                    })}
                </span>

                <span className={`${PILL} text-ink-500 dark:text-ink-400 sm:hidden`}>
                    {meta.current_page} / {meta.last_page}
                </span>

                {next.url ? (
                    <Link href={next.url} rel="next" preserveScroll className={`${PILL} ${IDLE}`}>Next</Link>
                ) : (
                    <span className={`${PILL} ${MUTED}`} aria-disabled="true">Next</span>
                )}
            </div>
        </nav>
    );
}
