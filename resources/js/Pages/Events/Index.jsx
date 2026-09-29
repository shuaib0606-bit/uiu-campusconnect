import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';

const SMALL = '!px-3 !py-1.5 text-xs';

function CreateForm({ clubs, onDone }) {
    const form = useForm({
        title: '',
        category: '',
        description: '',
        start_datetime: '',
        end_datetime: '',
        location: '',
        capacity: 80,
        registration_url: '',
        club_id: '',
        posters: [],
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/events', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { form.reset(); onDone(); },
        });
    };

    const field = (name) => ({
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        className: 'field',
    });

    return (
        <div className="surface mb-5 p-6">
            <h2 className="text-[15px] font-semibold">Create a campus event</h2>

            <form onSubmit={submit} className="mt-4 space-y-3">
                <div className="grid grid-cols-2 gap-2">
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Title</span>
                        <input {...field('title')} placeholder="Example: AI Workshop" required />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Category</span>
                        <input {...field('category')} placeholder="Workshop / Career / Club Activity" required />
                    </label>
                </div>

                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Description</span>
                    <textarea {...field('description')} rows={3} required placeholder="Briefly explain what participants will get from this event." />
                </label>

                <div className="grid grid-cols-2 gap-2">
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Start</span>
                        <input type="datetime-local" {...field('start_datetime')} required />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">End</span>
                        <input type="datetime-local" {...field('end_datetime')} required />
                    </label>
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Location</span>
                        <input {...field('location')} placeholder="Auditorium / Room / Online" required />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Capacity</span>
                        <input type="number" min="1" {...field('capacity')} />
                    </label>
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Registration link</span>
                        <input {...field('registration_url')} placeholder="https://..." />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium">Event pictures</span>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            multiple
                            onChange={(e) => form.setData('posters', Array.from(e.target.files))}
                            className="field !py-1.5"
                        />
                    </label>
                </div>

                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Post as</span>
                    <select {...field('club_id')}>
                        <option value="">University-level event</option>
                        {clubs.map((club) => <option key={club.id} value={club.id}>{club.name}</option>)}
                    </select>
                </label>

                {Object.keys(form.errors).length > 0 && (
                    <p className="text-sm text-red-600 dark:text-red-300">{Object.values(form.errors)[0]}</p>
                )}

                <button type="submit" disabled={form.processing} className="btn-primary w-full">
                    {form.processing ? 'Publishing…' : 'Publish Event'}
                </button>
            </form>
        </div>
    );
}

function EventCard({ event, signedIn }) {
    const post = (url) => router.post(url, {}, { preserveScroll: true });

    return (
        <article className="surface flex flex-col gap-4 p-5 sm:flex-row">
            <a href={event.url} className="block h-32 w-full shrink-0 overflow-hidden rounded-xl sm:h-auto sm:w-40">
                <img src={event.cover_url} alt={`Poster for ${event.title}`} className="h-full w-full object-cover" />
            </a>

            <div className="min-w-0 flex-1">
                <div className="flex items-start gap-3">
                    <div className="flex w-11 shrink-0 flex-col items-center rounded-xl bg-ink-900/[0.04] py-1.5 dark:bg-white/[0.06]">
                        <span className="text-[10px] font-semibold uppercase text-ink-400">{event.month}</span>
                        <span className="text-base font-bold">{event.day}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap gap-1.5">
                            <span className="chip">{event.category}</span>
                            {event.club_name && (
                                <span className="chip !bg-blue-500/10 !text-blue-700 dark:!text-blue-300">{event.club_name}</span>
                            )}
                        </div>
                        <h2 className="mt-1 truncate text-[15px] font-semibold">
                            <a href={event.url} className="hover:underline">{event.title}</a>
                        </h2>
                        <p className="section-sub">Hosted by {event.host}</p>
                    </div>
                </div>

                <p className="mt-2 text-sm text-ink-600 dark:text-ink-300">{event.description}</p>

                <div className="mt-2 flex flex-wrap gap-4 text-xs text-ink-500 dark:text-ink-400">
                    <span className="inline-flex items-center gap-1"><Icon name="calendar" className="h-3.5 w-3.5" />{event.date_label}</span>
                    <span className="inline-flex items-center gap-1"><Icon name="map-pin" className="h-3.5 w-3.5" />{event.location}</span>
                </div>

                <div className="mt-2">
                    <div className="flex justify-between text-xs text-ink-500 dark:text-ink-400">
                        <span>{event.registrants_count} registered</span>
                        <span>{event.capacity} seats</span>
                    </div>
                    <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-ink-900/[0.06] dark:bg-white/[0.08]">
                        <span className="block h-full rounded-full bg-primary-500" style={{ width: `${event.fill_percent}%` }} />
                    </div>
                </div>

                <div className="mt-2 flex gap-3 text-xs text-ink-500 dark:text-ink-400">
                    <span>{event.interested_count} interested</span>
                </div>

                <div className="mt-3 flex flex-wrap gap-2">
                    <a href={event.url} className={`btn-secondary ${SMALL}`}>Details</a>

                    {signedIn && (
                        <>
                            {event.is_registered ? (
                                <button type="button" onClick={() => post(event.actions.unregister)} className={`btn-secondary ${SMALL}`}>
                                    Cancel registration
                                </button>
                            ) : event.is_full ? (
                                <span className="chip">Full</span>
                            ) : (
                                <button type="button" onClick={() => post(event.actions.register)} className={`btn-primary ${SMALL}`}>
                                    Register
                                </button>
                            )}

                            <button
                                type="button"
                                onClick={() => post(event.actions.interest)}
                                className={`${event.is_interested ? 'btn-primary' : 'btn-secondary'} ${SMALL}`}
                            >
                                {event.is_interested ? 'Interested ✓' : 'Interested'}
                            </button>
                        </>
                    )}
                </div>
            </div>
        </article>
    );
}

export default function EventsIndex({ events, filters, categories, clubs, canCreateEvent, auth }) {
    const [open, setOpen] = useState(false);

    const go = (type) => router.get('/events', type ? { type } : {}, { preserveState: true, replace: true });

    return (
        <AppLayout title="Events">
            <div className="mx-auto max-w-4xl">
                <div className="surface mb-5 flex flex-wrap items-center justify-between gap-4 p-6">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">Campus activities</p>
                        <h1 className="mt-1 text-[22px] font-semibold tracking-tight">Events</h1>
                        <p className="section-sub mt-1 max-w-md">
                            Find workshops, club activities, academic sessions, and university-wide events in one clean place.
                        </p>
                    </div>
                    {canCreateEvent && (
                        <button type="button" className="btn-primary shrink-0" onClick={() => setOpen(!open)}>
                            {open ? 'Close' : 'Create Event'}
                        </button>
                    )}
                </div>

                <div className="surface-flat mb-5 flex flex-wrap gap-1.5 p-2">
                    {[['', 'All'], ...categories.map((c) => [c, c])].map(([value, label]) => (
                        <button
                            key={value || 'all'}
                            type="button"
                            onClick={() => go(value)}
                            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                                filters.type === value ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {canCreateEvent && open && <CreateForm clubs={clubs} onDone={() => setOpen(false)} />}

                {events.length === 0 ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="calendar-star" className="h-6 w-6" /></span>
                        <h3>No events found</h3>
                        <p>Try another category or check again later.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {events.map((event) => (
                            <EventCard key={event.id} event={event} signedIn={Boolean(auth?.user)} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
