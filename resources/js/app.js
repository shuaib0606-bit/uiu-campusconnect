import Alpine from 'alpinejs';
import axios from 'axios';
import groupChat from './chat';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrf = document.querySelector('meta[name="csrf-token"]');
if (csrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.content;
}

/** Theme handling. The cookie is read server-side by ShareThemePreference. */
function readCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

window.setTheme = function setTheme(theme) {
    applyTheme(theme);
    document.cookie = `cc_theme=${theme}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`;
    window.axios.post('/theme', { theme }).catch(() => {});
};

window.currentTheme = function currentTheme() {
    return readCookie('cc_theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light');
};

/** AJAX and toast layer, so in-list actions update in place. */
window.ccPost = async function ccPost(url, data = {}) {
    try {
        const res = await window.axios.post(url, data);
        return res.data && typeof res.data === 'object' ? res.data : {};
    } catch (e) {
        return { error: true };
    }
};

window.ccToast = function ccToast(message, tone = 'default') {
    window.dispatchEvent(new CustomEvent('cc-toast', { detail: { message, tone } }));
};

document.addEventListener('alpine:init', () => {
    // Generic toggle (react, save, join, follow) with its own active/count state.
    Alpine.data('ccToggle', (config) => ({
        active: !!config.active,
        count: config.count ?? null,
        busy: false,
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            const data = await window.ccPost(config.url, config.payload || {});
            this.busy = false;
            if (data.error) {
                window.ccToast(config.errorMessage || 'Something went wrong. Try again.', 'error');
                return;
            }
            this.active = 'active' in data ? data.active : !this.active;
            if ('count' in data) this.count = data.count;
            if (data.message) window.ccToast(data.message, 'success');
        },
    }));

    // One-shot action (block, mark read, delete) reporting via toast.
    Alpine.data('ccAction', (config) => ({
        busy: false,
        done: false,
        async run() {
            if (this.busy) return;
            this.busy = true;
            const data = await window.ccPost(config.url, config.payload || {});
            this.busy = false;
            if (data.error) {
                window.ccToast(config.errorMessage || 'Something went wrong. Try again.', 'error');
                return;
            }
            this.done = true;
            if (data.message) window.ccToast(data.message, 'success');
            if (config.onDone) config.onDone(data);
        },
    }));

    // Multi-reaction control: a button plus a delayed hover picker.
    Alpine.data('ccReactions', (config) => ({
        active: !!config.active,
        reaction: config.reaction || null,
        count: config.count ?? 0,
        open: false,
        busy: false,
        openTimer: null,
        closeTimer: null,

        show() {
            clearTimeout(this.closeTimer);
            this.openTimer = setTimeout(() => {
                this.open = true;
            }, 180);
        },
        hide() {
            clearTimeout(this.openTimer);
            this.closeTimer = setTimeout(() => {
                this.open = false;
            }, 260);
        },
        destroy() {
            clearTimeout(this.openTimer);
            clearTimeout(this.closeTimer);
        },

        get label() {
            return this.reaction ? config.labels[this.reaction] || 'Like' : 'Like';
        },
        get icon() {
            return this.reaction ? config.icons[this.reaction] || 'thumb-up' : 'thumb-up';
        },

        // Tapping the main button clears an existing reaction, else applies the default.
        primary() {
            this.send(this.reaction || 'like');
        },

        async send(key) {
            if (this.busy) return;
            this.busy = true;
            this.open = false;

            const data = await window.ccPost(config.url, { reaction: key });
            this.busy = false;

            if (data.error) {
                window.ccToast('Could not save your reaction.', 'error');
                return;
            }

            this.active = data.active;
            this.reaction = data.reaction || null;
            if ('count' in data) this.count = data.count;
        },
    }));

    // Shuttle countdown. Ticks one `now` value a second; the timetable is local.
    Alpine.data('ccShuttle', (routes) => ({
        all: routes || [],
        routeKey: (routes && routes[0] && routes[0].key) || '',
        filter: 'all', // all | inbound | outbound
        now: Math.floor(Date.now() / 1000),
        timer: null,

        init() {
            // Remember the route this person rides; storage can be unavailable.
            try {
                const saved = localStorage.getItem('cc.shuttle.route');
                if (saved && this.all.some((r) => r.key === saved)) this.routeKey = saved;
            } catch (e) {}
            this.$watch('routeKey', (key) => {
                try { localStorage.setItem('cc.shuttle.route', key); } catch (e) {}
                // The feed tile shows the next bus for this route too.
                window.dispatchEvent(new CustomEvent('shuttle-route', { detail: key }));
                // AC buses only have trips from campus.
                if (this.route && this.route.kind === 'ac') this.filter = 'all';
            });

            this.timer = setInterval(() => {
                this.now = Math.floor(Date.now() / 1000);
            }, 1000);
        },
        destroy() {
            if (this.timer) clearInterval(this.timer);
        },

        get route() {
            return this.all.find((r) => r.key === this.routeKey) || null;
        },
        get list() {
            if (!this.route) return [];
            return this.route.departures.filter(
                (d) => (this.filter === 'all' || d.dir === this.filter) && (d.until || d.ts) > this.now
            );
        },
        get current() {
            return this.list[0] || null;
        },
        get running() {
            return !!(this.current && this.current.ts <= this.now);
        },
        get rest() {
            return this.list.slice(1, 4);
        },
        get display() {
            if (!this.current) return '\u2014';
            if (this.running) return 'Running now';
            const secs = this.current.ts - this.now;
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            // Seconds are only shown inside the last minute.
            if (h > 0) return `${h}h ${m}m`;
            if (m > 0) return `${m} min`;
            return `${secs}s`;
        },
    }));

    // Floating messenger: a thread list popup plus small chat windows.
    Alpine.data('ccMessenger', () => ({
        listOpen: false,
        loadingThreads: false,
        threads: [],
        windows: [],
        unreadTotal: 0,
        poller: null,
        init() {
            this.loadThreads(true);
            this.poller = setInterval(() => this.loadThreads(false), 25000);
        },
        async loadThreads(showLoading) {
            if (showLoading) this.loadingThreads = true;
            try {
                const res = await window.axios.get('/messages');
                this.threads = res.data.threads || [];
                this.unreadTotal = this.threads.reduce((sum, t) => sum + (t.unread || 0), 0);
            } catch (e) {
                // The widget stays stale until the next poll.
            }
            this.loadingThreads = false;
        },
        toggleList() {
            this.listOpen = !this.listOpen;
            if (this.listOpen) this.loadThreads(true);
        },
        async openChat(t) {
            this.listOpen = false;
            let win = this.windows.find((w) => w.user_id === t.user_id);
            if (!win) {
                this.windows.push({ user_id: t.user_id, name: t.name, avatar_initials: t.avatar_initials, messages: [], draft: '', loading: true });

                // Read the object back out of the array to get Alpine's proxy.
                win = this.windows[this.windows.length - 1];
            }
            win.loading = true;
            try {
                const res = await window.axios.get(`/messages/${t.user_id}`);
                win.messages = res.data.messages || [];
            } catch (e) {
                window.ccToast('Could not load that conversation.', 'error');
            }
            win.loading = false;
            const thread = this.threads.find((x) => x.user_id === t.user_id);
            if (thread) {
                thread.unread = 0;
                this.unreadTotal = this.threads.reduce((sum, x) => sum + (x.unread || 0), 0);
            }
        },
        closeChat(userId) {
            this.windows = this.windows.filter((w) => w.user_id !== userId);
        },
        async send(win) {
            const body = win.draft.trim();
            if (!body) return;
            win.draft = '';
            try {
                const res = await window.axios.post(`/messages/${win.user_id}`, { body });
                if (res.data && res.data.message) {
                    win.messages.push(res.data.message);
                }
            } catch (e) {
                window.ccToast('Could not send that message.', 'error');
            }
        },
    }));

    // Full /messages page: three-column inbox, updated in place via AJAX.
    Alpine.data('messagesPage', (config) => ({
        loadingThreads: true,
        threads: [],
        filter: 'all',
        search: '',
        active: null,
        draft: '',
        sending: false,
        attachment: null,
        init() {
            const openId = /^[a-z0-9]{6,16}$/.test(String(config.initialWith || '')) ? String(config.initialWith) : '';

            // ?with=<id> opens that conversation without waiting for the thread list.
            if (openId) {
                this.openChat({ user_id: openId, name: 'Conversation', avatar_initials: '?' });
            }

            this.loadThreads(0);
        },
        async loadThreads(autoOpenId) {
            this.loadingThreads = true;
            try {
                const res = await window.axios.get('/messages');
                this.threads = res.data.threads || [];
            } catch (e) {
                window.ccToast('Could not load conversations.', 'error');
            }
            this.loadingThreads = false;
            if (autoOpenId) {
                const existing = this.threads.find((t) => t.user_id === autoOpenId);
                this.openChat(existing || { user_id: autoOpenId, name: 'Conversation', avatar_initials: '?' });
            }
        },
        get filteredThreads() {
            let list = this.threads;
            if (this.filter === 'unread') list = list.filter((t) => t.unread > 0);
            if (this.filter === 'recent') list = list.slice(0, 8);
            const q = this.search.trim().toLowerCase();
            if (q) list = list.filter((t) => t.name.toLowerCase().includes(q));
            return list;
        },
        async openChat(t) {
            this.active = {
                user_id: t.user_id, name: t.name, avatar_initials: t.avatar_initials,
                role: t.role || '', designation: t.designation || '', messages: [], loading: true,
            };
            try {
                const res = await window.axios.get(`/messages/${t.user_id}`);
                this.active.messages = res.data.messages || [];
                if (res.data.other_user) {
                    this.active.name = res.data.other_user.name;
                    this.active.avatar_initials = res.data.other_user.avatar_initials;
                    this.active.role = res.data.other_user.role || this.active.role;
                    this.active.designation = res.data.other_user.designation || this.active.designation;
                }
            } catch (e) {
                window.ccToast('Could not load that conversation.', 'error');
            }
            this.active.loading = false;
            const thread = this.threads.find((x) => x.user_id === t.user_id);
            if (thread) thread.unread = 0;
            this.$nextTick(() => this.scrollToBottom());
        },
        scrollToBottom() {
            const el = this.$refs.chatBody;
            if (el) el.scrollTop = el.scrollHeight;
        },
        pickFile(e) {
            this.attachment = e.target.files[0] || null;
        },
        async send() {
            if (!this.active || this.sending || (!this.draft.trim() && !this.attachment)) return;
            this.sending = true;
            const formData = new FormData();
            if (this.draft.trim()) formData.append('body', this.draft.trim());
            if (this.attachment) formData.append('attachment', this.attachment);
            this.draft = '';
            this.attachment = null;
            try {
                const res = await window.axios.post(`/messages/${this.active.user_id}`, formData);
                if (res.data && res.data.message) {
                    this.active.messages.push(res.data.message);
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {
                window.ccToast('Could not send that message.', 'error');
            }
            this.sending = false;
        },
    }));
});

/** Counts .stat-value numbers up from 0 on load. Non-numeric values are left alone. */
function animateCountUp(el) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const raw = el.textContent.trim();
    const match = raw.match(/^(\D*)([\d,]*\d)(\.\d+)?(\D*)$/);
    if (!match) return;
    const [, prefix, wholeStr, decimalStr, suffix] = match;
    const hasComma = wholeStr.includes(',');
    const decimals = decimalStr ? decimalStr.length - 1 : 0;
    const target = parseFloat(wholeStr.replace(/,/g, '') + (decimalStr || ''));
    if (!isFinite(target) || target === 0) return;

    const duration = 700;
    const start = performance.now();

    function format(n) {
        const fixed = decimals ? n.toFixed(decimals) : Math.round(n).toString();
        return hasComma ? Number(fixed).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) : fixed;
    }

    function tick(now) {
        const p = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = `${prefix}${format(target * eased)}${suffix}`;
        if (p < 1) {
            requestAnimationFrame(tick);
        } else {
            el.textContent = raw;
        }
    }

    requestAnimationFrame(tick);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.stat-value').forEach(animateCountUp);
});

