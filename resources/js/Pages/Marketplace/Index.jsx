import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';
import Pagination from '../../Components/Pagination';
import UserAvatar from '../../Components/UserAvatar';

/** Empty filters are the default, so they do not belong in the address bar. */
function clean(params) {
    return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined));
}

function TabLink({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                active
                    ? 'bg-primary-500 text-white'
                    : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]'
            }`}
        >
            {children}
        </button>
    );
}

function PostForm({ categories, onDone }) {
    const form = useForm({
        category: Object.keys(categories)[0] ?? 'other',
        title: '',
        description: '',
        price: '',
        location: '',
        photos: [],
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/marketplace', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onDone();
            },
        });
    };

    return (
        <div className="surface mb-6 p-6">
            <h2 className="mb-3 text-[15px] font-semibold">Post a marketplace item</h2>

            <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Category</span>
                    <select value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} className="field">
                        {Object.entries(categories).map(([key, label]) => (
                            <option key={key} value={key}>{label}</option>
                        ))}
                    </select>
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Title</span>
                    <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required className="field" />
                </label>

                <textarea
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                    placeholder="Describe condition, details, or offer"
                    required
                    rows={3}
                    className="field sm:col-span-2"
                />

                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Price</span>
                    <input
                        type="number"
                        step="0.01"
                        value={form.data.price}
                        onChange={(e) => form.setData('price', e.target.value)}
                        placeholder="Leave blank if negotiable"
                        className="field"
                    />
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-medium">Location</span>
                    <input
                        value={form.data.location}
                        onChange={(e) => form.setData('location', e.target.value)}
                        placeholder="Campus / area"
                        className="field"
                    />
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
                    <p className="text-sm text-red-600 sm:col-span-2 dark:text-red-300">
                        {Object.values(form.errors)[0]}
                    </p>
                )}

                <button type="submit" disabled={form.processing} className="btn-primary sm:col-span-2">
                    {form.processing ? 'Publishing…' : 'Publish Listing'}
                </button>
            </form>
        </div>
    );
}

function ItemCard({ item }) {
    return (
        <article className="surface surface-hover flex flex-col overflow-hidden">
            <a
                href={item.url}
                aria-label={item.title}
                className="relative block h-40 w-full bg-cover bg-center"
                style={{ backgroundImage: `url('${item.cover_url}')` }}
            >
                {item.gallery_count > 1 && (
                    <span className="absolute bottom-2.5 right-2.5 inline-flex items-center gap-1 rounded-full bg-ink-900/65 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur">
                        <Icon name="image" className="h-3 w-3" />
                        {item.gallery_count}
                    </span>
                )}
                <span className="absolute left-2.5 top-2.5 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-ink-700 shadow-sm backdrop-blur dark:bg-ink-900/80 dark:text-ink-200">
                    {item.category_label}
                </span>
                <span className="absolute bottom-2.5 left-2.5 rounded-full bg-primary-500 px-3 py-1 text-sm font-bold text-white shadow-sm">
                    {item.price_label}
                </span>
            </a>

            <div className="flex flex-1 flex-col p-4">
                <h3 className="truncate text-[15px] font-semibold">
                    <a href={item.url} className="transition hover:text-primary-600 dark:hover:text-primary-300">{item.title}</a>
                </h3>
                <p className="mt-1 flex-1 text-sm text-ink-600 dark:text-ink-300">{item.description}</p>
                <a href={item.url} className="mt-1.5 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-300">
                    View details<Icon name="chevron-right" className="h-4 w-4" />
                </a>

                <div className="mt-3 flex items-center gap-2 border-t border-ink-100 pt-3 text-xs text-ink-500 dark:border-ink-800/70 dark:text-ink-400">
                    <UserAvatar user={item.seller} size="h-6 w-6" text="text-[10px]" />
                    <span className="truncate font-medium">{item.seller.full_name}</span>
                    <span className="ml-auto flex shrink-0 items-center gap-1">
                        <Icon name="map-pin" className="h-3.5 w-3.5" />
                        {item.location}
                    </span>
                </div>

                {item.is_mine ? (
                    <button
                        type="button"
                        onClick={() => router.post(item.sold_url, {}, { preserveScroll: true })}
                        className="btn-secondary mt-3 w-full"
                    >
                        Mark Sold
                    </button>
                ) : (
                    <a href={item.message_url} className="btn-secondary mt-3 w-full">Message Seller</a>
                )}
            </div>
        </article>
    );
}

export default function MarketplaceIndex({ items, categories, filters, total, auth }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState(filters.q);

    useEffect(() => setQ(filters.q), [filters.q]);

    const go = (params) => router.get('/marketplace', clean(params), { preserveState: true, replace: true });

    const filtered = filters.q !== '' || filters.category !== '';

    return (
        <AppLayout title="Marketplace">
            <div className="mx-auto max-w-5xl">
                <div className="section-heading mb-5">
                    <div>
                        <h1>Marketplace</h1>
                        <p className="section-sub">
                            {total} {total === 1 ? 'item' : 'items'} available right now &middot; books, gadgets, roommates, and tuition posts.
                        </p>
                    </div>
                    {auth?.user && (
                        <button type="button" className="btn-primary" onClick={() => setOpen(!open)}>
                            <Icon name="plus" className="h-4 w-4" />
                            Post Item
                        </button>
                    )}
                </div>

                {auth?.user && open && <PostForm categories={categories} onDone={() => setOpen(false)} />}

                <div className="surface mb-5 p-3">
                    <form
                        onSubmit={(e) => { e.preventDefault(); go({ q, category: filters.category }); }}
                        className="relative mb-3"
                    >
                        <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                        <input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search books, laptops, roommates, tuition..."
                            className="field pl-10"
                        />
                    </form>

                    <div className="flex flex-wrap gap-1.5">
                        <TabLink active={filters.category === ''} onClick={() => go({ q: filters.q, category: '' })}>All</TabLink>
                        {Object.entries(categories).map(([key, label]) => (
                            <TabLink key={key} active={filters.category === key} onClick={() => go({ q: filters.q, category: key })}>
                                {label}
                            </TabLink>
                        ))}
                    </div>
                </div>

                {items.data.length === 0 ? (
                    <div className="empty-state">
                        <span className="empty-icon"><Icon name="storefront" className="h-6 w-6" /></span>
                        <h3>{filtered ? 'No matching listings' : 'No listings yet'}</h3>
                        <p>
                            {filtered
                                ? 'Try a different search term or category.'
                                : 'Books, gadgets, and anything else students want to sell will show up here.'}
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {items.data.map((item) => <ItemCard key={item.id} item={item} />)}
                        </div>
                        <div className="mt-6"><Pagination meta={items} /></div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
