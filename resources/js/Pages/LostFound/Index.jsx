import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import Pagination from '../../Components/Pagination';

const KINDS = [['', 'All kinds'], ['lost', 'Lost'], ['found', 'Found']];

/** Empty filters are the default, so they do not belong in the address bar. */
function clean(params) {
    return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined));
}

const BLANK = {
    open: ['Nothing reported yet', 'Lost something on campus, or found an item? Report it and it will show up here.'],
    reunited: ['Nothing reunited yet', 'Items marked as reunited by their posters are kept here as a record.'],
    mine: ['You have not posted anything', 'Anything you report stays here, open or reunited, so you can look it up later.'],
    everything: ['Nothing reported yet', 'Lost something on campus, or found an item? Report it and it will show up here.'],
};

function Tab({ active, onClick, children, count }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium ${
                active ? 'bg-primary-500 text-white' : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]'
            }`}
        >
            {children}
            {count !== undefined && (
                <span className={`rounded-full px-1.5 text-[11px] font-semibold ${active ? 'bg-white/25' : 'bg-ink-900/[0.06] dark:bg-white/[0.08]'}`}>
                    {count}
                </span>
            )}
        </button>
    );
}

function KindTab({ value, label, active, onClick }) {
    const on = value === 'lost' ? 'bg-red-500 text-white' : value === 'found' ? 'bg-emerald-500 text-white' : 'bg-ink-900/[0.06] dark:bg-white/[0.08]';

    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                active ? on : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]'
            }`}
        >
            {label}
        </button>
    );
}

function ReportForm({ onDone }) {
    const form = useForm({ type: 'lost', title: '', description: '', location: '', event_date: '', photos: [] });

    const submit = (event) => {
        event.preventDefault();
        form.post('/lost-found', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { form.reset(); onDone(); },
        });
    };

    return (
        <div className="surface mb-6 p-6">
            <h2 className="mb-3 text-[15px] font-semibold">Report a lost or found item</h2>

            <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Type</span>
                    <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className="field">
                        <option value="lost">Lost item</option>
                        <option value="found">Found item</option>
                    </select>
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Title</span>
                    <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required className="field" />
                </label>

                <textarea
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                    placeholder="Describe item and identifying details"
                    required
                    rows={3}
                    className="field sm:col-span-2"
                />

                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Location</span>
                    <input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} required className="field" />
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Date</span>
                    <input type="date" value={form.data.event_date} onChange={(e) => form.setData('event_date', e.target.value)} className="field" />
                </label>

                <label className="block sm:col-span-2">
                    <span className="mb-1 block text-xs font-medium">Photos</span>
                    <input
                        type="file"
                        accept="image/*"
                        multiple
                        onChange={(e) => form.setData('photos', Array.from(e.target.files))}
                        className="field !py-1.5"
                    />
                </label>

                {Object.keys(form.errors).length > 0 && (
                    <p className="text-sm text-red-600 sm:col-span-2 dark:text-red-300">{Object.values(form.errors)[0]}</p>
                )}

                <button type="submit" disabled={form.processing} className="btn-primary sm:col-span-2">
                    {form.processing ? 'Publishing…' : 'Publish Item'}
                </button>
            </form>
        </div>
    );
}

function ClaimBox({ item }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ message: '' });

    if (item.has_claimed) {
        return <p className="mt-3 text-sm text-primary-600 dark:text-primary-300">Claim sent.</p>;
    }

    if (!open) {
        return (
            <button type="button" className="btn-secondary mt-3 w-full" onClick={() => setOpen(true)}>
                Claim / Contact
            </button>
        );
    }

    return (
        <form
            className="mt-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(item.actions.claim, { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <textarea
                value={form.data.message}
                onChange={(e) => form.setData('message', e.target.value)}
                required
                rows={2}
                placeholder="Explain why this item may be yours or how you can return it"
                className="field"
            />
            <button type="submit" disabled={form.processing} className="btn-primary mt-2 w-full">Send Claim</button>
        </form>
    );
}

