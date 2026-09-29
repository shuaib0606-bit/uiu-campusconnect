import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Icon from '../../Components/Icon';

/**
 * The campus assistant.
 *
 * A request here can take a few seconds: the model is called, it asks for
 * data, the tools run, and it is called again. So the question is echoed
 * into the thread the moment it is sent and a thinking row sits under it
 * until the answer arrives. Without that the page looks broken for five
 * seconds, which on a demo recording reads as a bug.
 */

/**
 * The little markdown models write: **bold**, [links](/profile/k3v9x0qz7m), lists,
 * headings and small tables.
 *
 * Everything is turned into React elements - never innerHTML - so text
 * that came from somebody's post or listing cannot become markup. Links
 * are the one thing that could carry the reader somewhere, so only two
 * kinds are made clickable: a path on this site ("/profile/k3v9x0qz7m", never
 * "//elsewhere") and a mailto: for a plain email address. Anything else
 * a model writes as a link is shown as its text alone.
 */
const SAFE_PATH = /^\/(?!\/)[\w\-./?=&%#:]*$/;
const EMAIL = /^[\w.+-]+@[\w-]+\.[\w.-]+$/;
const INLINE = /(\[[^\]\n]+\]\([^)\s]+\)|\*\*[^*\n]+\*\*|[\w.+-]+@[\w-]+\.[\w-]+(?:\.[\w-]+)*)/g;

const linkClass =
    'font-medium text-primary-600 underline decoration-primary-300 underline-offset-2 hover:text-primary-700 dark:text-primary-400 dark:decoration-primary-700';

function safeHref(href) {
    // Models sometimes put a host in front of a path they were given
    // ("http://127.0.0.1:8000/profile/..."). Only this site's own origin
    // is accepted, and it is reduced to the path.
    if (/^https?:\/\//i.test(href)) {
        try {
            const url = new URL(href);
            if (url.origin !== window.location.origin) return null;
            href = url.pathname + url.search + url.hash;
        } catch {
            return null;
        }
    }

    if (SAFE_PATH.test(href)) return href;
    if (href.startsWith('mailto:') && EMAIL.test(href.slice(7))) return href;
    return null;
}

/** A link whose visible text is itself an address reads as noise; name it instead. */
function linkText(text, href) {
    if (!/^(https?:\/\/|\/)/i.test(text.trim())) return text;
    if (href.startsWith('/profile/')) return 'View profile';
    return 'Open';
}

function Inline({ text }) {
    return String(text).split(INLINE).map((part, i) => {
        if (!part) return null;

        const link = part.match(/^\[([^\]\n]+)\]\(([^)\s]+)\)$/);
        if (link) {
            const href = safeHref(link[2]);
            return href
                ? <a key={i} href={href} className={linkClass}>{linkText(link[1], href)}</a>
                : <span key={i}>{/^(https?:\/\/|\/)/i.test(link[1].trim()) ? '' : link[1]}</span>;
        }

        if (part.length > 4 && part.startsWith('**') && part.endsWith('**')) {
            return <strong key={i} className="font-semibold">{part.slice(2, -2)}</strong>;
        }

        // A bare address becomes a mail link. The output guard has already
        // removed any address a tool did not return.
        if (EMAIL.test(part)) {
            return <a key={i} href={`mailto:${part}`} className={linkClass}>{part}</a>;
        }

        return <span key={i}>{part}</span>;
    });
}

const cells = (line) => line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((c) => c.trim());
const isDivider = (line) => /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(line);

