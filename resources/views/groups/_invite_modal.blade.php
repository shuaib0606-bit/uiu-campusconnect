{{--
    Invite people to a student-made group. Only its creator sees this.

    Two ways to find people: search anyone by name, ID or email, or pick
    classmates from a class you are taking (all of them, or some). A student
    only ever sees faculty and staff they are connected with - the server
    applies the same rule, so the box never offers someone it would refuse.
    Invitations are not memberships: people join once they accept.
--}}
<div x-data="ccInvite({
        searchUrl: @js(route('groups.invite.search', $group)),
        coursesUrl: @js(route('groups.invite.courses', $group)),
        classmatesBase: @js(url('/groups/'.$group->id.'/invite/courses')),
        storeUrl: @js(route('groups.invites.store', $group)),
    })"
    x-show="open" x-cloak
    @keydown.escape.window="close()"
    class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="invite-title">
    <div class="absolute inset-0 bg-black/30 backdrop-blur-[1px]" @click="close()"></div>

    <div class="absolute inset-x-0 bottom-0 mx-auto flex max-h-[88vh] w-full max-w-lg flex-col rounded-t-3xl bg-white shadow-xl dark:bg-ink-900 sm:inset-y-0 sm:my-auto sm:h-fit sm:rounded-3xl">
        <div class="flex items-start justify-between gap-3 border-b border-ink-100 px-5 py-4 dark:border-ink-800">
            <div>
                <h2 id="invite-title" class="text-[16px] font-semibold">Invite people</h2>
                <p class="section-sub">They get a notification and join once they accept.</p>
            </div>
            <button type="button" class="btn-icon" @click="close()" aria-label="Close"><x-ui-icon name="x" class="h-5 w-5" /></button>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 px-5 pt-3">
            <button type="button" @click="tab = 'search'"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="tab === 'search' ? 'bg-primary-500/10 text-primary-700 dark:text-primary-300' : 'text-ink-500 hover:bg-ink-900/[0.04] dark:text-ink-400'">
                Search people
            </button>
            <button type="button" @click="tab = 'courses'; loadCourses()"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="tab === 'courses' ? 'bg-primary-500/10 text-primary-700 dark:text-primary-300' : 'text-ink-500 hover:bg-ink-900/[0.04] dark:text-ink-400'">
                From my courses
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-3">
            {{-- Search --}}
            <div x-show="tab === 'search'">
                <input type="search" x-ref="q" x-model="q" @input.debounce.300ms="search()"
                    placeholder="Name, student ID or email" class="field" autocomplete="off">
                @if (auth()->user()->role === 'student')
                    <p class="section-sub mt-1.5">Faculty and staff appear only if you're connected with them.</p>
                @endif

                <p x-show="loading" class="mt-4 text-sm text-ink-400">Searching…</p>
                <p x-show="!loading && q.trim().length >= 2 && results.length === 0" class="mt-4 text-sm text-ink-400">No one found.</p>

                <div class="mt-3 space-y-1">
                    <template x-for="p in results" :key="'s' + p.id">
                        @include('groups._invite_person')
                    </template>
                </div>
            </div>

            {{-- From my courses --}}
            <div x-show="tab === 'courses'" x-cloak>
                <p x-show="courses === null" class="text-sm text-ink-400">Loading your classes…</p>
                <p x-show="courses !== null && courses.length === 0" class="text-sm text-ink-400">You're not enrolled in any classes right now.</p>

                <div class="flex flex-wrap gap-2" x-show="courses && courses.length">
                    <template x-for="c in (courses || [])" :key="c.id">
                        <button type="button" @click="pickCourse(c.id)"
                            class="rounded-xl border px-3 py-2 text-left text-sm transition"
                            :class="course === c.id ? 'border-primary-500 bg-primary-500/5' : 'border-ink-200 hover:border-ink-300 dark:border-ink-700'">
                            <span class="block font-semibold" x-text="c.label"></span>
                            <span class="block text-xs text-ink-500" x-text="(c.title ? c.title + ' · ' : '') + c.students + ' classmates'"></span>
                        </button>
                    </template>
                </div>

                <template x-if="course">
                    <div class="mt-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold">Classmates</p>
                            <button type="button" x-show="invitableMates.length" @click="toggleAll()"
                                class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-300"
                                x-text="allMatesSelected ? 'Clear all' : 'Select all (' + invitableMates.length + ')'"></button>
                        </div>
                        <p x-show="loading" class="mt-3 text-sm text-ink-400">Loading…</p>
                        <p x-show="!loading && classmates.length === 0" class="mt-3 text-sm text-ink-400">No other students in this class.</p>
                        <div class="mt-2 space-y-1">
                            <template x-for="p in classmates" :key="'c' + p.id">
                                @include('groups._invite_person')
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Footer --}}
        <div class="border-t border-ink-100 px-5 py-3.5 dark:border-ink-800">
            <p x-show="message" x-cloak class="mb-2 text-sm" :class="error ? 'text-red-600 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300'" x-text="message"></p>
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-ink-500" x-text="count === 0 ? 'Nobody selected' : count + ' selected'"></span>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost" @click="close()">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="count === 0 || sending"
                        :class="(count === 0 || sending) && 'opacity-50 cursor-not-allowed'" @click="send()"
                        x-text="sending ? 'Sending…' : (count > 1 ? 'Send ' + count + ' invitations' : 'Send invitation')"></button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.ccInvite = (cfg) => ({
        open: false,
        tab: 'search',
        q: '',
        loading: false,
        results: [],
        courses: null,
        course: null,
        classmates: [],
        selected: {},
        message: '',
        error: false,
        sending: false,

        init() {
            window.addEventListener('open-invite', () => {
                this.open = true;
                this.$nextTick(() => this.$refs.q && this.$refs.q.focus());
            });
        },
        close() { this.open = false; },

        async getJson(url) {
            const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error('Request failed');
            return res.json();
        },
        async search() {
            const term = this.q.trim();
            if (term.length < 2) { this.results = []; return; }
            this.loading = true;
            try { this.results = (await this.getJson(cfg.searchUrl + '?q=' + encodeURIComponent(term))).results; }
            catch (e) { this.results = []; }
            this.loading = false;
        },
        async loadCourses() {
            if (this.courses !== null) return;
            try { this.courses = (await this.getJson(cfg.coursesUrl)).courses; } catch (e) { this.courses = []; }
        },
        async pickCourse(id) {
            this.course = id;
            this.classmates = [];
            this.loading = true;
            try { this.classmates = (await this.getJson(cfg.classmatesBase + '/' + id)).results; } catch (e) { this.classmates = []; }
            this.loading = false;
        },

        isSelected(p) { return !!this.selected[p.id]; },
        toggle(p) {
            if (!p.can_invite) return;
            if (this.selected[p.id]) { delete this.selected[p.id]; } else { this.selected[p.id] = p; }
            this.selected = { ...this.selected };
        },
        get invitableMates() { return this.classmates.filter((p) => p.can_invite); },
        get allMatesSelected() {
            return this.invitableMates.length > 0 && this.invitableMates.every((p) => this.selected[p.id]);
        },
        toggleAll() {
            const clear = this.allMatesSelected;
            this.invitableMates.forEach((p) => { if (clear) { delete this.selected[p.id]; } else { this.selected[p.id] = p; } });
            this.selected = { ...this.selected };
        },
        get count() { return Object.keys(this.selected).length; },

        async send() {
            if (this.count === 0 || this.sending) return;
            this.sending = true;
            this.message = '';
            try {
                const res = await fetch(cfg.storeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ user_ids: Object.keys(this.selected).map(Number) }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Could not send invitations.');
                this.error = data.sent === 0;
                this.message = data.message + (data.skipped && data.skipped.length ? ' Skipped: ' + data.skipped.join(' ') : '');
                if (data.sent > 0) {
                    this.selected = {};
                    setTimeout(() => window.location.reload(), 1400);
                }
            } catch (e) {
                this.error = true;
                this.message = e.message || 'Could not send invitations.';
            }
            this.sending = false;
        },
    });
</script>
@endpush