/** News feed: infinite scroll, plus seen-post tracking for the ranker. */
document.addEventListener('alpine:init', () => {
    Alpine.data('ccFeed', (config) => ({
        endpoint: config.endpoint,
        seenEndpoint: config.seenEndpoint,
        tab: config.tab || 'for-you',
        cursor: config.cursor || null,
        // No cursor means there is nothing more, whatever hasMore says.
        hasMore: !!config.hasMore && !!config.cursor,
        loading: false,
        failed: false,
        sentinelVisible: false,
        loadObserver: null,
        seenObserver: null,
        pendingSeen: [],
        seenTimer: null,
        onHide: null,

        init() {
            // Without IntersectionObserver the list stays a plain first page.
            if (!('IntersectionObserver' in window)) {
                this.hasMore = false;
                return;
            }

            this.loadObserver = new IntersectionObserver(
                (entries) => {
                    this.sentinelVisible = entries.some((e) => e.isIntersecting);
                    if (this.sentinelVisible) this.loadMore();
                },
                // Fetch this far ahead of the end of the list.
                { rootMargin: '800px 0px' }
            );

            this.seenObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        // A post over 70% of viewport height counts as seen.
                        const tall = entry.boundingClientRect.height > window.innerHeight * 0.7;
                        if (entry.intersectionRatio < 0.6 && !tall) return;
                        this.seenObserver.unobserve(entry.target);
                        this.queueSeen(parseInt(entry.target.dataset.postId, 10));
                    });
                },
                { threshold: [0.3, 0.6] }
            );

            this.onHide = () => {
                if (document.visibilityState === 'hidden') this.flushSeen();
            };
            document.addEventListener('visibilitychange', this.onHide);

            this.$nextTick(() => {
                if (this.$refs.sentinel) this.loadObserver.observe(this.$refs.sentinel);
                this.watchSeen();
            });
        },

        destroy() {
            if (this.loadObserver) this.loadObserver.disconnect();
            if (this.seenObserver) this.seenObserver.disconnect();
            if (this.onHide) document.removeEventListener('visibilitychange', this.onHide);
            this.flushSeen();
        },

        async loadMore() {
            // The in-flight guard is set before the first await.
            if (this.loading || !this.hasMore || !this.cursor) return;
            this.loading = true;
            this.failed = false;

            try {
                const res = await window.axios.get(this.endpoint, {
                    params: { tab: this.tab, cursor: this.cursor },
                });
                const data = res.data || {};
                const html = (data.html || '').trim();

                if (html && this.$refs.list) {
                    this.$refs.list.insertAdjacentHTML('beforeend', html);
                    window.ccHighlight(this.$refs.list);
                }

                this.cursor = data.nextCursor || null;
                // An empty batch ends the feed whatever the server claims.
                this.hasMore = !!data.hasMore && !!this.cursor && !!html;
            } catch (e) {
                // hasMore and cursor are left as they are, so "Try again" retries.
                this.failed = true;
            }

            this.loading = false;

            this.$nextTick(() => {
                this.watchSeen();
                // A sentinel still on screen will not fire the observer again.
                if (this.hasMore && this.sentinelVisible && !this.failed) {
                    setTimeout(() => {
                        if (this.sentinelVisible) this.loadMore();
                    }, 200);
                }
            });
        },

        // Observes any post not already flagged as watched.
        watchSeen() {
            if (!this.seenObserver || !this.$refs.list) return;
            this.$refs.list
                .querySelectorAll('article[data-post-id]:not([data-cc-seen-watched])')
                .forEach((el) => {
                    el.setAttribute('data-cc-seen-watched', '');
                    this.seenObserver.observe(el);
                });
        },

        queueSeen(id) {
            if (!id || !this.seenEndpoint || this.pendingSeen.includes(id)) return;
            this.pendingSeen.push(id);

            // Flush on size as well as on the debounce timer.
            if (this.pendingSeen.length >= 25) {
                this.flushSeen();
                return;
            }

            clearTimeout(this.seenTimer);
            this.seenTimer = setTimeout(() => this.flushSeen(), 2000);
        },

        flushSeen() {
            clearTimeout(this.seenTimer);
            if (!this.pendingSeen.length || !this.seenEndpoint) return;
            // Failures are silent.
            window.ccPost(this.seenEndpoint, { ids: this.pendingSeen.splice(0, 100) });
        },
    }));
});

