import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import Icon from './Icon';

/**
 * "UIU Official Sites": the same dialog as the Blade sidebar's
 * (components/uiu-sites.blade.php). The list comes from the server as the
 * shared `uiuSites` prop, built by App\Support\UiuSites.
 */
const TONES = {
    sky: { icon: 'bg-sky-500/12 text-sky-600 dark:text-sky-300', button: 'bg-sky-600 hover:bg-sky-700', dot: 'bg-sky-500' },
    emerald: { icon: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-300', button: 'bg-emerald-600 hover:bg-emerald-700', dot: 'bg-emerald-500' },
    rose: { icon: 'bg-rose-500/12 text-rose-600 dark:text-rose-300', button: 'bg-rose-600 hover:bg-rose-700', dot: 'bg-rose-500' },
};

export default function UiuSites({ sites = [] }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);
        document.documentElement.classList.add('overflow-hidden');
        return () => {
            window.removeEventListener('keydown', onKey);
            document.documentElement.classList.remove('overflow-hidden');
        };
    }, [open]);

    if (!sites.length) return null;

    return (
        <>
            <button type="button" onClick={() => setOpen(true)} className="nav-link w-full" aria-haspopup="dialog">
                <Icon name="globe" className="h-5 w-5" />
                <span className="flex-1 text-left">UIU Official Sites</span>
                <Icon name="chevron-right" className="h-4 w-4 text-ink-400" />
            </button>

            {open && createPortal(
                <div className="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-labelledby="uiu-sites-title">
                    <div className="absolute inset-0 bg-black/40 backdrop-blur-[2px]" onClick={() => setOpen(false)} />

                    <div
                        className="relative max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-3xl border p-6 shadow-2xl sm:p-8"
                        style={{ background: 'var(--surface-page)', borderColor: 'var(--hairline)' }}
                    >
                        <div className="mb-6 flex items-start justify-between gap-4">
                            <div>
                                <h2 id="uiu-sites-title" className="text-xl font-bold tracking-tight">UIU official sites</h2>
                                <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">
                                    The university&apos;s own systems. Each opens in a new tab and uses your UIU sign-in, not your CampusConnect one.
                                </p>
                            </div>
                            <button type="button" className="btn-icon shrink-0" onClick={() => setOpen(false)} aria-label="Close">
                                <Icon name="x" className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            {sites.map((site) => {
                                const t = TONES[site.tone] ?? TONES.sky;

                                return (
                                    <a
                                        key={site.key}
                                        href={site.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="group flex flex-col rounded-2xl border p-5 transition hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                        style={{ background: 'var(--surface-card)', borderColor: 'var(--hairline)' }}
                                    >
                                        <span className={`grid h-12 w-12 place-items-center rounded-2xl ${t.icon}`}>
                                            <Icon name={site.icon} className="h-6 w-6" />
                                        </span>

                                        <span className="mt-4 text-[17px] font-bold">{site.name}</span>
                                        <span className="text-xs font-medium text-ink-400">{site.host}</span>
                                        <span className="mt-2 text-sm font-semibold text-ink-700 dark:text-ink-200">{site.tagline}</span>

                                        <ul className="mt-3 space-y-1.5 text-[13px] text-ink-600 dark:text-ink-300">
                                            {site.uses.map((use) => (
                                                <li key={use} className="flex items-start gap-2">
                                                    <span className={`mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full ${t.dot}`} />
                                                    {use}
                                                </li>
                                            ))}
                                        </ul>

                                        {site.hint && (
                                            <span className="mt-4 flex items-start gap-1.5 rounded-xl bg-ink-900/[0.04] px-3 py-2 text-xs text-ink-600 dark:bg-white/[0.05] dark:text-ink-300">
                                                <Icon name="calendar-days" className="mt-px h-3.5 w-3.5 shrink-0" />
                                                <span>{site.hint}</span>
                                            </span>
                                        )}

                                        <span className="mt-auto pt-5">
                                            <span className={`inline-flex w-full items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition ${t.button}`}>
                                                Open {site.name} <Icon name="external" className="h-4 w-4" />
                                            </span>
                                        </span>
                                    </a>
                                );
                            })}
                        </div>

                        <p className="mt-6 flex items-start gap-2 text-xs text-ink-400">
                            <Icon name="lock" className="mt-px h-3.5 w-3.5 shrink-0" />
                            CampusConnect is not connected to these sites and never asks for your UIU password. Only type it on the sites themselves.
                        </p>
                    </div>
                </div>,
                document.body,
            )}
        </>
    );
}