function ItemCard({ item, signedIn }) {
    const lost = item.type === 'lost';

    return (
        <article className="surface surface-hover overflow-hidden">
            <a
                href={item.url}
                aria-label={item.title}
                className="relative block h-40 w-full bg-cover bg-center"
                style={{ backgroundImage: `url('${item.cover_url}')` }}
            >
                {item.gallery_count > 1 && (
                    <span className="absolute bottom-3 right-3 inline-flex items-center gap-1 rounded-full bg-ink-900/65 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur">
                        <Icon name="image" className="h-3 w-3" />
                        {item.gallery_count}
                    </span>
                )}
                <span className={`absolute left-0 top-3 rounded-r-full py-1 pl-3 pr-3.5 text-xs font-bold uppercase tracking-wide text-white shadow-sm ${lost ? 'bg-red-500' : 'bg-emerald-500'}`}>
                    {item.type}
                </span>
                {!item.is_open && (
                    <span className="absolute right-3 top-3 rounded-full bg-ink-900/85 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white backdrop-blur">
                        Reunited
                    </span>
                )}
            </a>

            <div className="p-5">
                <h3 className="text-[15px] font-semibold">
                    <a href={item.url} className="transition hover:text-primary-600 dark:hover:text-primary-300">{item.title}</a>
                </h3>
                <p className="mt-1 text-sm text-ink-600 dark:text-ink-300">{item.description}</p>
                <a href={item.url} className="mt-1.5 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-300">
                    View details<Icon name="chevron-right" className="h-4 w-4" />
                </a>

                <div className="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                    <span className="inline-flex items-center gap-1"><Icon name="map-pin" className="h-3.5 w-3.5" />{item.location}</span>
                    {item.event_date && (
                        <span className="inline-flex items-center gap-1"><Icon name="calendar" className="h-3.5 w-3.5" />{item.event_date}</span>
                    )}
                    <span className="inline-flex items-center gap-1"><Icon name="clock" className="h-3.5 w-3.5" />{item.posted_ago}</span>
                </div>
                <p className="mt-2 text-xs text-ink-400">Posted by {item.poster}</p>

                {!item.is_open && (
                    <p className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-300">
                        <Icon name="check-circle" className="h-3.5 w-3.5" />
                        Reunited{item.resolved_on ? ` on ${item.resolved_on}` : ''}
                    </p>
                )}

                {signedIn && (item.can_manage ? (
                    <button
                        type="button"
                        className="btn-secondary mt-3 w-full"
                        onClick={() => router.post(item.is_open ? item.actions.resolve : item.actions.reopen, {}, { preserveScroll: true })}
                    >
                        {item.is_open ? 'Mark Reunited' : 'Reopen Listing'}
                    </button>
                ) : item.is_open ? (
                    <ClaimBox item={item} />
                ) : null)}
            </div>
        </article>
    );
}

export default function LostFoundIndex({ items, filters, counts, can, auth }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState(filters.q);

    useEffect(() => setQ(filters.q), [filters.q]);

    const go = (params) => router.get('/lost-found', clean(params), { preserveState: true, replace: true });

    const lists = [
        ['open', 'Open', counts.open],
        ['reunited', 'Reunited', counts.reunited],
        ...(can.see_mine ? [['mine', 'My posts', counts.mine]] : []),
        ...(can.see_everything ? [['everything', 'Everything', undefined]] : []),
    ];

    const [blankTitle, blankLine] = BLANK[filters.view] ?? BLANK.open;
    const filtered = filters.q !== '' || filters.type !== '';

    return (
        <AppLayout title="Lost & Found">
            <div className="mx-auto max-w-5xl">
                <div className="section-heading mb-5">
                    <div>
                        <h1>Lost &amp; Found</h1>
                        <p className="section-sub">Reunite misplaced items with their owners — post with a photo, location, and date.</p>
                    </div>
                    {can.post && (
                        <button type="button" className="btn-primary" onClick={() => setOpen(!open)}>
                            <Icon name="plus" className="h-4 w-4" />
                            Report Item
                        </button>
                    )}
                </div>

                {can.post && open && <ReportForm onDone={() => setOpen(false)} />}

                <div className="surface mb-5 p-3">
                    <form
                        onSubmit={(e) => { e.preventDefault(); go({ ...filters, q }); }}
                        className="relative mb-3"
                    >
                        <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                        <input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search by item, location, or description"
                            className="field pl-10"
                        />
                    </form>

                    <div className="flex flex-wrap items-center gap-1.5">
                        {lists.map(([key, label, count]) => (
                            <Tab
                                key={key}
                                active={filters.view === key}
                                count={count}
                                onClick={() => go({ ...filters, view: key })}
                            >
                                {label}
                            </Tab>
                        ))}

                        <span className="mx-1 hidden h-5 w-px bg-ink-100 dark:bg-ink-800 sm:block" />

                        {KINDS.map(([value, label]) => (
                            <KindTab
                                key={value || 'all'}
                                value={value}
                                label={label}
                                active={filters.type === value}
                                onClick={() => go({ ...filters, type: value })}
                            />
                        ))}
                    </div>
                </div>

                {items.data.length === 0 ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="inbox" className="h-6 w-6" /></span>
                        <h3>{filtered ? 'Nothing matches' : blankTitle}</h3>
                        <p>{filtered ? 'Try a different search term or switch tabs.' : blankLine}</p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {items.data.map((item) => (
                                <ItemCard key={item.id} item={item} signedIn={Boolean(auth?.user)} />
                            ))}
                        </div>
                        <div className="mt-6"><Pagination meta={items} /></div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