/**
 * Colours any code blocks on the page, pulling the highlighter in as its own
 * chunk the first time one is actually present. Most people never post or
 * read code, and they should not download a parser for it.
 */
let highlighterLoading = null;

window.ccHighlight = function ccHighlight(root = document) {
    if (!root.querySelector('pre code[data-language]:not([data-highlighted])')) {
        return;
    }

    highlighterLoading = highlighterLoading || import('./code-highlight.js');

    highlighterLoading
        .then(({ highlight }) => highlight(root))
        // No colours is a perfectly readable outcome; the text is already there.
        .catch(() => {});
};

document.addEventListener('DOMContentLoaded', () => window.ccHighlight());

/**
 * Document types that may be attached, matched on the file name. Kept in step
 * with PostController::ALLOWED_EXTENSIONS, which is what actually enforces it.
 */
const ALLOWED_DOCUMENTS = /\.(pdf|docx?|pptx?|xlsx?|csv|txt|md|zip)$/i;

/** Post composer. The form is submitted natively, so input.files is the upload. */
document.addEventListener('alpine:init', () => {
    Alpine.data('ccComposer', (config = {}) => ({
        maxFiles: config.maxFiles || 10,
        maxFileSize: config.maxFileSize || 220 * 1024 * 1024,
        maxBody: config.maxBody || 5000,
        maxPollOptions: config.maxPollOptions || 6,
        searchUrl: config.searchUrl || '',

        open: false,
        busy: false,
        body: '',
        visibility: config.visibility || 'uiu',
        items: [],
        error: '',
        poll: false,
        options: ['', ''],
        // Code mode: the body becomes the snippet, so it cannot also be a poll.
        code: false,
        codeLanguage: 'text',
        dragging: false,
        seq: 0,

        // Mention picker state.
        mOpen: false,
        mItems: [],
        mIndex: 0,
        mStart: -1,
        mEnd: -1,
        mTimer: null,
        mRequest: 0,

        init() {
            // Object URLs have to be released on navigation away as well.
            this.onUnload = () => this.releaseAll();
            window.addEventListener('beforeunload', this.onUnload);
        },

        destroy() {
            window.removeEventListener('beforeunload', this.onUnload);
            this.releaseAll();
        },

        // State

        get charsLeft() {
            return this.maxBody - this.body.length;
        },

        // The counter appears within 500 characters of the limit.
        get showCounter() {
            return this.body.length > this.maxBody - 500;
        },

        get hasContent() {
            return this.body.trim().length > 0 || this.items.length > 0 || this.filledOptions.length > 0;
        },

        get filledOptions() {
            return this.options.map((o) => o.trim()).filter((o) => o !== '');
        },

        get canPost() {
            return !this.busy && this.body.trim().length > 0 && this.body.length <= this.maxBody;
        },

        // Open / close

        openComposer(mode) {
            // The file dialog must open synchronously inside the click handler.
            if (mode === 'media') {
                this.pick();
            }

            this.open = true;
            this.error = '';
            if (mode === 'poll') this.poll = true;
            if (mode === 'code') this.toggleCode(true);
            document.body.style.overflow = 'hidden';

            this.$nextTick(() => {
                if (mode !== 'media' && this.$refs.body) this.$refs.body.focus();
            });
        },

        requestClose() {
            if (this.busy) return;
            if (this.hasContent && !window.confirm('Discard this post?')) return;
            this.closeComposer(true);
        },

        closeComposer(reset) {
            this.open = false;
            this.closeMentions();
            document.body.style.overflow = '';
            if (reset) this.reset();
            this.$nextTick(() => {
                if (this.$refs.trigger) this.$refs.trigger.focus();
            });
        },

        reset() {
            this.releaseAll();
            this.items = [];
            this.body = '';
            this.poll = false;
            this.options = ['', ''];
            this.error = '';
            this.dragging = false;
            this.syncInput();
        },

        // Attachments

        pick() {
            if (this.$refs.input) this.$refs.input.click();
        },

        addFiles(fileList) {
            const incoming = Array.from(fileList || []);
            if (!incoming.length) return;

            this.error = '';
            const accepted = [];

            for (const file of incoming) {
                if (this.items.length + accepted.length >= this.maxFiles) {
                    this.error = `You can attach up to ${this.maxFiles} files to one post.`;
                    break;
                }

                const kind = file.type.startsWith('video/')
                    ? 'video'
                    : (file.type.startsWith('image/') ? 'image' : 'file');

                // Documents are attached by extension rather than by MIME
                // type: browsers report an empty type for plenty of ordinary
                // files, and the server checks the extension again anyway.
                if (kind === 'file' && !ALLOWED_DOCUMENTS.test(file.name)) {
                    this.error = `"${file.name}" is not a file type you can attach.`;
                    continue;
                }

                if (file.size > this.maxFileSize) {
                    this.error = `"${file.name}" is ${this.humanSize(file.size)} - the limit is ${this.humanSize(this.maxFileSize)} per file.`;
                    continue;
                }

                accepted.push({
                    key: ++this.seq,
                    file,
                    // A document has nothing to preview, so no object URL is
                    // made for it - and nothing to revoke later either.
                    url: kind === 'file' ? null : URL.createObjectURL(file),
                    kind,
                    name: file.name,
                    size: file.size,
                });
            }

            if (accepted.length) this.items.push(...accepted);
            this.syncInput();
        },

        remove(index) {
            const item = this.items[index];
            if (!item) return;
            this.release(item);
            this.items.splice(index, 1);
            this.error = '';
            this.syncInput();
        },

        move(index, delta) {
            const target = index + delta;
            if (target < 0 || target >= this.items.length) return;
            const moved = this.items.splice(index, 1)[0];
            this.items.splice(target, 0, moved);
            this.syncInput();
        },

        /** Rebuilds input.files so it matches this.items in content and order. */
        syncInput() {
            const input = this.$refs.input;
            if (!input) return;

            try {
                const transfer = new DataTransfer();
                this.items.forEach((item) => transfer.items.add(Alpine.raw(item.file)));
                input.files = transfer.files;
            } catch (e) {
                // Some browsers do not allow assigning to input.files.
                this.error = 'This browser cannot reorder attachments; they will upload in the order you picked them.';
            }
        },

        release(item) {
            if (item && item.url) URL.revokeObjectURL(item.url);
        },

        releaseAll() {
            this.items.forEach((item) => this.release(item));
        },

        onDrop(event) {
            this.dragging = false;
            const dropped = event.dataTransfer ? event.dataTransfer.files : null;
            if (dropped && dropped.length) this.addFiles(dropped);
        },

        humanSize(bytes) {
            if (bytes >= 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024 / 1024).toFixed(1)} GB`;
            if (bytes >= 1024 * 1024) return `${Math.round(bytes / 1024 / 1024)} MB`;
            return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        },

        // Preview geometry, mirroring the layouts _post.blade.php renders

        get gridClass() {
            const n = this.items.length;
            if (n === 1) return 'grid-cols-1';
            if (n === 3) return 'grid-cols-2 grid-rows-2';
            return 'grid-cols-2';
        },

        tileClass(index) {
            const n = this.items.length;
            if (n === 1) return 'aspect-[4/3]';
            if (n === 3 && index === 0) return 'row-span-2 h-full min-h-64';
            return 'aspect-square';
        },

        /** How many attachments the feed will fold into the 4th tile's badge. */
        get hiddenInFeed() {
            return this.items.length > 4 ? this.items.length - 4 : 0;
        },

        // Poll

        addOption() {
            if (this.options.length >= this.maxPollOptions) return;
            this.options.push('');
        },

        removeOption(index) {
            if (this.options.length <= 2) return;
            this.options.splice(index, 1);
        },

        togglePoll() {
            this.poll = !this.poll;
            this.error = '';
            if (!this.poll) this.options = ['', ''];
            // A snippet with poll options attached is a mistake, not both.
            if (this.poll) this.toggleCode(false);
        },

        toggleCode(on) {
            this.code = typeof on === 'boolean' ? on : !this.code;
            this.error = '';

            if (this.code) {
                this.poll = false;
                this.options = ['', ''];
            } else {
                this.codeLanguage = 'text';
            }
        },

        // Mentions

        onBodyInput() {
            this.detectMention();
        },

        /** Is the caret inside an @token? The pattern mirrors Mentions::PATTERN. */
        detectMention() {
            const el = this.$refs.body;
            if (!el || !this.searchUrl) return this.closeMentions();

            const caret = el.selectionStart;
            const before = this.body.slice(0, caret);
            const match = before.match(/(?:^|[^A-Za-z0-9_@.\-])@([A-Za-z0-9][A-Za-z0-9._\-]{0,48})?$/);

            if (!match) return this.closeMentions();

            const token = match[1] || '';
            this.mStart = caret - token.length - 1;
            this.mEnd = caret;
            this.queryMentions(token);
        },

        queryMentions(token) {
            clearTimeout(this.mTimer);
            // Debounced by 200 ms.
            this.mTimer = setTimeout(async () => {
                const ticket = ++this.mRequest;
                try {
                    const res = await window.axios.get(this.searchUrl, { params: { q: token } });
                    if (ticket !== this.mRequest) return;
                    const results = (res.data && res.data.results) || [];
                    this.mItems = results.map((u) => ({ ...u, display: '@' + u.handle }));
                    this.mIndex = 0;
                    this.mOpen = this.mItems.length > 0;
                } catch (e) {
                    if (ticket === this.mRequest) this.closeMentions();
                }
            }, 200);
        },

        closeMentions() {
            clearTimeout(this.mTimer);
            this.mOpen = false;
            this.mItems = [];
            this.mIndex = 0;
        },

        onBodyKeydown(event) {
            if (!this.mOpen || !this.mItems.length) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.mIndex = (this.mIndex + 1) % this.mItems.length;
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.mIndex = (this.mIndex - 1 + this.mItems.length) % this.mItems.length;
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                this.chooseMention(this.mItems[this.mIndex]);
            } else if (event.key === 'Escape') {
                // Stopped here so Escape does not also close the dialog behind.
                event.preventDefault();
                event.stopPropagation();
                this.closeMentions();
            }
        },

        chooseMention(item) {
            if (!item) return;
            const el = this.$refs.body;
            const before = this.body.slice(0, this.mStart);
            const after = this.body.slice(this.mEnd);
            const inserted = '@' + item.handle + ' ';

            this.body = before + inserted + after;
            this.closeMentions();

            this.$nextTick(() => {
                if (!el) return;
                el.focus();
                const caret = (before + inserted).length;
                el.setSelectionRange(caret, caret);
            });
        },

        // Submit

        validate() {
            if (this.body.trim() === '') return 'Write something to go with your post.';
            if (this.body.length > this.maxBody) return `Your post is ${this.body.length - this.maxBody} characters over the limit.`;
            if (this.items.length > this.maxFiles) return `You can attach up to ${this.maxFiles} files to one post.`;

            const oversized = this.items.find((i) => i.size > this.maxFileSize);
            if (oversized) return `"${oversized.name}" is larger than ${this.humanSize(this.maxFileSize)}.`;

            if (this.poll && this.filledOptions.length < 2) return 'A poll needs at least two options.';

            return '';
        },

        onSubmit(event) {
            // Second click while the first submit is in flight.
            if (this.busy) {
                event.preventDefault();
                return;
            }

            const problem = this.validate();
            if (problem) {
                event.preventDefault();
                this.error = problem;
                return;
            }

            // The native submit continues from here; busy disables the controls.
            this.busy = true;
        },
    }));
});

/**
 * Mention autocomplete for a plain <input>/<textarea>. It never owns the value,
 * and its token rules match Mentions::PATTERN on the server.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('ccMentionField', (config = {}) => ({
        searchUrl: config.searchUrl || '',
        mOpen: false,
        mItems: [],
        mIndex: 0,
        mStart: 0,
        mEnd: 0,
        mTimer: null,
        mRequest: 0,

        detect() {
            const el = this.$refs.field;
            if (!el || !this.searchUrl) return this.close();

            const caret = el.selectionStart;
            const before = el.value.slice(0, caret);
            const match = before.match(/(?:^|[^A-Za-z0-9_@.\-])@([A-Za-z0-9][A-Za-z0-9._\-]{0,48})?$/);

            if (!match) return this.close();

            const token = match[1] || '';
            this.mStart = caret - token.length - 1;
            this.mEnd = caret;
            this.query(token);
        },

        query(token) {
            clearTimeout(this.mTimer);
            this.mTimer = setTimeout(async () => {
                const ticket = ++this.mRequest;
                try {
                    const res = await window.axios.get(this.searchUrl, { params: { q: token } });
                    if (ticket !== this.mRequest) return;
                    this.mItems = ((res.data && res.data.results) || []);
                    this.mIndex = 0;
                    this.mOpen = this.mItems.length > 0;
                } catch (e) {
                    if (ticket === this.mRequest) this.close();
                }
            }, 200);
        },

        close() {
            clearTimeout(this.mTimer);
            this.mOpen = false;
            this.mItems = [];
            this.mIndex = 0;
        },

        onKeydown(event) {
            if (!this.mOpen || !this.mItems.length) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.mIndex = (this.mIndex + 1) % this.mItems.length;
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.mIndex = (this.mIndex - 1 + this.mItems.length) % this.mItems.length;
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                // Enter picks the highlighted person rather than submitting.
                event.preventDefault();
                event.stopPropagation();
                this.choose(this.mItems[this.mIndex]);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                this.close();
            }
        },

        choose(item) {
            if (!item) return;
            const el = this.$refs.field;
            if (!el) return;

            const before = el.value.slice(0, this.mStart);
            const after = el.value.slice(this.mEnd);
            const inserted = '@' + item.handle + ' ';

            el.value = before + inserted + after;
            this.close();

            this.$nextTick(() => {
                el.focus();
                const caret = (before + inserted).length;
                el.setSelectionRange(caret, caret);
            });
        },
    }));

    // Group chat. Its Echo and Pusher imports are dynamic, so pages
    // without a chat never download them.
    Alpine.data('groupChat', groupChat);
});

window.Alpine = Alpine;
Alpine.start();