function Table({ lines }) {
    const rows = lines.filter((l) => !isDivider(l)).map(cells);
    const [head, ...body] = rows;

    return (
        <div className="my-2 overflow-x-auto">
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        {head.map((c, i) => (
                            <th key={i} className="border-b border-ink-300 px-2 py-1.5 text-left font-semibold dark:border-ink-600">
                                <Inline text={c} />
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {body.map((row, r) => (
                        <tr key={r}>
                            {row.map((c, i) => (
                                <td key={i} className="border-b border-ink-200 px-2 py-1.5 align-top dark:border-ink-700">
                                    <Inline text={c} />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Rich({ text }) {
    const lines = String(text ?? '').split('\n');
    const blocks = [];

    for (let i = 0; i < lines.length; i++) {
        // Consecutive lines starting with "|" are one table.
        if (lines[i].trim().startsWith('|')) {
            const start = i;
            while (i + 1 < lines.length && lines[i + 1].trim().startsWith('|')) i++;
            blocks.push({ table: lines.slice(start, i + 1) });
            continue;
        }

        const line = lines[i]
            .replace(/^[ \t]*[*-] /, '• ')
            .replace(/^#{1,6}\s+(.*)$/, '**$1**');

        const last = blocks[blocks.length - 1];
        if (last && 'text' in last) last.text += '\n' + line;
        else blocks.push({ text: line });
    }

    return blocks.map((block, i) =>
        block.table
            ? <Table key={i} lines={block.table} />
            : <p key={i} className="whitespace-pre-wrap leading-relaxed"><Inline text={block.text.replace(/^\n+|\n+$/g, '')} /></p>,
    );
}

function Bubble({ role, body, tools }) {
    const mine = role === 'user';

    return (
        <div className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
            <div
                className={
                    mine
                        ? 'max-w-[85%] rounded-2xl rounded-br-md bg-primary-600 px-4 py-2.5 text-[15px] text-white'
                        : 'max-w-[85%] rounded-2xl rounded-bl-md bg-ink-100 px-4 py-2.5 text-[15px] dark:bg-ink-800'
                }
            >
                {mine
                    ? <p className="whitespace-pre-wrap leading-relaxed">{body}</p>
                    : <div className="space-y-1"><Rich text={body} /></div>}

                {!mine && tools?.length > 0 && (
                    // Naming what was read is the honest version of a
                    // citation: the student can see the answer came from
                    // their own timetable and not from the model's memory.
                    <p className="mt-2 border-t border-ink-200 pt-2 text-xs text-ink-500 dark:border-ink-700 dark:text-ink-400">
                        Checked: {tools.map((t) => t.replace(/_/g, ' ')).join(', ')}
                    </p>
                )}
            </div>
        </div>
    );
}

function Thinking() {
    return (
        <div className="flex justify-start">
            <div className="rounded-2xl rounded-bl-md bg-ink-100 px-4 py-3 dark:bg-ink-800">
                <span className="flex gap-1.5">
                    {[0, 150, 300].map((delay) => (
                        <span
                            key={delay}
                            className="h-2 w-2 animate-bounce rounded-full bg-ink-400"
                            style={{ animationDelay: `${delay}ms` }}
                        />
                    ))}
                </span>
            </div>
        </div>
    );
}

/** "Today", "Yesterday", "Previous 7 days", ... - how a long list stays findable. */
function bucketOf(iso) {
    if (!iso) return 'Older';
    const day = new Date(iso);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const that = new Date(day);
    that.setHours(0, 0, 0, 0);
    const days = Math.round((today - that) / 86400000);

    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 7) return 'Previous 7 days';
    if (days < 30) return 'Previous 30 days';
    return day.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
}

/**
 * Every past conversation. Opening one shows it in full and the next
 * question continues it - the model gets that conversation's recent turns
 * as context, exactly as if it had never been left.
 */
function ChatList({ conversations, currentId, onPick }) {
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState(null);
    const [title, setTitle] = useState('');
    const [confirming, setConfirming] = useState(null);

    const needle = query.trim().toLowerCase();
    const shown = needle ? conversations.filter((c) => c.title.toLowerCase().includes(needle)) : conversations;

    const groups = [];
    for (const row of shown) {
        const label = bucketOf(row.updated);
        const last = groups[groups.length - 1];
        if (last && last.label === label) last.rows.push(row);
        else groups.push({ label, rows: [row] });
    }

    const startRename = (row) => {
        setConfirming(null);
        setEditing(row.id);
        setTitle(row.title);
    };

    const saveRename = (row) => {
        const next = title.trim();
        if (!next || next === row.title) {
            setEditing(null);
            return;
        }
        router.patch(`/assistant/${row.id}`, { title: next }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(null),
        });
    };

    const remove = (row) => {
        router.delete(`/assistant/${row.id}`, {
            data: { stay: row.id !== currentId },
            preserveScroll: true,
            preserveState: row.id !== currentId,
            onFinish: () => setConfirming(null),
        });
    };

    return (
        <div className="flex h-full min-h-0 flex-col">
            <Link
                href="/assistant"
                onClick={onPick}
                className="btn-primary mb-3 flex items-center justify-center gap-2 rounded-xl py-2.5"
            >
                <Icon name="plus" className="h-4 w-4" /> New chat
            </Link>

            {conversations.length > 5 && (
                <div className="relative mb-3">
                    <Icon name="search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search chats"
                        className="field !py-2 !pl-9 text-sm"
                        aria-label="Search chats"
                    />
                </div>
            )}

            <nav className="-mx-1 min-h-0 flex-1 space-y-4 overflow-y-auto px-1 pb-2" aria-label="Your conversations">
                {conversations.length === 0 && (
                    <p className="px-2 text-sm text-ink-500 dark:text-ink-400">Your conversations will be listed here, and you can come back to any of them.</p>
                )}
                {conversations.length > 0 && shown.length === 0 && (
                    <p className="px-2 text-sm text-ink-500 dark:text-ink-400">No chat matches "{query}".</p>
                )}

                {groups.map((group) => (
                    <div key={group.label}>
                        <h3 className="mb-1 px-2 text-[11px] font-semibold uppercase tracking-wider text-ink-400">{group.label}</h3>
                        <ul className="space-y-0.5">
                            {group.rows.map((row) => {
                                const active = row.id === currentId;

                                if (editing === row.id) {
                                    return (
                                        <li key={row.id}>
                                            <form
                                                onSubmit={(e) => { e.preventDefault(); saveRename(row); }}
                                                className="flex items-center gap-1 rounded-xl bg-ink-100 p-1 dark:bg-ink-800"
                                            >
                                                <input
                                                    autoFocus
                                                    value={title}
                                                    maxLength={110}
                                                    onChange={(e) => setTitle(e.target.value)}
                                                    onKeyDown={(e) => e.key === 'Escape' && setEditing(null)}
                                                    className="field min-w-0 flex-1 !py-1.5 text-sm"
                                                    aria-label="Chat name"
                                                />
                                                <button type="submit" className="btn-icon" title="Save name"><Icon name="check" className="h-4 w-4" /></button>
                                                <button type="button" className="btn-icon" title="Cancel" onClick={() => setEditing(null)}><Icon name="x" className="h-4 w-4" /></button>
                                            </form>
                                        </li>
                                    );
                                }

                                if (confirming === row.id) {
                                    return (
                                        <li key={row.id} className="rounded-xl bg-red-500/[0.07] p-2 text-sm">
                                            <p className="mb-2 truncate">Delete "{row.title}"?</p>
                                            <div className="flex gap-2">
                                                <button type="button" onClick={() => remove(row)} className="rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold text-white hover:bg-red-700">Delete</button>
                                                <button type="button" onClick={() => setConfirming(null)} className="rounded-lg px-3 py-1 text-xs font-semibold hover:bg-ink-900/[0.05] dark:hover:bg-white/[0.06]">Cancel</button>
                                            </div>
                                        </li>
                                    );
                                }

                                return (
                                    <li key={row.id} className="group relative">
                                        <Link
                                            href={row.url}
                                            onClick={onPick}
                                            preserveScroll
                                            className={`block rounded-xl py-2 pl-3 pr-16 text-sm transition ${
                                                active
                                                    ? 'bg-primary-500/10 font-medium text-primary-700 dark:text-primary-300'
                                                    : 'text-ink-700 hover:bg-ink-900/[0.04] dark:text-ink-200 dark:hover:bg-white/[0.05]'
                                            }`}
                                            title={row.title}
                                        >
                                            <span className="block truncate">{row.title}</span>
                                            <span className="block text-[11px] text-ink-400">{row.at}</span>
                                        </Link>
                                        <span className={`absolute right-1.5 top-1/2 flex -translate-y-1/2 gap-0.5 transition ${active ? 'opacity-100' : 'opacity-100 lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100'}`}>
                                            <button type="button" onClick={() => startRename(row)} className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-900/[0.06] hover:text-ink-700 dark:hover:bg-white/[0.08] dark:hover:text-ink-100" title="Rename" aria-label={`Rename ${row.title}`}>
                                                <Icon name="edit" className="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" onClick={() => { setEditing(null); setConfirming(row.id); }} className="rounded-lg p-1.5 text-ink-400 hover:bg-red-500/10 hover:text-red-600" title="Delete" aria-label={`Delete ${row.title}`}>
                                                <Icon name="trash" className="h-3.5 w-3.5" />
                                            </button>
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>
        </div>
    );
}

export default function AssistantIndex({
    conversationId,
    conversationTitle,
    messages,
    conversations,
    available,
    personaName,
    suggestions,
    maxLength,
}) {
    const { flash } = usePage().props;
    const [pending, setPending] = useState(null);
    const [drawer, setDrawer] = useState(false);

    // Hiding the chat list gives a long answer the whole width. The choice
    // is remembered on this browser; storage can be unavailable.
    const [listOpen, setListOpen] = useState(() => {
        try {
            return window.localStorage.getItem('cc.assistant.list') !== 'hidden';
        } catch {
            return true;
        }
    });

    const toggleList = () => {
        setListOpen((open) => {
            try {
                window.localStorage.setItem('cc.assistant.list', open ? 'hidden' : 'shown');
            } catch {}
            return !open;
        });
    };
    const endRef = useRef(null);

    const form = useForm({ question: '', conversation_id: conversationId ?? null });

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length, pending]);

    // A server error leaves the optimistic bubble stranded, so it is
    // cleared whenever a flash message arrives.
    useEffect(() => {
        if (flash?.error) setPending(null);
    }, [flash?.error]);

    const send = (text) => {
        const question = (text ?? form.data.question).trim();

        if (!question || form.processing || !available) return;

        setPending(question);

        router.post(
            '/assistant',
            { question, conversation_id: conversationId ?? null },
            {
                preserveScroll: true,
                onFinish: () => {
                    setPending(null);
                    form.setData('question', '');
                },
            },
        );
    };

    const onKeyDown = (event) => {
        // Enter sends, Shift+Enter makes a new line - what every chat box does.
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            send();
        }
    };

    return (
        <AppLayout title="Assistant">
            <div className="mx-auto flex h-[calc(100svh-8rem)] w-full max-w-6xl gap-6">

            {/* Past conversations: a column on large screens, a drawer on phones. */}
            <aside className={`hidden w-72 shrink-0 border-r border-ink-200 pr-5 dark:border-ink-800 ${listOpen ? 'lg:block' : ''}`}>
                <ChatList conversations={conversations} currentId={conversationId} />
            </aside>

            {drawer && (
                <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Your conversations">
                    <button type="button" className="absolute inset-0 bg-black/40" onClick={() => setDrawer(false)} aria-label="Close" />
                    <div className="absolute inset-y-0 left-0 flex w-[85%] max-w-xs flex-col bg-white p-4 shadow-xl dark:bg-ink-900">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Your chats</h2>
                            <button type="button" className="btn-icon" onClick={() => setDrawer(false)} aria-label="Close"><Icon name="x" className="h-5 w-5" /></button>
                        </div>
                        <ChatList conversations={conversations} currentId={conversationId} onPick={() => setDrawer(false)} />
                    </div>
                </div>
            )}

            <div className={`mx-auto flex min-w-0 flex-1 flex-col ${listOpen ? 'max-w-3xl' : 'max-w-4xl'}`}>

                <header className="flex items-center justify-between gap-3 pb-4">
                    <div className="flex items-center gap-2.5">
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-primary-500 text-white">
                            <Icon name="sparkles" className="h-5 w-5" />
                        </span>
                        <div>
                            <h1 className="text-lg font-semibold tracking-tight">{personaName}</h1>
                            <p className="text-xs text-ink-500 dark:text-ink-400">
                                Answers from your own courses, notices and campus pages.
                            </p>
                        </div>
                    </div>

                    <div className="hidden items-center gap-2 lg:flex">
                        {!listOpen && (
                            <Link
                                href="/assistant"
                                className="inline-flex items-center gap-1.5 rounded-xl border border-ink-200 px-3 py-1.5 text-sm font-medium transition hover:bg-ink-50 dark:border-ink-700 dark:hover:bg-ink-800"
                            >
                                <Icon name="plus" className="h-4 w-4" /> New chat
                            </Link>
                        )}
                        <button
                            type="button"
                            onClick={toggleList}
                            aria-pressed={!listOpen}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-ink-200 px-3 py-1.5 text-sm font-medium transition hover:bg-ink-50 dark:border-ink-700 dark:hover:bg-ink-800"
                            title={listOpen ? 'Hide the chat list to give answers more room' : 'Show your chats'}
                        >
                            <Icon name={listOpen ? 'chevron-left' : 'chevron-right'} className="h-4 w-4" />
                            {listOpen ? 'Hide chats' : `Show chats${conversations.length > 0 ? ` (${conversations.length})` : ''}`}
                        </button>
                    </div>

                    <div className="flex items-center gap-2 lg:hidden">
                        <button
                            type="button"
                            onClick={() => setDrawer(true)}
                            className="rounded-xl border border-ink-200 px-3 py-1.5 text-sm font-medium transition hover:bg-ink-50 dark:border-ink-700 dark:hover:bg-ink-800"
                        >
                            Chats{conversations.length > 0 ? ` (${conversations.length})` : ''}
                        </button>
                        {conversationId && (
                            <Link
                                href="/assistant"
                                className="rounded-xl border border-ink-200 px-3 py-1.5 text-sm font-medium transition hover:bg-ink-50 dark:border-ink-700 dark:hover:bg-ink-800"
                            >
                                New chat
                            </Link>
                        )}
                    </div>
                </header>

                {conversationTitle && (
                    <p className="-mt-1 mb-3 truncate border-b border-ink-200 pb-3 text-sm font-medium text-ink-600 dark:border-ink-800 dark:text-ink-300" title={conversationTitle}>
                        {conversationTitle}
                    </p>
                )}

                {!available && (
                    <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                        The assistant is not configured yet. An administrator needs to add an API key.
                    </div>
                )}

                {flash?.error && (
                    <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                        {flash.error}
                    </div>
                )}

                <div className="flex-1 space-y-3 overflow-y-auto pb-4">
                    {messages.length === 0 && !pending && (
                        <div className="py-10 text-center">
                            <p className="text-[15px] text-ink-500 dark:text-ink-400">
                                Ask about your courses, your teachers, notices, clubs or anything on campus.
                            </p>

                            <div className="mt-5 flex flex-wrap justify-center gap-2">
                                {suggestions.map((text) => (
                                    <button
                                        key={text}
                                        type="button"
                                        onClick={() => send(text)}
                                        disabled={!available}
                                        className="rounded-full border border-ink-200 px-3.5 py-1.5 text-sm transition hover:border-primary-400 hover:text-primary-600 disabled:opacity-50 dark:border-ink-700 dark:hover:border-primary-500"
                                    >
                                        {text}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {messages.map((message) => (
                        <Bubble
                            key={message.id}
                            role={message.role}
                            body={message.body}
                            tools={message.tools_used}
                        />
                    ))}

                    {pending && (
                        <>
                            <Bubble role="user" body={pending} />
                            <Thinking />
                        </>
                    )}

                    <div ref={endRef} />
                </div>

                <div className="border-t border-ink-200 pt-3 dark:border-ink-800">
                    <div className="flex items-end gap-2">
                        <textarea
                            rows={1}
                            value={form.data.question}
                            maxLength={maxLength}
                            disabled={!available}
                            onChange={(e) => form.setData('question', e.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder="Ask anything about campus…"
                            className="field max-h-40 flex-1 resize-none"
                        />

                        <button
                            type="button"
                            onClick={() => send()}
                            disabled={!available || form.processing || !form.data.question.trim()}
                            className="btn-primary grid h-11 w-11 shrink-0 place-items-center rounded-xl p-0 disabled:opacity-40"
                            aria-label="Send"
                        >
                            <Icon name="send" className="h-5 w-5" />
                        </button>
                    </div>

                    <p className="mt-2 text-center text-xs text-ink-400">
                        {personaName} can only see what you can see. Check anything important against the official notice.
                    </p>
                </div>
            </div>

            </div>
        </AppLayout>
    );
}
